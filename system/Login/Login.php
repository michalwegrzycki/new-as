<?php
/**
 * @brief		Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Mar 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Login Handler
 */
class _Login
{
	const AUTH_TYPE_USERNAME	= 1;
	const AUTH_TYPE_EMAIL		= 2;
	
	/**
	 * @brief	Handlers
	 */
	public static $handlers = NULL;

	/**
	 * @brief	All handlers
	 */
	public static $allHandlers = NULL;
	
	/**
	 * Get Handlers
	 *
	 * @param	bool	$all	Force fetching of all enabled login handlers
	 * @return	array
	 */
	public static function handlers( $all=FALSE )
	{
		/* Do we need all handlers? */
		if( $all === TRUE )
		{
			if( static::$allHandlers !== NULL )
			{
				return static::$allHandlers;
			}
			
			foreach ( \IPS\Db::i()->select( '*', 'core_login_handlers', 'login_enabled=1', 'login_order' ) as $row )
			{
				try
				{
					static::$allHandlers[ $row['login_key'] ] = \IPS\Login\LoginAbstract::constructFromData( $row );
				}
				catch ( \RuntimeException $e ) { /* Skip over any which error (may happen if they haven't been updated for 4.x for example */ }
			}
			
			if ( \IPS\Dispatcher::hasInstance() === TRUE )
			{
				if( \IPS\Dispatcher::i()->controllerLocation == 'front' )
				{
					static::$handlers	= static::$allHandlers;
				}
			}

			return static::$allHandlers;
		}

		/* Fetch the appropriate handlers */
		if ( static::$handlers === NULL )
		{
			if ( \IPS\Dispatcher::i()->controllerLocation === 'front' )
			{
				if ( isset( \IPS\Data\Store::i()->loginHandlers ) )
				{
					$rows = \IPS\Data\Store::i()->loginHandlers;
				}
				else
				{
					$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'core_login_handlers', 'login_enabled=1', 'login_order' ) );
					\IPS\Data\Store::i()->loginHandlers = $rows;					
				}
			}
			else
			{
				$rows = \IPS\Db::i()->select( '*', 'core_login_handlers', 'login_enabled=1 AND login_acp=1', 'login_order' );
			}
	
			foreach ( $rows as $row )
			{
				try
				{
					static::$handlers[ $row['login_key'] ] = \IPS\Login\LoginAbstract::constructFromData( $row );
				}
				catch ( \RuntimeException $e ) { /* Skip over any which error (may happen if they haven't bee updated for 4.x for example */ }
			}

