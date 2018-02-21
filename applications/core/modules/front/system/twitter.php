<?php
/**
 * @brief		Twitter Authentication
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 February 2017
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Twitter auth for ACP
 */
class _twitter extends \IPS\Dispatcher\Controller
{	
	/**
	 * View Announcement
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( ! \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login', NULL, \IPS\Settings::i()->logins_over_https )->setQueryString( 'ref', base64_encode( \IPS\Http\Url::internal( 'app=core&module=system&controller=twitter' ) ) ) );
		}

		$twitter = \IPS\Login::getHandler('Twitter');
		$promote = \IPS\core\Promote::getPromoter( 'Twitter' );
		$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=twitter' );
		
		try
		{
			/* Get a request token */
			if ( !isset( \IPS\Request::i()->oauth_token ) )
			{
				$callback = $url->setQueryString( 'csrf', \IPS\Session::i()->csrfKey );
				$response = $twitter->sendRequest( 'get', 'https://api.twitter.com/oauth/request_token', array( 'oauth_callback' => (string) $callback ) )->decodeQueryString('oauth_token');
				\IPS\Output::i()->redirect( "https://api.twitter.com/oauth/authorize?force_login=1&oauth_token={$response['oauth_token']}" );
			}
			
			/* CSRF Check */
			if ( \IPS\Request::i()->csrf !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Authenticate */
			$response = $twitter->sendRequest( 'post', 'https://api.twitter.com/oauth/access_token', array( 'oauth_verifier' => \IPS\Request::i()->oauth_verifier ), \IPS\Request::i()->oauth_token )->decodeQueryString('user_id');
			
			$user = $twitter->sendRequest( 'get', 'https://api.twitter.com/1.1/account/verify_credentials.json', array(), $response['oauth_token'], $response['oauth_token_secret'] )->decodeJson();
						
			/* Store the settings */
			$promote->saveSettings( array(
				'id' => $response['user_id'],
				'owner' => \IPS\Member::loggedIn()->member_id,
				'secret' => $response['oauth_token_secret'],
				'token' => $response['oauth_token'],
				'name' => $user['screen_name']
			) );
			
			/* Show a done page */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_twitter_sorted');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'promote' )->promoteTwitterComplete( $user );
				
		}
		catch ( \Exception $e )
   		{
	   		throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
   		}
	}
}