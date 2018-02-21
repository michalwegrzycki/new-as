<?php
/**
 * @brief		Friendly URL
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 May 2015
 */

namespace IPS\Http\Url;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Friendly URL
 */
class _Friendly extends \IPS\Http\Url\Internal
{
	/**
	 * @brief	Base
	 */
	public $base = 'front';
	
	/**
	 * @brief	SEO Template
	 */
	public $seoTemplate;
	
	/**
	 * @brief	SEO Titles (unencoded)
	 */
	public $seoTitles = array();
	
	/**
	 * @brief	The friendly URL component, which may be for the path or the query string (e.g. "topic/1-test")
	 */
	public $friendlyUrlComponent;
	
	/* !Factory methods */
	
	/**
	 * Create a friendly URL from a query string
	 *
	 * @param	int				$protocol				Protocol (one of the PROTOCOL_* constants)
	 * @param	string			$friendlyUrlComponent	The friendly URL component, which may be for the path or the query string (e.g. "topic/1-test")
	 * @param	array			$queryString			Additional query string data
	 * @return	\IPS\Http\Url\Friendly
	 * @throws	\IPS\Http\Url\Exception
	 */
	public static function friendlyUrlFromComponent( $protocol, $friendlyUrlComponent, $queryString )
	{		
		if ( \IPS\Settings::i()->htaccess_mod_rewrite )
		{
			return static::createInternalFromComponents(
				'front',
				$protocol,
				$friendlyUrlComponent ? "{$friendlyUrlComponent}/" : '',
				$queryString
			);
		}
		else
		{
			return static::createInternalFromComponents(
				'front',
				$protocol,
				'index.php',
				( $friendlyUrlComponent ? array( "/{$friendlyUrlComponent}/" => NULL ) : array() ) + $queryString
			);
		}
	}
	
	/**
	 * Create a friendly URL from a full URL, working out friendly URL data
	 *
	 * @param	array		$components			An array of components as returned by componentsFromUrlString()
	 * @param	string		$potentialFurl		The potential FURL slug (e.g. "topic/1-test")
	 * @return	\IPS\Http\Url\Friendly|NULL
	 */
	public static function createFriendlyUrlFromComponents( $components, $potentialFurl )
	{
		/* Loop each of our friendly URL definitions */
		$matchedFurlDefinition = NULL;
		$seoTitles = array();
		foreach ( static::furlDefinition() as $seoTemplate => $furlDefinition )
		{		
			if ( $returnedMatches = static::getMatchedParamsFromFriendlyUrlComponent( $furlDefinition, $potentialFurl ) )
			{
				$matchedFurlDefinition = $furlDefinition;
				list( $matchedParams, $seoTitles ) = $returnedMatches;
				break;
			}
		}
				
		/* If we didn't get one return NULL */
		if ( !$matchedFurlDefinition )
		{
			return NULL;
		}
		
		/* If we did, create our object... */
		return static::createFromComponents( $components[ static::COMPONENT_HOST ], $components[ static::COMPONENT_SCHEME ], $components[ static::COMPONENT_PATH ], $components[ static::COMPONENT_QUERY ], $components[ static::COMPONENT_PORT ], $components[ static::COMPONENT_USERNAME ], $components[ static::COMPONENT_PASSWORD ], $components[ static::COMPONENT_FRAGMENT ] )
			->setFriendlyUrlData( $seoTemplate, $seoTitles, $matchedParams, $potentialFurl );
	}
	
