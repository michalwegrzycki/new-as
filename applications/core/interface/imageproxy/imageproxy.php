<?php
/**
* @brief		Image Proxy
* @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
* @copyright	(c) Invision Power Services, Inc.
* @license		https://www.invisioncommunity.com/legal/standards/
* @package		Invision Community
* @since		29 Jun 2015
* @version		SVN_VERSION_NUMBER
*/

/* Init */
require_once str_replace( 'applications/core/interface/imageproxy/imageproxy.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
$url = \IPS\Request::i()->img;

/* Check for a valid key */
if ( !\IPS\Login::compareHashes( hash_hmac( "sha256", $url, \IPS\Settings::i()->site_secret_key ), (string) \IPS\Request::i()->key ) )
{
	\IPS\Output::i()->sendOutput( NULL, 500 );
}


/* Check the cache */
try
{
	$cacheEntry = \IPS\Db::i()->select( '*', 'core_image_proxy', array( 'md5_url=?', md5( $url ) ) )->first();

	/* If we have a cache entry, but it is over an hour old and the location is NULL, try to refetch */
	if( $cacheEntry['location'] === NULL AND $cacheEntry['cache_time'] < time() - 3600 )
	{
		\IPS\Db::i()->delete( 'core_image_proxy', array( 'md5_url=?', $cacheEntry['md5_url'] ) );
		throw new \UnderflowException;
	}
	
	if ( $cacheEntry['location'] )
	{
		/* Set the cache expiration time */
		$cacheExpires = new \DateTime;  // Use of \DateTime is intentional, do not replace with \IPS\DateTime
		$cacheExpires->setTimestamp( (int) $cacheEntry['cache_time'] );
		$cacheExpires->add( new \DateInterval( ( \IPS\Settings::i()->image_proxy_cache_period ) ? sprintf( 'P%dD', \IPS\Settings::i()->image_proxy_cache_period ) : 'P1Y' ) );

		$file = \IPS\File::get( 'core_Imageproxycache', $cacheEntry['location'] );
	}
	else
	{
		\IPS\Output::i()->sendOutput( NULL, 500 );
	}
}

/* Not in cache - fetch and store */
catch ( \UnderflowException $e )
{
	/* If the image proxy is disabled and the image isn't already stored, 301. This prevents images being stored when image proxy is disabled */
	if( !\IPS\Settings::i()->remote_image_proxy )
	{
		\IPS\Output::i()->redirect( \IPS\Http\Url::external( mb_substr( $url, 0, 2 ) === '//' ? "http:{$url}" : $url ) );
	}

	/* Set the cache expiration time */
	$cacheExpires = new \DateTime;  // Use of \DateTime is intentional, do not replace with \IPS\DateTime
	$cacheExpires->add( new \DateInterval( ( \IPS\Settings::i()->image_proxy_cache_period ) ? sprintf( 'P%dD', \IPS\Settings::i()->image_proxy_cache_period ) : 'P1Y' ) );

	/* First, let's store a placeholder row that we will later update - this prevents being able to DOS the server if the image is crazy */
	\IPS\Db::i()->replace( 'core_image_proxy', array(
		'md5_url'		=> md5( $url ),
		'location'		=> NULL,
		'cache_time'	=> time(),
	) );

	try
	{
		$output = \IPS\Http\Url::external( mb_substr( $url, 0, 2 ) === '//' ? "http:{$url}" : $url )->request()->get();
		/* Check it's a valid image */
		$image = \IPS\Image::create( (string) $output );
		$imageExtension = $image->type;
		unset( $image );
	}
	catch ( \Exception $e )
	{
		\IPS\Output::i()->sendOutput( NULL, 500 );
	}
	
	/* Work out an appropriate filename */
	$extension = mb_substr( $url, mb_strrpos( $url, '.' ) + 1 );
	if ( in_array( $extension, \IPS\Image::$imageExtensions ) )
	{
		$filename = mb_substr( $url, mb_strrpos( $url, '/' ) + 1 );
		if ( mb_strlen( $filename ) > 200 )
		{
			$filename = mb_substr( $filename, 0, 150 ) . '.' . $extension;
		}
	}
	else
	{
		$filename = md5( uniqid() ) . '.' . $imageExtension;
	}

	/* Cache */
	$file = \IPS\File::create( 'core_Imageproxycache', $filename, (string) $output, 'imageproxy' );
	\IPS\Db::i()->replace( 'core_image_proxy', array(
		'md5_url'		=> md5( $url ),
		'location'		=> (string) $file,
		'cache_time'	=> time(),
	) );
}

try
{
	/* Output */
	\IPS\Output::i()->sendOutput( $file->contents(), 200, \IPS\File::getMimeType( $file->filename ), array(
		'cache-control' => 'public, max_age=' . max( ( $cacheExpires->getTimestamp() - time() ), 0 ) . ', must-revalidate',
		'expires' => $cacheExpires->format( 'D, d M Y H:i:s \G\M\T' )
	) );
}
catch (  \RuntimeException $e )
{
	\IPS\Log::debug( "Failed fetching proxy image", 'imageProxy' );
}