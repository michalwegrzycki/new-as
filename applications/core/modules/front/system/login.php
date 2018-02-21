<?php
/**
 * @brief		Login
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		7 Jun 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Login
 */
class _login extends \IPS\Dispatcher\Controller
{
	/**
	 * Log In
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Did we just log in? */
		if ( \IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->_fromLogin ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('') );
		}
		
		/* Force HTTPs? */
		if ( mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and \IPS\Settings::i()->logins_over_https and \IPS\Request::i()->url()->data['scheme'] !== 'https' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login', NULL, \IPS\Settings::i()->logins_over_https ) );
		}
		
		/* Init login class */
		$login = new \IPS\Login( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login', NULL, \IPS\Settings::i()->logins_over_https ) );

		/* Process */
		$error = isset( \IPS\Request::i()->_err ) && \IPS\Member::loggedIn()->language()->checkKeyExists( \IPS\Request::i()->_err ) ? \IPS\Request::i()->_err : NULL;
		try
		{
			$member = $login->authenticate();
			if ( $member !== NULL )
			{
				$this->_doLogin( $member, $login->flags['signin_anonymous'], $login->flags['remember_me'], \IPS\Login::getDestination(), FALSE, $login->usedHandler );
			}
		}
		catch ( \IPS\Login\Exception $e )
		{
			if ( $e->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
			{
				$e->member = $e->member->member_id;
				$_SESSION['linkAccounts'] = json_encode( $e );
				
				$linkUrl = \IPS\Http\Url::internal( 'app=core&module=system&controller=login&do=link', 'front', 'login' );
				if ( isset( \IPS\Request::i()->ref ) )
				{
					$linkUrl = $linkUrl->setQueryString( 'ref', \IPS\Request::i()->ref );
				}				
				\IPS\Output::i()->redirect( $linkUrl );
			}
			
			$error = $e->getMessage();
		}

		if ( \IPS\Request::i()->isAjax() && $error )
		{
			\IPS\Output::i()->json( array( 'status' => 'error', 'error' => $error ), 401 );			
		}
		else
		{
			/* Display Login Form */
			\IPS\Output::i()->allowDefaultWidgets = FALSE;
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('login');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->login( $login->forms(), $error );
		}
		
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', NULL, 'login' ), array(), 'loc_logging_in' );
	}
	
	/**
	 * Process the login
	 *
	 * @param	\IPS\Member		$member			The member
	 * @param	bool			$anonymous		If the login is anonymous
	 * @param	bool			$rememberMe		If the "remember me" checkbox was checked
	 * @param	\IPS\Http\Url	$destination	Where to redirect to
	 * @param	bool			$bypass2FA		If true, will not perform 2FA check
	 * @param	string			$loginHandler	Which login handler processed the login
	 * @return	void
	 */
	protected function _doLogin( $member, $anonymous=FALSE, $rememberMe=TRUE, $destination=NULL, $bypass2FA=FALSE, $loginHandler=NULL )
	{
		/* Get destination */
		if ( !$destination )
		{
			$destination = \IPS\Http\Url::internal( '' );
		}
		
		/* Is this a known device? */
		$device = \IPS\Member\Device::loadOrCreate( $member );
				
		/* Do we need to do 2FA? */
		if ( !$bypass2FA and $output = \IPS\MFA\MFAHandler::accessToArea( 'core', $device->known ? 'AuthenticateFrontKnown' : 'AuthenticateFront', \IPS\Http\Url::internal( '' ), $member ) )
		{
			$_SESSION['processing2FA'] = array( 'memberId' => $member->member_id, 'anonymous' => $anonymous, 'remember' => $rememberMe, 'destination' => (string) $destination, 'handler' => $loginHandler );
			\IPS\Output::i()->redirect( $destination->setQueryString( '_mfaLogin', 1 ) );
		}
				
		/* Log in */
		\IPS\Session::i()->setMember( $member );
		if ( $anonymous and !\IPS\Settings::i()->disable_anonymous )
		{
			\IPS\Session::i()->setAnon();
		}
		
		/* Log device */
		$device->anonymous = $anonymous and !\IPS\Settings::i()->disable_anonymous;
		$device->updateAfterAuthentication( $rememberMe, $loginHandler );

		/* Member sync */
		$member->memberSync( 'onLogin', array( \IPS\Login::getDestination() ) );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $destination->setQueryString( '_fromLogin', 1 ), '', 303 );
	}
	
	/**
	 * MFA
	 *
	 * @return	void
	 */
	protected function mfa()
	{				
		/* Have we logged in? */
		$member = NULL;
		if ( isset( $_SESSION['processing2FA']  ) )
		{
			$member = \IPS\Member::load( $_SESSION['processing2FA']['memberId'] );
		}
		if ( !$member->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
		}
		
		/* Where do we want to go? */
		$destination = NULL;
		try
		{
			$destination = \IPS\Http\Url::createFromString( $_SESSION['processing2FA']['destination'] );
		}
		catch ( \Exception $e ) { }	
		
		/* Have we already done 2FA? */
		$device = \IPS\Member\Device::loadOrCreate( $member );
		$output = \IPS\MFA\MFAHandler::accessToArea( 'core', $device->known ? 'AuthenticateFrontKnown' : 'AuthenticateFront', \IPS\Http\Url::internal( 'app=core&module=system&controller=login&do=mfa', 'front', 'login' ), $member );		
		if ( !$output )
		{						
			$this->_doLogin( $member, $_SESSION['processing2FA']['anonymous'], $_SESSION['processing2FA']['remember'], $destination, TRUE, $_SESSION['processing2FA']['handler'] );
		}
		
		/* Nope, just send us where we want to go not logged in */
		$qs = array( '_mfaLogin' => 1 );
		if ( isset( \IPS\Request::i()->_mfa ) )
		{
			$qs['_mfa'] = \IPS\Request::i()->_mfa;
			if ( isset( \IPS\Request::i()->_mfaMethod ) )
			{
				$qs['_mfaMethod'] = \IPS\Request::i()->_mfaMethod;
			}
		}
		elseif ( isset( \IPS\Request::i()->mfa_auth ) )
		{
			$qs['mfa_auth'] = \IPS\Request::i()->mfa_auth;
		}
		elseif ( isset( \IPS\Request::i()->mfa_setup ) )
		{
			$qs['mfa_setup'] = \IPS\Request::i()->mfa_setup;
		}
		\IPS\Output::i()->redirect( $destination->setQueryString( $qs ) );
	}
	
	/**
	 * Link Accounts
	 *
	 * @return	void
	 */
	protected function link()
	{
		if ( !isset( $_SESSION['linkAccounts'] ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
		}
		$details = json_decode( $_SESSION['linkAccounts'], TRUE );
				
		$member = \IPS\Member::load( $details['member'] );
		if ( !$member->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' ) );
		}
		
		$form = new \IPS\Helpers\Form( 'link_accounts', 'login' );
		if ( isset( \IPS\Request::i()->ref ) )
		{
			$form->hiddenValues['ref'] = \IPS\Request::i()->ref;
		}
		$form->addDummy( 'email_address', htmlspecialchars( isset( $details['email'] ) ? $details['email'] : $member->email, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE, array( 'validateFor' => $member ) ) );
		if ( $values = $form->values() )
		{
			try
			{
				$class = 'IPS\Login\\' . ucfirst( $details['handler'] );
				$class::link( $member, $details['details'] );
				
				unset( $_SESSION['linkAccounts'] );			
				
				$destination = NULL;
				if ( isset( \IPS\Request::i()->ref ) )
				{
					try
					{
						$ref = \IPS\Http\Url::createFromString( base64_decode( \IPS\Request::i()->ref ) );
						if ( $ref instanceof \IPS\Http\Url\Internal )
						{
							$destination = $ref;
						}
					}
					catch ( \Exception $e ) { }
				}
				$this->_doLogin( $member, FALSE, TRUE, $destination, FALSE, $details['handler'] );
			}
			catch ( \IPS\Login\Exception $e )
			{
				$form->error = $e->getMessage();
			}			
		}
		
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('login');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->mergeSocialAccount( $details['handler'], $member, $form );
	}
	
	/**
	 * Log Out
	 *
	 * @return void
	 */
	protected function logout()
	{
		$member = \IPS\Member::loggedIn();
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Work out where we will be going after log out */
		if( !empty( $_SERVER['HTTP_REFERER'] ) )
		{
			$referrer = \IPS\Http\Url::createFromString( $_SERVER['HTTP_REFERER'] );
			$redirectUrl = ( $referrer instanceof \IPS\Http\Url\Internal and ( !isset( $referrer->queryString['do'] ) or $referrer->queryString['do'] != 'validating' ) ) ? $referrer : \IPS\Http\Url::internal('');
		}
		else
		{
			$redirectUrl = \IPS\Http\Url::internal( '' );
		}
		
		/* Are we logging out back to an admin user? */
		if( isset( $_SESSION['logged_in_as_key'] ) )
		{
			$key = $_SESSION['logged_in_as_key'];
			unset( \IPS\Data\Store::i()->$key );
			unset( $_SESSION['logged_in_as_key'] );
			unset( $_SESSION['logged_in_from'] );
			
			\IPS\Output::i()->redirect( $redirectUrl );
		}
		
		/* Do not allow the login_key to be re-used */
		if ( isset( \IPS\Request::i()->cookie['device_key'] ) )
		{
			try
			{
				$device = \IPS\Member\Device::loadAndAuthenticate( \IPS\Request::i()->cookie['device_key'], $member );
				$device->login_key = NULL;
				$device->save();
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* Clear cookies */
		\IPS\Request::i()->clearLoginCookies();

		/* Destroy the session (we have to explicitly reset the session cookie, see http://php.net/manual/en/function.session-destroy.php) */
		$_SESSION = array();
		$params = session_get_cookie_params();
		setcookie( session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"] );
		session_destroy();

		/* Login handler callback */
		foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
		{
			try
			{
				$handler->logoutAccount( $member, $redirectUrl );
			}
			catch( \BadMethodCallException $e ) {}
		}

		/* Member sync callback */
		$member->memberSync( 'onLogout', array( $redirectUrl ) );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $redirectUrl->setQueryString( '_fromLogout', 1 ) );
	}
	
	/**
	 * Log in as user
	 *
	 * @return void
	 */
	protected function loginas()
	{
		if ( !\IPS\Request::i()->key or \IPS\Data\Store::i()->admin_login_as_user != \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'invalid_login_as_user_key', '3S167/1', 403, '' );
		}
	
		/* Load member and admin user */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		$admin 	= \IPS\Member::load( \IPS\Request::i()->admin );
		
		/* Not logged in as admin? */
		if ( $admin->member_id != \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login', NULL, \IPS\Settings::i()->logins_over_https )->setQueryString( array( 'ref' => base64_encode( \IPS\Request::i()->url() ), '_err' => 'login_as_user_login' ) ) );
		}
		
		/* Do it */
		$_SESSION['logged_in_from']			= array( 'id' => $admin->member_id, 'name' => $admin->name );
		$unique_id							= \IPS\Login::generateRandomString();
		$_SESSION['logged_in_as_key']		= $unique_id;
		\IPS\Data\Store::i()->$unique_id	= $member->member_id;
		
		/* Ditch the key */
		unset( \IPS\Data\Store::i()->admin_login_as_user );
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ) );
	}
}