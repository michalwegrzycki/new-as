<?php
/**
 * @brief		Facebook Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Mar 2013
 */

namespace IPS\Login;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Facebook Login Handler
 */
class _Facebook extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'facebook-square';
	
	/** 
	 * @brief	Logo
	 */
	public static $logo = 'Facebook';
	
	/** 
	 * @brief	Share Service
	 */
	public static $shareService = 'facebook';
	
	/**
	 * Get Form
	 *
	 * @param	\IPS\Http\Url	$url			The URL for the login page
	 * @param	bool			$ucp			Is UCP? (as opposed to login form)
	 * @param	\IPS\Http\Url	$destination	The URL to redirect to after a successful login
	 * @return	string
	 */
	public function loginForm( \IPS\Http\Url $url, $ucp = FALSE, \IPS\Http\Url $destination = NULL )
	{
		$scope = 'email';
		$facebookUrl = "https://www.facebook.com/dialog/oauth";
		
		if ( \IPS\Settings::i()->profile_comments )
		{
			if ( isset( $this->settings['allow_status_import'] ) and $this->settings['allow_status_import'] )
			{
				$scope .= ',user_posts';
			}
		}
			
		if ( isset( $this->settings['autoshare'] ) and $this->settings['autoshare'] )
		{			
			$scope .= ',publish_actions';
		}
		
		if ( isset( \IPS\Request::i()->permissionRequest_Facebook ) )
		{
			$allowedExtra = array( 'pages_show_list', 'manage_pages', 'publish_pages' );
			
			/* We need at least version 2.5 for this to work, 2.8 is latest */
			$facebookUrl = "https://www.facebook.com/v2.8/dialog/oauth";
			
			foreach( explode( ',', \IPS\Request::i()->permissionRequest_Facebook ) as $new )
			{
				if ( in_array( trim( $new ), $allowedExtra ) )
				{
					$scope .= "," . $new;
				}
			}
		}
		
		$url = \IPS\Http\Url::external( $facebookUrl )->setQueryString( array(
			'client_id'		=> $this->settings['app_id'],
			'scope'			=> $scope,
			'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/facebook/auth.php', 'none', NULL, array(), \IPS\Settings::i()->logins_over_https ? \IPS\Http\Url::PROTOCOL_HTTPS : 0 ),
			'state'			=> ( $ucp ? 'ucp' : \IPS\Dispatcher::i()->controllerLocation ) . '-' . \IPS\Session::i()->csrfKey . '-' . ( $destination ? base64_encode( $destination ) : '' )
		) );		
				
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->facebook( (string) $url );
	}
	
	/**
	 * Authenticate
	 *
	 * @param	string			$url	The URL for the login page
	 * @param	\IPS\Member		$member	If we want to integrate this login method with an existing member, provide the member object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( $url, $member=NULL )
	{
		$url = $url->setQueryString( 'loginProcess', 'facebook' );
		
		try
		{
			/* CSRF Check */
			if ( \IPS\Request::i()->state !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Check user approved */
			if( !isset( \IPS\Request::i()->code ) OR !\IPS\Request::i()->code )
			{
				throw new \IPS\Login\Exception( 'denied_oauth', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Get a token */
			try
			{
				$response = \IPS\Http\Url::external( "https://graph.facebook.com/v2.8/oauth/access_token" )->request()->post( array(
					'client_id'		=> $this->settings['app_id'],
					'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/facebook/auth.php', 'none', NULL, array(), \IPS\Settings::i()->logins_over_https ? \IPS\Http\Url::PROTOCOL_HTTPS : 0 ),
					'client_secret'	=> $this->settings['app_secret'],
					'code'			=> \IPS\Request::i()->code
				) )->decodeJson();
			}
			catch( \RuntimeException $e )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Now exchange it for a one that will last a bit longer in case the user wants to use syncing */
			$response['access_token'] = $this->exchangeToken( $response['access_token'] );
			
			if ( ! $response['access_token'] )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}

			/* Get the user data */
			$userData = \IPS\Http\Url::external( "https://graph.facebook.com/me?fields=email,id,name&access_token={$response['access_token']}&appsecret_proof=" . static::appSecretProof( $response['access_token'] ) )->request()->get()->decodeJson();

			/* Find or create member */
			$member = $this->createOrUpdateAccount( $member ?: \IPS\Member::load( $userData['id'], 'fb_uid' ), array(
				'fb_uid'	=> $userData['id'],
				'fb_token'	=> $response['access_token']
			), $this->settings['real_name'] ? $userData['name'] : NULL, ( isset( $userData['email'] ) AND $userData['email'] ) ? $userData['email'] : NULL, $response['access_token'], array( 'photo' => TRUE, 'cover' => TRUE, 'status' => '' ) );
			
			/* Return */
			return $member;
   		}
   		catch ( \IPS\Http\Request\Exception $e )
   		{
	   		throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
   		}
	}
	
	
	/**
	 * Exchange a short lived token for a longer lived token
	 *
	 * @param	string	$shortLivedToken	The short lived token to exchange for a long lived token
	 * @return string
	 */
	public function exchangeToken( $shortLivedToken )
	{
		try
		{
			$response = \IPS\Http\Url::external( "https://graph.facebook.com/v2.8/oauth/access_token" )->request()->post( array(
				'grant_type'		=> 'fb_exchange_token',
				'client_id'			=> $this->settings['app_id'],
				'client_secret'		=> $this->settings['app_secret'],
				'fb_exchange_token'	=> $shortLivedToken
			) )->decodeJson();
			
			return $response['access_token'];			
		}
		catch( \RuntimeException $e )
		{
			\IPS\Log::log( $e, 'facebook' );
		}
		
		return NULL;
	}
	
	/**
	 * Link Account
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	mixed		$details	Details as they were passed to the exception thrown in authenticate()
	 * @return	void
	 */
	public static function link( \IPS\Member $member, $details )
	{
		$userData = \IPS\Http\Url::external( "https://graph.facebook.com/me?access_token={$details}&appsecret_proof=" . static::appSecretProof( $details ) )->request()->get()->decodeJson();
		$member->fb_uid = $userData['id'];
		$member->fb_token = $details;
		$member->save();
	}
	
	/**
	 * ACP Settings Form
	 *
	 * @param	string	$url	URL to redirect user to after successful submission
	 * @return	array	List of settings to save - settings will be stored to core_login_handlers.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'help',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/login_facebook' ),
				'target'	=> '_blank',
				'class'		=> ''
			),
		);
		
		
		$return = array(
			'app_id'				=> new \IPS\Helpers\Form\Text( 'login_facebook_app', ( isset( $this->settings['app_id'] ) ) ? $this->settings['app_id'] : '', TRUE ),
			'app_secret'			=> new \IPS\Helpers\Form\Text( 'login_facebook_secret', ( isset( $this->settings['app_secret'] ) ) ? $this->settings['app_secret'] : '', TRUE ),
			'real_name'				=> new \IPS\Helpers\Form\YesNo( 'login_real_name', ( isset( $this->settings['real_name'] ) ) ? $this->settings['real_name'] : FALSE, TRUE )
		);
		
		if ( \IPS\Settings::i()->profile_comments )
		{
			$return['allow_status_import'] = new \IPS\Helpers\Form\YesNo( 'login_facebook_allow_status_import', ( isset( $this->settings['allow_status_import'] ) ) ? $this->settings['allow_status_import'] : FALSE, FALSE );
		}
		
		return $return;
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings()
	{
		return TRUE;
	}
	
	/**
	 * Can a member sign in with this login handler?
	 * Used to ensure when a user disassociates a social login that they have some other way of logging in
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function canProcess( \IPS\Member $member )
	{
		return ( $member->fb_uid and $member->fb_token );
	}
	
	/**
	 * Can a member change their email/password with this login handler?
	 *
	 * @param	string		$type	'email' or 'password'
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function canChange( $type, \IPS\Member $member )
	{
		return FALSE;
	}

	/**
	 * Generated an appsecret_proof for Graph API requests
	 * @link	https://developers.facebook.com/docs/graph-api/securing-requests
	 *
	 * @param	string			$accessToken	Members access token
	 * @return	string
	 */
	public static function appSecretProof( $accessToken )
	{
		$loginHandlers = \IPS\Login::handlers( TRUE );

		return hash_hmac( 'sha256', $accessToken, $loginHandlers['Facebook']->settings['app_secret'] );
	}
}