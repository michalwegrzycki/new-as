<?php
/**
 * @brief		Facebook Promotion
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Feb 2017
 */

namespace IPS\Content\Promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Facebook Promotion
 */
class _Facebook extends PromoteAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'facebook';
	
	/**
	 * @brief Default settings
	 */
	public $defaultSettings = array(
		'token' => NULL,
		'owner' => NULL,
		'page_name' => NULL,
		'permissions' => NULL,
		'members' => NULL,
		'page' => NULL,
		'tags' => NULL,
		'image' => NULL,
		'last_sync' => 0
	);
	
	/**
	 * Is connected?
	 *
	 * @return	bool
	 */
	public function connected()
	{
		return ( $this->member->fb_uid and $this->member->fb_token );
	}
	
	/**
	 * Get image
	 *
	 * @return string
	 */
	public function getPhoto()
	{
		if ( ! $this->settings['image'] or $this->settings['last_sync'] < time() - 86400 )
		{ 
			/* Fetch again */
			$response = \IPS\Http\Url::external( "https://graph.facebook.com/" . $this->settings['page'] . "/picture?type=large&appsecret_proof=" . \IPS\Login\Facebook::appSecretProof( $this->settings['token'] ) )->request()->get();
			 
			$extension = str_replace( 'image/', '', $response->httpHeaders['Content-Type'] );
			$newFile = \IPS\File::create( 'core_Promote', 'facebook_' . $this->settings['page'] . '.' . $extension, (string) $response, NULL, FALSE, NULL, FALSE );
			 
			$this->saveSettings( array( 'image' => (string) $newFile->url, 'last_sync' => time() ) );
		}
		 
		return $this->settings['image'];
	}
	
	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->settings['page_name'];
	}
	
	/**
	 * Check publish permissions
	 *
	 * @return	boolean
	 */
	public function canPostToPage()
	{
		try
		{
			$response = \IPS\Http\Url::external( "https://graph.facebook.com/{$this->member->fb_uid}/permissions" )->setQueryString( array( 'access_token' => $this->member->fb_token, 'appsecret_proof' => \IPS\Login\Facebook::appSecretProof( $this->member->fb_token ) ) )->request()->get()->decodeJson();
			
			if ( isset( $response['error'] ) )
			{
				return $response['error']['message'];
			}
			
			if ( !empty( $response['data'] ) )
			{			
				$got = 0;	
				foreach( $response['data'] as $permission )
				{
					if ( in_array( $permission['permission'], array( 'manage_pages', 'publish_pages' ) ) and $permission['status'] == 'granted' )
					{
						$got++;
					}
				}

				return $got === 2;
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			\IPS\Log::log( $e, 'facebook' );
		}
		
		return NULL;
	}
	
	/**
	 * Get pages this user manages
	 *
	 * @return	array
	 */
	public function getPages()
	{
		$pages = array();
		try
		{
			$response = \IPS\Http\Url::external( "https://graph.facebook.com/{$this->member->fb_uid}/accounts" )->setQueryString( array( 'access_token' => $this->member->fb_token, 'appsecret_proof' => \IPS\Login\Facebook::appSecretProof( $this->member->fb_token ) ) )->request()->get()->decodeJson();

			if ( !empty( $response['data'] ) )
			{			
				foreach( $response['data'] as $page )
				{
					$pages[ $page['id'] ] = array( $page['name'], $page['access_token'] );
				}
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			\IPS\Log::log( $e, 'facebook' );
		}
		
		return $pages;
	}
	
	/**
	 * Get form elements for this share service
	 *
	 * @param	string		$text		Text for the text entry
	 * @param	string		$link		Short or full link (short when available)
	 * @param	string		$content	Additional text content (usually a comment, or the item content)
	 *
	 * @return array of form elements
	 */
	public function form( $text, $link=null, $content=null )
	{
		
		
		if ( $this->promote and $this->promote->id )
		{
			$_text = $text;
		}
		else
		{
			$_text = $text;
			
				if ( $content )
			{
				$_text .= "\n" . $content;
			}
			
			if ( $link )
			{
				$_text .= "\n" . $link;
			}
			
			if ( count( $this->settings['tags'] ) )
			{
				$_text .= "\n" . '#' . implode( ' #', $this->settings['tags'] );
			} 
		}
		
		return array( new \IPS\Helpers\Form\TextArea( 'promote_social_content_facebook', $_text, FALSE, array( 'maxLength' => 2000, 'rows' => 10 ) ) );
	}
	 
	/**
	 * Post to Facebook
	 *
	 * @param	\IPS\core\Promote	$promote 	Promote Object
	 * @return void
	 */
	public function post( $promote )
	{
		$photos = $promote->imageObjects();

		/* Get the last 20 items to see if we have a duplicate */
		$items = \IPS\Http\Url::external( "https://graph.facebook.com/" . $this->settings['page'] . "/feed" )->setQueryString( array( 'limit' => 20, 'access_token' => $this->settings['token'], 'appsecret_proof' => \IPS\Login\Facebook::appSecretProof( $this->settings['token'] ) ) )->request()->get()->decodeJson();

		if ( isset( $items['data'] ) )
		{
			foreach( $items['data'] as $item )
			{
				$time = new \IPS\DateTime( $item['created_time'] );
				$now = new \IPS\DateTime();
				
				/* Only look back past the last 30 mins */
				if ( intval( $now->diff( $time )->format('%i') ) > 30 )
				{
					continue;
				}
				
				if ( preg_replace( '#\s{1,}#', " ", $promote->text['facebook'] ) == preg_replace( '#\s{1,}#', " ", $item['message'] ) )
				{
					/* Duplicate */
					return $item['id'];
				}
			}
		}
		
		if ( ! $photos or count( $photos ) === 1 )
		{
			/* Simple message */
			try
			{
				if ( $photos )
				{
					$thePhoto = array_pop( $photos );
					$this->response = \IPS\Http\Url::external( "https://graph.facebook.com/" . $this->settings['page'] . "/photos" )->setQueryString( 'appsecret_proof', \IPS\Login\Facebook::appSecretProof( $this->settings['token'] ) )->request()->post( array(
						'message' => $promote->text['facebook'],
						'url' => (string) $this->returnUrlWithProtocol( $thePhoto->url ),
						'access_token' => $this->settings['token'],
						'link' => $promote->short_link
					) )->decodeJson();
				}
				else
				{
					$this->response = \IPS\Http\Url::external( "https://graph.facebook.com/" . $this->settings['page'] . "/feed" )->setQueryString( 'appsecret_proof', \IPS\Login\Facebook::appSecretProof( $this->settings['token'] ) )->request()->post( array(
						'message' => $promote->text['facebook'],
						'access_token' =>  $this->settings['token'],
						'link' => $promote->short_link
					) )->decodeJson();
				}
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'facebook' );
				
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('facebook_publish_exception') );
			}
			
			if ( isset( $this->response['id'] ) )
			{
				return $this->response['id'];
			}
			else
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('facebook_publish_exception') );
			}
		}
		else
		{
			/* We have multiple photos so we first need to create an album, and then upload into that */
			try
			{
				/* The item may be a comment, which uses a language string for objectTitle - make sure that is parsed before sending */
				$title = $promote->objectTitle;
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $title );


				$this->response = \IPS\Http\Url::external( "https://graph.facebook.com/" . $this->settings['page'] . "/albums" )->setQueryString( 'appsecret_proof', \IPS\Login\Facebook::appSecretProof( $this->settings['token'] ) )->request()->post( array(
					'name' => $title,
					'access_token' => $this->settings['token'],
				) )->decodeJson();
				
				if ( ! isset( $this->response['id'] ) )
				{
					throw new \InvalidArgumentException('Could not create album');
				}
				
				$newAlbumId = $this->response['id'];
				
				foreach( $photos as $photo )
				{
					$this->response = \IPS\Http\Url::external( "https://graph.facebook.com/" . $newAlbumId . "/photos" )->setQueryString( 'appsecret_proof', \IPS\Login\Facebook::appSecretProof( $this->settings['token'] ) )->request()->post( array(
						'message' => $promote->text['facebook'],
						'url' => (string) $this->returnUrlWithProtocol( $photo->url ),
						'access_token' => $this->settings['token'],
					) )->decodeJson();
				}
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'facebook' );
				
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->get('facebook_publish_exception') );
			}
			
			return $this->response['id'];
		}
	}
	
	/**
	 * Return the published URL
	 *
	 * @param	array	$data	Data returned from a successful POST
	 * @return	\IPS\Http\Url
	 * @throws InvalidArgumentException
	 */
	public function getUrl( $data )
	{
		if ( $data and preg_match( '#^[0-9_]*$#', $data ) )
		{
			return \IPS\Http\Url::external( 'https://facebook.com/' . $data );
		}
		
		throw new \InvalidArgumentException();
	}
	
	/**
	 * Ensure the URL is not protocol relative
	 *
	 * @return \IPS\Http\Url
	 */
	protected function returnUrlWithProtocol( \IPS\Http\Url $url )
	{
		if ( ! $url->data['scheme'] )
		{
			$url = $url->setScheme( ( \substr( \IPS\Settings::i()->base_url, 0, 5 ) == 'https' ) ? 'https' : 'http' );
		}
		
		return $url;
	}
}