	/**
	 * Create a friendly URL from a query string with known friendly URL data
	 *
	 * @param	string			$queryString	The query string
	 * @param	string			$seoTemplate	The key for making this a friendly URL
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @param	int				$protocol		Protocol (one of the PROTOCOL_* constants)
	 * @return	\IPS\Http\Url\Friendly
	 * @throws	\IPS\Http\Url\Exception
	 * @todo	Currently this will silently return a non-friendly URL if $seoTemplate is not valid, for backwards compatibility. Remove this in a major update.
	 */
	public static function friendlyUrlFromQueryString( $queryString, $seoTemplate, $seoTitles, $protocol )
	{
		/* Get the friendly URL component */
		try
		{
			$friendlyUrlComponent = static::buildFriendlyUrlComponentFromData( $queryString, $seoTemplate, $seoTitles );
		}
		catch ( \IPS\Http\Url\Exception $e )
		{
			if ( $e->getMessage() === 'INVALID_SEO_TEMPLATE' and !\IPS\IN_DEV )
			{
				return static::createInternalFromComponents( 'front', $protocol, 'index.php', $queryString );
			}
			else
			{
				throw $e;
			}
		}
		
		/* Extract the hidden query string parameters inside it */
		$matchedParams = array();
		$furlDefinition = static::furlDefinition();
		if ( !isset( $furlDefinition[ $seoTemplate ] ) )
		{
			throw new \IPS\Http\Url\Exception( 'INVALID_SEO_TEMPLATE' );
		}
		if ( $returnedMatches = static::getMatchedParamsFromFriendlyUrlComponent( $furlDefinition[ $seoTemplate ], $friendlyUrlComponent ) )
		{
			list( $matchedParams, $_seoTitles ) = $returnedMatches;
		}
		else
		{
			if ( \IPS\IN_DEV )
			{
				throw new \IPS\Http\Url\Exception( 'SEO_TEMPLATE_IS_NOT_VALID_FOR_URL' );
			}
		}
		
		/* Return */
		return static::friendlyUrlFromComponent( $protocol, $friendlyUrlComponent, $queryString )->setFriendlyUrlData( $seoTemplate, $seoTitles, $matchedParams, $friendlyUrlComponent );
	}
	
	/* !Instance */
	
	/**
	 * Set friendly URL data
	 *
	 * @param	string			$seoTemplate			The key for making this a friendly URL
	 * @param	string|array	$seoTitles				The title(s) needed for the friendly URL
	 * @param	array			$matchedParams			The values for hidden query string properties
	 * @param	string			$friendlyUrlComponent	The friendly URL component, which may be for the path or the query string (e.g. "topic/1-test")
	 * @return	\IPS\Http\Url\Friendly
	 * @throws	\IPS\Http\Url\Exception
	 */
	protected function setFriendlyUrlData( $seoTemplate, $seoTitles, $matchedParams=array(), $friendlyUrlComponent )
	{
		/* Get the definition */
		$furlDefinition = static::furlDefinition();
		if ( !isset( $furlDefinition[ $seoTemplate ] ) )
		{
			throw new \IPS\Http\Url\Exception( 'INVALID_SEO_TEMPLATE' );
		}
		
		/* Set basic properties */
		$this->seoTemplate = $seoTemplate;
		$this->seoTitles = is_string( $seoTitles ) ? array( $seoTitles ) : $seoTitles;
		$this->friendlyUrlComponent = $friendlyUrlComponent;
		
		/* Set hidden query string */
		parse_str( $furlDefinition[ $seoTemplate ]['real'], $hiddenQueryString );
		$this->hiddenQueryString = $hiddenQueryString + $matchedParams;
		
		/* Return */
		return $this;
	}
	