			if( \IPS\Dispatcher::i()->controllerLocation == 'front' )
			{
				static::$allHandlers	= static::$handlers;
			}
		}
		
		/* If we have no handlers, use the standard internal */
		if ( empty( static::$handlers ) )
		{
			static::$handlers['Internal'] = \IPS\Login\LoginAbstract::load('internal');
		}

		return static::$handlers;
	}
	
	/**
	 * Return a single handler object
	 *
	 * @throws UnderflowException
	 * @return \IPS\Login
	 */
	public static function getHandler( $handler )
	{
		return \IPS\Login\LoginAbstract::constructFromData( \IPS\Db::i()->select( '*', 'core_login_handlers', array( 'login_key=?', $handler ) )->first() );
	}

	/**
	 * @brief	URL
	 */
	protected $url = '';
	
	/**
	 * @brief	Handlers which use the 'Standard' log in form
	 */
	public $standardHandlers = array();
	
	/**
	 * @brief	Forms
	 */
	protected $forms = NULL;
	
	/**
	 * @brief	Show flag options (remember me, anonymous) on form?
	 */
	public $flagOptions = TRUE;
	
	/**
	 * @brief	Which handler was used?
	 */
	public $usedHandler = NULL;
		
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	The URL page for the login screen
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url )
	{
		$this->url = $url;
	}

	/**
	 * @brief Force a URL to send to post-login
	 * @note Useful when you need to redirect the user to another URL that is not local to this installation
	 */
	public static $forcedRedirectUrl = NULL;

	/**
	 * Fetch the URL to redirect to
	 *
	 * @return	\IPS\Http\Url
	 */
	public static function getDestination()
	{
		/* Try and get a referrer... */
		try
		{
			/* Are we forcing the user to be sent to a specific URL? */
			if( static::$forcedRedirectUrl !== NULL )
			{
				return static::$forcedRedirectUrl;
			}

			/* Get the URL we need to redirect to */
			if ( isset( \IPS\Request::i()->ref ) and $decoded = @base64_decode( \IPS\Request::i()->ref ) )
			{
				$ref = \IPS\Http\Url::createFromString( $decoded );
			}
			elseif ( isset( $_SERVER['HTTP_REFERER'] ) )
			{
				$ref = \IPS\Http\Url::createFromString( $_SERVER['HTTP_REFERER'] );
			}
			else
			{
				throw new \DomainException;
			}

			/* Make sure it's internal and to the front-end */
			if ( !( $ref instanceof \IPS\Http\Url\Internal ) or $ref->base !== 'front' )
			{
				throw new \DomainException;
			}
			
			/* Make sure it's not the redirector controller as we can use it to redirect to any site */
			if ( isset( $ref->queryString['module'] ) and isset( $ref->queryString['controller'] ) and $ref->queryString['module'] == 'system' and $ref->queryString['controller'] == 'redirect' )
			{
				throw new \DomainException;
			}

			/* Strip the csrf and return */
			return $ref->stripQueryString( 'csrfKey' );
		}
		/* And if anything goes wrong, just use the base URL */
		catch ( \Exception $e )
		{
			return \IPS\Http\Url::internal('');
		}
	}

	/**
	 * Fetch the URL to redirect to following registration, if any
	 *
	 * @param	\IPS\Member	$member		The member that just registered
	 * @return	\IPS\Http\Url
	 */
	public static function getRegistrationDestination( $member )
	{
		foreach ( static::handlers() as $key => $handler )
		{
			if ( method_exists( $handler, 'getRegistrationDestination' ) )
			{
				return $handler->getRegistrationDestination( $member );
			}
		}
		
		$ref = NULL;
		if ( isset( \IPS\Request::i()->ref ) )
		{
			try
			{
				$ref = \IPS\Http\Url::createFromString( base64_decode( \IPS\Request::i()->ref ) );
				if ( !( $ref instanceof \IPS\Http\Url\Internal ) )
				{
					$ref = NULL;
				}
			}
			catch ( \Exception $e ) { }
		}
		
		if ( in_array( \IPS\Settings::i()->reg_auth_type, array( 'admin', 'admin_user' ) ) )
		{
			return \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=validating', 'front', 'register' );
		}

		return $ref ?: \IPS\Http\Url::internal( '' );
	}

	/**
	 * Get Login Forms
	 *
	 * @param	bool	$acp			TRUE=ACP login form, FALSE=front end login form
	 * @param	bool	$skipReferer	If set to true we will skip adding the "referer" to the form, useful for the quick login popup where you want the same page to reload
	 * @return	array
	 */
	public function forms( $acp=FALSE, $skipReferer=FALSE )
	{
		if ( $this->forms === NULL )
		{
			$this->forms = array();
			$standardTypes = 0;
			
			foreach ( static::handlers() as $key => $handler )
			{
				if ( method_exists( $handler, 'loginForm' ) )
				{
					try
					{
						$this->forms[ $key ] = $handler->loginForm( $this->url, FALSE, $skipReferer ? $this->url : static::getDestination() );
					}
					catch( \BadMethodCallException $e )
					{
						/* The user may have installed a custom login handler, but not provided a template for the upgrader.
							This results in a NO_TEMPLATE_FILE exception and blocks the upgrader completely. We should just skip
							that login handler in this case */
						continue;
					}
					catch( \ErrorException $e )
					{
						/*	The login handler may belong to an application that has been disabled. In this instance, an ErrorException is thrown with template_store_missing.
							Look for these and log then skip. These are automatically logged in \IPS\Theme, which throws the exception. */
						continue;
					}
				}
				else
				{
					$this->forms['_standard'] = NULL;
					$standardTypes = $standardTypes | $handler->authTypes;
					$this->standardHandlers[] = $key;
				}
			}

			if ( $standardTypes !== 0 )
			{
				switch ( $standardTypes )
				{
					case static::AUTH_TYPE_USERNAME:
						\IPS\Member::loggedIn()->language()->words['auth'] = \IPS\Member::loggedIn()->language()->addToStack( 'username', FALSE );
						break;
						
					case static::AUTH_TYPE_EMAIL:
						\IPS\Member::loggedIn()->language()->words['auth'] = \IPS\Member::loggedIn()->language()->addToStack( 'email_address', FALSE );
						break;
					
					case static::AUTH_TYPE_USERNAME + static::AUTH_TYPE_EMAIL:
						\IPS\Member::loggedIn()->language()->words['auth'] = \IPS\Member::loggedIn()->language()->addToStack( 'username_or_email', FALSE );
						break;
				}

				$standardForm = new \IPS\Helpers\Form( "login__standard", 'login', $this->url );
				
				$classname = 'IPS\Helpers\Form\Text';
				if ( $standardTypes === static::AUTH_TYPE_EMAIL )
				{
					$classname = 'IPS\Helpers\Form\Email';
				}
				$standardForm->class = 'ipsForm_vertical';
				$standardForm->add( new $classname( 'auth', NULL, TRUE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->words['auth'], '_loginType' => $standardTypes, 'bypassProfanity' => TRUE ), NULL, NULL, NULL, 'auth' ) );
				$standardForm->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack( 'password', FALSE ), 'bypassProfanity' => TRUE ), NULL, NULL, NULL, 'password' ) );

				/* Are we adding the referer value to the form? */
				if( !$skipReferer )
				{
					$standardForm->hiddenValues['ref'] = base64_encode( static::getDestination() );
				}
				
				if ( $this->flagOptions )
				{
					$standardForm->add( new \IPS\Helpers\Form\Checkbox( 'remember_me', TRUE ) );
					if ( !\IPS\Settings::i()->disable_anonymous )
					{
						$standardForm->add( new \IPS\Helpers\Form\Checkbox( 'signin_anonymous' ) );
					}
					$standardForm->addButton( 'forgotten_password', 'link', \IPS\Http\Url::internal( 'app=core&module=system&controller=lostpass', 'front', 'lostpassword' ), 'ipsButton ipsButton_small ipsButton_fullWidth ipsButton_link' );
				}
				
				$this->forms['_standard'] = $standardForm;
			}
		}

		return $this->forms;
	}
	
	/**
	 * @brief	Login Flags
	 */
	public $flags = array( 'remember_me' => TRUE, 'signin_anonymous' => FALSE );
	
	/**
	 * Check for successful authentication
	 *
	 * @return	\IPS\Member|null
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate()
	{
		if ( ( !isset( \IPS\Request::i()->cookie[ 'IPSSession' . ucfirst( \IPS\Dispatcher::i()->controllerLocation ) ] ) or \IPS\Request::i()->cookie[ 'IPSSession' . ucfirst( \IPS\Dispatcher::i()->controllerLocation ) ] != session_id() ) )
		{
			if ( !isset( \IPS\Request::i()->cookieCheck ) )
			{
				/* Append the cookieCheck query string value */
				$url = $this->url->setQueryString( 'cookieCheck', 1 );

				/* If we have referrer info, don't lose it  */
				if( isset( \IPS\Request::i()->ref ) )
				{
					$url = $url->setQueryString( 'ref', \IPS\Request::i()->ref );
				}

				$_SESSION['_cookieCheck'] = TRUE; // Forces it to write the session so the above check will fail on the next load
				\IPS\Output::i()->redirect( $url, NULL, 307 ); // 307 instructs the browser to resubmit the form as a POST request maintaining all the values from before
			}
			else
			{
				\IPS\Output::i()->error( 'login_err_no_cookies', '1S267/1', 403, '' );
			}
		}
		
		$handlers = static::handlers();
		foreach ( $this->forms() as $handler => $form )
		{			
			/* Pass to the handler */
			$values = NULL;
			if ( ( is_object( $form ) and $values = $form->values() ) or ( ucfirst( \IPS\Request::i()->loginProcess ) === ucfirst( $handler ) ) )
			{
				/* Set any flags */
				foreach ( array_keys( $this->flags ) as $k )
				{
					if ( isset( $values[ $k ] ) )
					{
						$this->flags[ $k ] = $values[ $k ];
					}
				}
				
				/* Authenticate */
				$member = NULL;
				try
				{
					if ( $handler === '_standard' )
					{
						$values['auth'] = mb_strtolower( $values['auth'] );
						$member = $this->authenticateStandard( $values );
					}
					else
					{
						$member = $handlers[ $handler ]->authenticate( is_object( $form ) ? $values : $this->url );
					}
				}
				catch ( \IPS\Login\Exception $e )
				{
					/* Check if the account is locked and throw that error rather than a bad password error first */
					if ( $e->getCode() === \IPS\Login\Exception::BAD_PASSWORD )
					{
						$this->checkIfAccountIsLocked( $e->member );
					}
					
					/* If we're still here, throw the error we got */
					throw $e;
				}
												
				/* If we passed, log in! */
				if ( $member->member_id )
				{
					/* Set which handler processed it */
					if ( $handler !== '_standard' ) // If _standard, is set in authenticateStandard()
					{
						$this->usedHandler = $handler;
					}
					
					//if( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation != 'setup' )
					//{
						/* Check if the account is locked */
						$this->checkIfAccountIsLocked( $member );
						
						/* Remove old failed login attempts */
						if ( \IPS\Settings::i()->ipb_bruteforce_period and ( \IPS\Settings::i()->ipb_bruteforce_unlock or !isset( $member->failed_logins[ \IPS\Request::i()->ipAddress() ] ) or $member->failed_logins[ \IPS\Request::i()->ipAddress() ] < \IPS\Settings::i()->ipb_bruteforce_attempts ) )
						{
							$removeLoginsOlderThan = \IPS\DateTime::create()->sub( new \DateInterval( 'PT' . \IPS\Settings::i()->ipb_bruteforce_period . 'M' ) );
							$failedLogins = $member->failed_logins;

							/* The failed login data could potentially not be an array (i.e. a float) but as this code executes during the first
								step of upgrading to 4.0 if we don't force it to be an array here we could end up with an error exception we can't
								get past when attempting to upgrade. */
							if( !is_array( $failedLogins ) )
							{
								$failedLogins = array();
							}

							if ( is_array( $failedLogins ) )
							{
								foreach ( $failedLogins as $ipAddress => $times )
								{
									foreach ( $times as $k => $v )
									{
										if ( $v < $removeLoginsOlderThan->getTimestamp() )
										{
											unset( $failedLogins[ $ipAddress ][ $k ] );
										}
									}
								}
								$member->failed_logins = $failedLogins;
							}
							else
							{
								$member->failed_logins = array();
							}
							$member->save();
						}
					
						/* If we're still here, the login was fine, so we can reset the count and process login */
						if ( isset( $member->failed_logins[ \IPS\Request::i()->ipAddress() ] ) )
						{
							$failedLogins = $member->failed_logins;
							unset( $failedLogins[ \IPS\Request::i()->ipAddress() ] );
							$member->failed_logins = $failedLogins;
						}
						$member->last_visit = time();
						$member->save();
					//}
					
					return $member;
				}
			}
		}
		
		/* Still here? Just throw an exception */
		return NULL;
	}
	
	/**
	 * Authenticate all 'Standard' forms
	 *
	 * @param	array	$values		Values from form
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticateStandard( $values )
	{
		$member = NULL;
		$leastOffensiveException = NULL;
		
		$handlers = static::handlers();
		foreach ( $this->standardHandlers as $key )
		{
			try
			{
				$member = $handlers[ $key ]->authenticate( $values );
				$this->usedHandler = $key;
				break;
			}
			catch ( \IPS\Login\Exception $e )
			{
				if ( $leastOffensiveException === NULL or $leastOffensiveException->getCode() > $e->getCode() )
				{
					$leastOffensiveException = $e;
				}
			}
		}
	
		if ( $member === NULL )
		{
			throw $leastOffensiveException;
		}
		else
		{
			return $member;
		}
	}
	
	/**
	 * Check if an account is locked
	 *
	 * @param	\IPS\Member	$member	The account
	 * @return	void
	 * @throws	\Exception
	 */
	protected function checkIfAccountIsLocked( $member )
	{
		$unlockTime = static::accountUnlockTime( $member );
		if ( $unlockTime !== FALSE )
		{
			/* Notify the member if they've been locked */
			if( count( $member->failed_logins[ \IPS\Request::i()->ipAddress() ] ) == \IPS\Settings::i()->ipb_bruteforce_attempts )
			{
				/* Can we get a physical location */
				try
				{
					$location = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
				}
				catch ( \Exception $e )
				{
					$location = \IPS\Request::i()->ipAddress();
				}
				\IPS\Email::buildFromTemplate( 'core', 'account_locked', array( $member, $location, isset( $unlockTime ) ? $unlockTime : NULL ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $member );
			}

			if ( \IPS\Settings::i()->ipb_bruteforce_period and \IPS\Settings::i()->ipb_bruteforce_unlock )
			{
				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_locked_unlock', FALSE, array( 'pluralize' => array( $unlockTime->diff( new DateTime() )->format('%i') ) ) ) );
			}
			else
			{
				throw new \IPS\Login\Exception( 'login_err_locked_nounlock' );
			}
		}
	}
	
	/**
	 * Check if an account is locked - returns FALSE if account is unlocked, an \IPS\DateTime object if the account is locked until a certain time, or TRUE if account is unlocked indefinitely
	 *
	 * @param	\IPS\Member	$member	The account
	 * @return	\IPS\DateTime|bool
	 */
	public static function accountUnlockTime( $member )
	{
		if ( \IPS\Settings::i()->ipb_bruteforce_attempts and isset( $member->failed_logins[ \IPS\Request::i()->ipAddress() ] ) and count( $member->failed_logins[ \IPS\Request::i()->ipAddress() ] ) >= \IPS\Settings::i()->ipb_bruteforce_attempts )
		{
			if ( \IPS\Settings::i()->ipb_bruteforce_period and \IPS\Settings::i()->ipb_bruteforce_unlock )
			{
				$failedLogins = $member->failed_logins[ \IPS\Request::i()->ipAddress() ];
				sort( $failedLogins );

				while ( count( $failedLogins ) > \IPS\Settings::i()->ipb_bruteforce_attempts )
				{
					array_pop( $failedLogins );
				}
				$unlockTime = \IPS\DateTime::ts( array_pop( $failedLogins ) );
				$unlockTime->add( new \DateInterval( 'PT' . \IPS\Settings::i()->ipb_bruteforce_period . 'M' ) );

				/* If Unlock Time is in the past, return FALSE to avoid the exception and allow login */
				if ( $unlockTime->getTimestamp() < time() )
				{
					return FALSE;
				}
				
				/* Otherwise that is what we're returning */
				return $unlockTime;
			}
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Compare hashes in fixed length, time constant manner.
	 *
	 * @param	string	$expected	The expected hash
	 * @param	string	$provided	The provided input
	 * @return	boolean
	 */
	public static function compareHashes( $expected, $provided )
	{
		if ( !is_string( $expected ) || !is_string( $provided ) || $expected === '*0' || $expected === '*1' || $provided === '*0' || $provided === '*1' ) // *0 and *1 are failures from crypt() - if we have ended up with an invalid hash anywhere, we will reject it to prevent a possible vulnerability from deliberately generating invalid hashes
		{
			return FALSE;
		}
	
		$len = \strlen( $expected );
		if ( $len !== \strlen( $provided ) )
		{
			return FALSE;
		}
	
		$status = 0;
		for ( $i = 0; $i < $len; $i++ )
		{
			$status |= ord( $expected[ $i ] ) ^ ord( $provided[ $i ] );
		}
		
		return $status === 0;
	}

	/**
	 * Return a random string
	 *
	 * @param	int		$length		The length of the final string
	 * @return	string
	 */
	public static function generateRandomString( $length=32 )
	{
		$return = '';

		if( function_exists( 'openssl_random_pseudo_bytes' ) )
		{
			$return = \substr( bin2hex( openssl_random_pseudo_bytes( ceil( $length / 2 ) ) ), 0, $length );
		}

		/* Fallback JUST IN CASE */
		if( !$return OR \strlen( $return ) != $length )
		{
			$return = \substr( md5( uniqid( microtime(), true ) ) . md5( uniqid( microtime(), true ) ), 0, $length );
		}

		return $return;
	}
}