	/**
	 * Validate that the SEO title is correct
	 *
	 * @return	mixed	The correct URL if incorrect, TRUE is correct, or NULL if unknown
	 */
	public function correctFriendlyUrl()
	{
		/* Get the definition */
		$furlDefinition = static::furlDefinition();
		
		/* If we don't have a validate callback, we can return NULL */
		if ( !isset( $furlDefinition[ $this->seoTemplate ]['verify'] ) or !$furlDefinition[ $this->seoTemplate ]['verify'] )
		{
			return NULL;
		}
		
		/* Load it */
		try
		{
			if ( $correctUrl = $this->correctUrlFromVerifyClass( $furlDefinition[ $this->seoTemplate ]['verify'] ) )
			{
				/* IP.Board 3.x used /page-x in the path rather than a query string argument - we support this so as not to break past links */
				if( mb_strpos( (string) $this, '/page-' ) )
				{
					preg_match( "/\/page\-(\d+)/", (string) $this, $matches );

					if( isset( $matches[1] ) )
					{
						$correctUrl = $correctUrl->setQueryString( 'page', $matches[1] );
					}
				}

				/* IP.Board 3.x also used to do /page__x__y__a__b for /?x=y&a=b ... let's extract those parameters */
				if( mb_strpos( (string) $this, '/page__' ) )
				{
					preg_match( "/\/page__([^\/$]+?)(?:$|\/)/", (string) $this, $matches );

					if( isset( $matches[1] ) )
					{
						$params	= explode( '__', $matches[1] );
						$key	= NULL;

						foreach( $params as $param )
						{
							if( $key === NULL )
							{
								$key = $param;
							}
							else
							{
								$correctUrl = $correctUrl->setQueryString( $key, $param );
								$key = NULL;
							}
						}
					}
				}
				
				/* And it also used to do index.php?/topic/123-example/?p=456 - with the double ? */
				foreach ( $correctUrl->queryString as $k => $v )
				{
					if ( preg_match( '/^\/' . preg_quote( $correctUrl->friendlyUrlComponent, '/' ) . '\/\?(.+)$/', $k, $matches ) )
					{
						$correctUrl = $correctUrl->setQueryString( array(
							$matches[0]	=> NULL,
							$matches[1]	=> $v
						) );
					}
				}
				
				if ( (string) $correctUrl->normaliseForEqualityCheck() !== (string) $this->normaliseForEqualityCheck() )
				{
					return $correctUrl;
				}
				else
				{
					return TRUE;
				}
			}
			return NULL;
		}
		/* It doesn't exist */
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Normalise the URL for comparing if the "correct" URL is different to the accessed URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function normaliseForEqualityCheck()
	{
		/* Strip the query string except the FURL component */
		$return = $this->stripQueryString();
		
		/* Make it schema-relative */
		$return = $return->setScheme( NULL );
		
		/* Return */
		return $return;
	}
	
	/**
	 * Strip Query String
	 * Overrides main method so that the FURL slug isn't lost
	 *
	 * @param	string|array	$keys	The key(s) to strip - if omitted, entire query string is wiped
	 * @return	\IPS\Http\Url
	 */
	public function stripQueryString( $keys=NULL )
	{		
		if ( $keys === NULL and !\IPS\Settings::i()->htaccess_mod_rewrite )
		{
			$keys = $this->queryString;
			unset( $keys[ "/{$this->friendlyUrlComponent}/" ] );
			$keys = array_keys( $keys );
		}
		
		return parent::stripQueryString( $keys );
	}
			
	/* !Utilities */
	
	/**
	 * Get friendly URL component (e.g. "topic/1-test") from a query string and SEO template
	 *
	 * @param	string			$queryString	The query string - is passed by reference and any parts used are removed, which can be used to detect extraneous parts
	 * @param	string			$seoTemplate	The key for making this a friendly URL
	 * @param	string|array	$seoTitles		The title(s) needed for the friendly URL
	 * @return	string
	 * @throws	\IPS\Http\Url\Exception
	 */
	public static function buildFriendlyUrlComponentFromData( &$queryString, $seoTemplate, $seoTitles )
	{
		/* Get the definition */
		$furlDefinition = static::furlDefinition();
		if ( !isset( $furlDefinition[ $seoTemplate ] ) )
		{			
			throw new \IPS\Http\Url\Exception( 'INVALID_SEO_TEMPLATE' );
		}
		$component = $furlDefinition[ $seoTemplate ]['friendly'];
		
		/* Parse the query string into an array */
		parse_str( $queryString, $queryString );
				
		/* For each query string component, replace it out */
		foreach ( $queryString as $k => $v )
		{
			if ( mb_strpos( $component, "{#{$k}}" ) !== FALSE or mb_strpos( $component, "{@{$k}}" ) !== FALSE )
			{
				$component = str_replace( "{#{$k}}", intval( $v ), $component );
				$component = str_replace( "{@{$k}}", $v, $component );
				unset( $queryString[ $k ] );
			}
		}
		
		/* Parse out the titles */
		$seoTitles = is_null( $seoTitles ) ? array() : ( is_string( $seoTitles ) ? array( $seoTitles ) : array_values( $seoTitles ) );
		$seoTitlesCount = count( $seoTitles );
		for ( $i = 0; $i < $seoTitlesCount; $i++ )
		{
			if ( $i === 0 )
			{
				$component = str_replace( '{?}', $seoTitles[ $i ], $component );
			}
			$component = str_replace( "{?{$i}}", $seoTitles[ $i ], $component );
		}
		
		/* Remove the "real" parts in the query string */
		parse_str( $furlDefinition[ $seoTemplate ]['real'], $realQueryString );
		foreach ( $realQueryString as $k => $v )
		{
			if ( isset( $queryString[ $k ] ) and $queryString[ $k ] == $v )
			{
				unset( $queryString[ $k ] );
			}
		}
				
		/* Return */
		return $component;
	}
	
	/**
	 * Convert a value into an "SEO Title" for friendly URLs
	 *
	 * @param	string	$value	Value
	 * @return	string
	 * @note	Many places require an SEO title, so we always need to return something, so when no valid title is available we return a dash
	 */
	public static function seoTitle( $value )
	{
		/* Ensure there are no HTML tags */
		$value = strip_tags( $value );
		
		/* Always lowercase */
		$value = mb_strtolower( $value );

		/* Get rid of newlines/carriage returns as they're not cool in friendly URL titles */
		$value = str_replace( array( "\r\n", "\r", "\n" ), ' ', $value );

		/* Just for readability */
		$value = str_replace( ' ', '-', $value );
		
		/* Disallowed characters which browsers may try to automatically percent-encode */
		$value = str_replace( array( '!', '*', '\'', '(', ')', ';', ':', '@', '&', '=', '+', '$', ',', '/', '?', '#', '[', ']', '%', '\\', '"', '<', '>', '^', '{', '}', '|', '.', '`' ), '', $value );
		
		/* Trim */
		$value = preg_replace( '/\-+/', '-', $value );
		$value = trim( $value, '-' );
		$value = trim( $value );
		
		/* Return */
		return $value ?: '-';
	}
	
	/**
	 * Get the matched parameters and SEO titles from a friendly URL component for a particular 
	 *
	 * @param	array		$furlDefinition			The FURL definition from the furl.json file
	 * @param	string		$friendlyUrlComponent	The friendly URL component, which may be for the path or the query string (e.g. "topic/1-test")
	 * @return	array|NULL	array( $matchedParams, $seoTitles ) if matched, NULL if the $friendlyUrlComponent does not match this $furlDefinition
	 */
	protected static function getMatchedParamsFromFriendlyUrlComponent( $furlDefinition, $friendlyUrlComponent )
	{		
		/* See if it matches */
		$seoTitles = array();
		$matchedParams = array();
		foreach ( $furlDefinition['regex'] as $_regex )
		{
			if ( preg_match( '/^' . $_regex . '$/i', $friendlyUrlComponent, $matches ) )
			{
				foreach ( $furlDefinition['params'] as $k => $param )
				{
					if ( $param )
					{
						$matchedParams[ $param ] = $matches[ $k + 1 ];
					}
					else
					{
						$seoTitles[] = $matches[ $k + 1 ];
					}
				}
							
				return array( $matchedParams, $seoTitles );
			}
		}
		
		/* Still here? No match */
		return NULL;
	}
	
	/**
	 * @brief	FURL Definition
	 */
	protected static $furlDefinition = NULL;
	
	/**
	 * Get FURL Definition
	 *
	 * @param	bool	$revert	If TRUE, ignores all customisations and reloads from json
	 * @return	array
	 */
	public static function furlDefinition( $revert=FALSE )
	{
		if ( static::$furlDefinition === NULL or $revert )
		{
			$furlCustomizations	= ( \IPS\Settings::i()->furl_configuration AND !$revert ) ? json_decode( \IPS\Settings::i()->furl_configuration, TRUE ) : array();
			$furlConfiguration = ( isset( \IPS\Data\Store::i()->furl_configuration ) AND \IPS\Data\Store::i()->furl_configuration ) ? json_decode( \IPS\Data\Store::i()->furl_configuration, TRUE ) : array();

			if ( ( \IPS\IN_DEV and !\IPS\DEV_USE_FURL_CACHE ) or !count( $furlConfiguration ) or $revert )
			{
				$furlConfiguration = static::buildFurlConfiguation();
			}
			
			static::$furlDefinition = array_merge( $furlConfiguration, $furlCustomizations );
		}
		
		return static::$furlDefinition;
	}
	
	/**
	 * Rebuild and return \IPS\Data\Store::i()->furl_configuration with default, uncustomised values
	 *
	 * @return	array
	 */
	protected static function buildFurlConfiguation()
	{
		/* Init */
		$furlConfiguration = array();
		
		/* Load apps, prioritising the default (otherwise if two apps both have "/category/..." the app which is higher in the list may steal from the default app) */
		$applications = \IPS\Application::applications();
		foreach ( $applications as $k => $app )
		{
			if ( $app->default )
			{
				unset( $applications[ $k ] );
				array_unshift( $applications, $app );
				break;
			}
		}
		
		/* Loop each app... */
		foreach ( $applications as $app )
		{
			/* If it has a furl.json file... */
			if( file_exists( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json" ) )
			{
				/* Open it up */
				$data = json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json" ) ), TRUE );
				$topLevel = $data['topLevel'];
				
				/* Process them */
				$definitions = array();
				foreach ( $data['pages'] as $k => $page )
				{
					$definitions[ $k ] = static::buildFurlDefinition( $page['friendly'], $page['real'], $topLevel, $app->default, isset( $page['alias'] ) ? $page['alias'] : NULL, FALSE, isset( $page['verify'] ) ? $page['verify'] : NULL, isset( $page['seoTitles'] ) ? $page['seoTitles'] : NULL );
				}
												
				/* Add it in */
				$furlConfiguration = array_merge( $furlConfiguration, $definitions );
			}
		}
				
		/* Store */
		\IPS\Data\Store::i()->furl_configuration = json_encode( $furlConfiguration );
		
		/* Return */
		return $furlConfiguration;
	}

	/**
	 * Build the friendly URL definition
	 *
	 * @param	string		$friendly		Friendly URL pattern
	 * @param	string		$real			Non-friendly URL pattern
	 * @param	NULL|string	$appTopLevel	FURL slug if the app is not the default app
	 * @param	bool		$appIsDefault	Flag to indicate if the app is default or not
	 * @param	NULL|string	$alias			Friendly URL alias
	 * @param	bool		$custom			Flag to indicate if this is a custom FURL definition
	 * @param	string		$verify			The name of a class that contains a loadFromUrl() and an url() method for verifying the friendly URL is correct
	 * @param	array		$seoTitles		The class, query param and property to load from to rebuild seo titles
	 * @return	array
	 */
	public static function buildFurlDefinition( $friendly, $real, $appTopLevel = NULL, $appIsDefault = FALSE, $alias = NULL, $custom = FALSE, $verify = NULL, $seoTitles = NULL )
	{
		/* Init */
		$return = array(
			'friendly'	=> $friendly,
			'real'		=> $real
		);
		if ( $verify )
		{
			$return['verify'] = $verify;
		}
		if ( $custom )
		{
			$return['custom'] = TRUE;
		}
		if ( $seoTitles )
		{
			$return['seoTitles'] = $seoTitles;
		}
		
		/* If it has a top-level (e.g. "/forums") we need to store the definition either with that if it's the default app, or
			without it if it isn't, so that if the default app changes we can redirect accordingly */
		if ( $appTopLevel )
		{
			if ( $appIsDefault )
			{
				$return['with_top_level'] = $appTopLevel . ( $friendly ? '/' . $friendly : '' );
			}
			else
			{
				$return['without_top_level'] = $friendly;
				$return['friendly'] = $appTopLevel . ( $friendly ? '/' . $friendly : '' );
			}
		}
		
		/* Figure out the regexes */
		$return['regex'] = array( preg_quote( $return['friendly'], '/' ) );
		if ( $alias )
		{
			$return['regex'][] = str_replace( '\{\!\}', '(?!&)(?:.+?)', preg_quote( $alias, '/' ) );
		}
		if ( isset( $return['without_top_level'] ) and $return['without_top_level'] )
		{
			$return['regex'][] = preg_quote( $return['without_top_level'], '/' );
		}
		elseif ( isset( $return['with_top_level'] ) )
		{
			$return['regex'][] = preg_quote( $return['with_top_level'], '/' );
		}
		
		/* Parse out variables */
		$return['params'] = array();
		preg_match_all( '/{(.+?)}/', $return['friendly'], $matches );
		foreach ( $matches[1] as $tag )
		{
			switch ( mb_substr( $tag, 0, 1 ) )
			{
				case '#':
					$return['regex'] = array_map( function( $_regex ) use ( $tag ) {
						return str_replace( '\{#' . mb_substr( $tag, 1 ) . '\}', '(\d+?)', $_regex );
					}, $return['regex'] );
					$return['params'][] = mb_substr( $tag, 1 );
					break;
						
				case '@':
					$return['regex'] = array_map( function( $_regex ) use ( $tag ) {
						return str_replace( '\{@' . mb_substr( $tag, 1 ) . '\}', '(.+?)', $_regex );
					}, $return['regex'] );
					$return['params'][] = mb_substr( $tag, 1 );
					break;
		
				case '?':
					$return['regex'] = array_map( function( $_regex ) use ( $tag ) {
						return str_replace( '\{\?\}', '(?!&)(.+?)', $_regex );
					}, $return['regex'] );
					$return['params'][]	= '';
					break;
			}
		}
		
		/* Return */
		return $return;
	}
	
	/* !Deprecated */
	
	/** 
	 * @brief		Is internal?
	 * @deprecated	Using this property is deprecated. Check if instance of \IPS\Http\Url\Internal
	 */
	public $isInternal = TRUE;
	
	/** 
	 * @brief	Is friendly?
	 * @deprecated	Using this property is deprecated. Check if instance of \IPS\Http\Url\Friendly
	 */
	public $isFriendly = TRUE;
}