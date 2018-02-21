<?php
/**
 * @brief		Converter Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Oct 2014
 */
namespace IPS\Login;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( ! defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER[ 'SERVER_PROTOCOL' ] ) ? $_SERVER[ 'SERVER_PROTOCOL' ] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit();
}

/**
 * Converter Login Handler
 */
class _Convert extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'arrow-right';
	
	/** 
	 * @brief	Auth types
	 */
	public $authTypes = NULL;

	/**
	 * Initiate
	 *
	 * @return	void
	 */
	public function init()
	{
		$this->authTypes = $this->settings['auth_types'] ?: \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL;
	}
	
	/**
	 * Authenticate
	 *
	 * @param	array	$values	Values from form
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( $values )
	{
		/* Get member(s) */
		$members = array();
		if ( $this->authTypes & \IPS\Login::AUTH_TYPE_USERNAME )
		{
			$_member = \IPS\Member::load( $values[ 'auth' ], 'name', NULL );
			if ( $_member->member_id )
			{
				$members[] = $_member;
			}
		}
		if ( $this->authTypes & \IPS\Login::AUTH_TYPE_EMAIL )
		{
			$_member = \IPS\Member::load( $values[ 'auth' ], 'email' );
			if ( $_member->member_id )
			{
				$members[] = $_member;
			}
		}
		
		/* If we didn't match any, throw an exception */
		if ( empty( $members ) )
		{
			throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_no_account', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $this->getLoginType( $this->authTypes ) ) ) ) ), \IPS\Login\Exception::NO_ACCOUNT );
		}
		
		/* Table switcher for new converters */		
		try
		{
			try
			{
				$apps = iterator_to_array( \IPS\Db::i()->select( 'app_key', 'convert_apps', array( 'login=?', 1 ) ) );
			}
			catch ( \IPS\Db\Exception $e )
			{
				if ( $e->getCode() === 1146 )
				{
					$apps = \IPS\Db::i()->select( 'app_key', 'conv_apps', array( 'login=?', 1 ) );
				}
				else
				{
					throw $e;
				}
			}
			
			foreach( $apps as $sw )
			{
				/* Strip underscores from keys */
				$sw = str_replace( "_", "", $sw );
				
				/* loop found members */
				foreach( $members as $member )
				{
					/* Check the app method exists */
					if ( !method_exists( $this, $sw ) )
					{
						continue;
					}
					
					/* We still want to use the parent methods (no sense in recreating them) so copy conv_password_extra to misc */
					$member->misc = $member->conv_password_extra;
					$success = $this->$sw( $member, $values['password'] );
					
					unset( $member->misc );
					unset( $member->changed['misc'] );
					if ( $success )
					{
						/*	Update password and return */
						$member->conv_password			= NULL;
						$member->conv_password_extra	= NULL;
						$member->members_pass_salt		= $member->generateSalt();
						$member->members_pass_hash		= $member->encryptedPassword( $values['password'] );
						$member->save();
						$member->memberSync( 'onPassChange', array( $values['password'] ) );
						
						return $member;
					}
				}
			}
		}
		catch( \IPS\Db\Exception $e )
		{
			/* Converter tables no longer exist */
			if( $e->getCode() == 1146 )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
		}
		
		/* Still here? Throw a password incorrect exception */
		throw new \IPS\Login\Exception( 'login_err_bad_password', \IPS\Login\Exception::BAD_PASSWORD, NULL, isset( $member ) ? $member : NULL );
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
		return array(
			'auth_types'	=> new \IPS\Helpers\Form\Select( 'login_auth_types', $this->settings['auth_types'], TRUE, array( 'options' => array(
				\IPS\Login::AUTH_TYPE_USERNAME => 'username',
				\IPS\Login::AUTH_TYPE_EMAIL	=> 'email_address',
				\IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => 'username_or_email',
			) ) )
		);
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
		// Though this is not entirely true, once a user logs in with the Convert method,
		// a password for the Internal method is created for them so we want that to
		// be being used and not for this method to be depended on
		return FALSE;
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
	 * AEF
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function aef( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( $member->misc . $password ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * BBPress Standalone
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function bbpressstandalone( $member, $password )
	{
		return $this->bbpress( $member, $password );
	}
	
	/**
	 * BBPress
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function bbpress( $member, $password )
	{
		$success = false;
		$password = html_entity_decode( $password );
		$hash = $member->conv_password;
		
		if ( \strlen( $hash ) == 32 )
		{
			$success = ( bool ) ( \IPS\Login::compareHashes( $member->conv_password, md5( $password ) ) );
		}
		
		// Nope, not md5.
		if ( ! $success )
		{
			$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			$crypt = $this->hashCryptPrivate( $password, $hash, $itoa64, 'P' );
			if ( $crypt[ 0 ] == '*' )
			{
				$crypt = crypt( $password, $hash );
			}
			
			if ( $crypt == $hash )
			{
				$success = true;
			}
		}
		
		// Nope
		if ( ! $success )
		{
			// No - check against WordPress.
			// Note to self - perhaps push this to main bbpress method.
			$success = $this->wordpress( $member, $password );
		}
		
		return $success;
	}
	
	/**
	 * BBPress 2.3
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function bbpress23( $member, $password )
	{
		return $this->bbpress( $member, $password );
	}
	
	/**
	 * Community Server
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function cs( $member, $password )
	{		
		$encodedHashPass = base64_encode( pack( "H*", sha1( base64_decode( $member->misc ) . $password ) ) );
		
		if ( \IPS\Login::compareHashes( $member->conv_password, $encodedHashPass ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * CSAuth
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function csauth( $member, $password )
	{
		$wsdl = 'https://internal.auth.com/Service.asmx?wsdl';
		$dest = 'https://interal.auth.com/Service.asmx';
		$single_md5_pass = md5( $password );
		
		try
		{
			$client = new SoapClient( $wsdl, array( 'trace' => 1 ) );
			$client->__setLocation( $dest );
			$loginparams = array( 'username' => $member->name, 'password' => $password );
			$result = $client->AuthCS( $loginparams );
			
			switch( $result->AuthCSResult )
			{
				case 'SUCCESS' :
					return TRUE;
				case 'WRONG_AUTH' :
					return FALSE;
				default :
					return FALSE;
			}
		}
		catch( Exception $ex )
		{
			return FALSE;
		}
	}
	
	/**
	 * Discuz
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function discuz( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $password ) . $member->misc ) ) )
		{
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * ExpressionEngine
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function expressionengine( $member, $password )
	{
		$length = \strlen( $member->conv_password );
		$providedHash = FALSE;

		switch( $length )
		{
			/* MD5 */
			case 32:
				$providedHash = md5( $password );
			break;
			/* SHA1 */
			case 40:
				$providedHash = sha1( $password );
			break;
			/* SHA256 */
			case 64:
				$providedHash = hash( 'sha256', $password );
			break;
			/* SHA512 */
			case 128:
				$providedHash = hash( 'sha512', $password );
			break;
		}

		return ( \IPS\Login::compareHashes( $member->conv_password, $providedHash ) ) ? TRUE : FALSE;
	}
	
	/**
	 * FudForum
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function fudforum( $member, $password )
	{
		$success = false;
		$single_md5_pass = md5( $password );
		$hash = $member->conv_password;
		
		if ( \strlen( $hash ) == 40 )
		{
			$success = ( \IPS\Login::compareHashes( $member->conv_password, sha1( $member->misc . sha1( $password ) ) ) ) ? TRUE : FALSE;
		}
		else
		{
			$success = ( \IPS\Login::compareHashes( $member->conv_password, $single_md5_pass ) ) ? TRUE : FALSE;
		}
		
		return $success;
	}
	
	/**
	 * FusionBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function fusionbb( $member, $password )
	{
		/* FusionBB Has multiple methods that can be used to check a hash, so we need to cycle through them */
	
		/* md5( md5( salt ) . md5( pass ) ) */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $member->misc ) . md5( $password ) ) ) )
		{
			return TRUE;
		}
		
		/* md5( md5( salt ) . pass ) */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $member->misc ) . $password ) ) )
		{
			return TRUE;
		}
		
		/* md5( pass ) */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( $password ) ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Ikonboard
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function ikonboard( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, crypt( $password, $member->misc ) ) )
		{
			return TRUE;
		}
		else if ( \IPS\Login::compareHashes( $member->conv_password, md5( $password . mb_strtolower( $member->conv_password_extra ) ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Joomla
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function joomla( $member, $password )
	{
		/* Joomla 3 */
		if( preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $member->conv_password ) )
		{
			$ph = new PasswordHash( 8, TRUE );
			return $ph->CheckPassword( $password, $member->conv_password ) ? TRUE : FALSE;
		}

		/* Joomla 2 */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( $password . $member->misc ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Kunena
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function kunena( $member, $password )
	{
		// Kunena authenticates using internal Joomla functions.
		// This is required, however, if the member only converts from
		// Kunena and not Joomla + Kunena.
		return $this->joomla( $member, $password );
	}
	
	/**
	 * PhpBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function phpbb( $member, $password )
	{
		$password = html_entity_decode( $password );
		$success = FALSE;
		$hash = $member->conv_password;
		
		/* phpBB 3.1 */
		if( preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $hash ) )
		{
			$ph = new PasswordHash( 8, TRUE );
			$success = $ph->CheckPassword( $password, $member->conv_password ) ? TRUE : FALSE;
		}
		
		
		if ( $success === FALSE )
		{
			/* phpBB3 */
			$single_md5_pass = md5( $password );
			$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			
			if ( \strlen( $hash ) == 34 )
			{
				$success = ( \IPS\Login::compareHashes( $hash, $this->hashCryptPrivate( $password, $hash, $itoa64 ) ) ) ? TRUE : FALSE;
			}
			else
			{
				$success = ( \IPS\Login::compareHashes( $hash, $single_md5_pass ) ) ? TRUE : FALSE;
			}
		}
		
		/* phpBB2 */
		if ( !$success )
		{
			$success = ( \IPS\Login::compareHashes( $hash, $this->hashCryptPrivate( $single_md5_pass, $hash, $itoa64 ) ) ) ? TRUE : FALSE ;
		}
		
		return $success;
	}
	
	/**
	 * PHP Legacy (2.x)
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function phpbblegacy( $member, $password )
	{
		return $this->phpbb( $member, $password );
	}
	
	/**
	 * Vanilla
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vanilla( $member, $password )
	{
		/* Vanilla 2.2 */
		if( preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $member->conv_password ) OR mb_substr( $member->conv_password, 0, 3 ) == '$P$' )
		{
			$ph = new PasswordHash( 8, TRUE );
			return $ph->CheckPassword( $password, $member->conv_password ) ? TRUE : FALSE;
		}

		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( str_replace( '&#39;', "'", html_entity_decode( $password ) ) ) . $member->misc ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Vbulletin 5.1
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vb51connect( $member, $password )
	{
		/* Which do we need to use? */
		$algo = explode( ':', $member->misc );
		
		switch( $algo[ 0 ] )
		{
			case 'blowfish':
				/* vBulletin uses PasswordHash, however they md5 once the password prior to checking */
				$md5_once_password = md5( $password );
				$ph = new PasswordHash( $algo[ 1 ], FALSE );
				return $ph->CheckPassword( $md5_once_password, $member->conv_password );
				break;
			
			case 'legacy':
				/* Legacy Passwords are stored in a format of HASH SALT so we need to explode on the space. */
				$token = explode( ' ', $member->conv_password );
				return $this->vbulletin( $member, $password, $token[ 1 ], $token[ 0 ] );
				break;
		}
	}
	
	/**
	 * Vbulletin 5
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vb5connect( $member, $password )
	{
		if ( \strpos( $member->misc, 'blowfish' ) === FALSE and \strpos( $member->misc, 'legacy' ) === FALSE )
		{
			return $this->vbulletin( $member, $password );
		}
		else
		{
			return $this->vb51connect( $member, $password );
		}
	}

	/**
	 * vBulletin 5 Wrapper Method
	 *
	 * @param	\IPS|Member		$member
	 * @param	string			$password
	 * @return	bool
	 */
	public function vbulletin5( $member, $password )
	{
		return $this->vb5connect( $member, $password );
	}
	
	/**
	 * Vbulletin 4
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vbulletin( $member, $password, $salt = NULL, $hash = NULL )
	{
		if ( is_null( $salt ) )
		{
			$salt = $member->misc;
		}
		
		if ( is_null( $hash ) )
		{
			$hash = $member->conv_password;
		}
		
		$password = html_entity_decode( $password );
		if ( \IPS\Login::compareHashes( $hash, md5( md5( str_replace( '&#39;', "'", $password ) ) . $salt ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Vbulletin 3.8
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vbulletinlegacy( $member, $password )
	{
		return $this->vbulletin( $member, $password );
	}
	
	/**
	 * Vbulletin 3.6
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vbulletinlegacy36( $member, $password )
	{
		return $this->vbulletin( $member, $password );
	}
	
	/**
	 * MyBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function mybb( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $member->misc ) . md5( $password ) ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * SMF
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function smf( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->name ) . html_entity_decode( $password ) ) ) )
		{
			return TRUE;
		}
		else if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->name ) . $password ) ) )
		{
			return TRUE;
		}
		/* In 4.2.6 we save the original members name as the salt so that we don't have one that has been modified in the conversion process */
		else if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->conv_password_extra ) . $password ) ) )
		{
			return TRUE;
		}
		else
		{
			$ph = new PasswordHash( 8, TRUE );

			if( $ph->CheckPassword( mb_strtolower( $member->name ) . $password, $member->conv_password ) )
			{
				return TRUE;
			}
			/* In 4.2.6 we save the original members name as the salt so that we don't have one that has been modified in the conversion process */
			else if ( $ph->CheckPassword( mb_strtolower( $member->conv_password_extra ) . $password, $member->conv_password ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}
	
	/**
	 * SMF Legacy
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function smflegacy( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->name ) . $password ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Telligent
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function telligentcs( $member, $password )
	{
		return $this->cs( $member, $password );
	}
	
	/**
	 * WoltLab 4.x
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function woltlab( $member, $password )
	{
		$testHash = FALSE;

		/* If it's not blowfish, then we don't have a salt for it. */
		if ( !preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $member->conv_password ) )
		{
			$salt = mb_substr( $member->conv_password, 0, 29 );
			$testHash = crypt( crypt( $password, $salt ), $salt );
		}
		
		if (	$testHash AND \IPS\Login::compareHashes( $member->conv_password, $testHash ) )
		{
			return TRUE;
		}
		elseif ( $this->woltlablegacy( $member, $password ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * WoltLab 3.x
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function woltlablegacy( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, sha1( $member->misc . sha1( $member->misc . sha1( $password ) ) ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * WebWiz
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function webwiz( $member, $password )
	{
		$success = FALSE;
		
		if ( \IPS\Login::compareHashes( $member->conv_password, webWizAuth::HashEncode( $password . $member->misc ) ) )
		{
			$success = TRUE;
		}
		
		return $success;
	}
	
	/**
	 * XenForo
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function xenforo( $member, $password )
	{
		$password = html_entity_decode( $password );
		
		// XF 1.2
		if ( $this->xenforo12( $member, $password ) )
		{
			return TRUE;
		}
		
		// XF 1.1
		if ( $this->xenforo11( $member, $password ) )
		{
			return TRUE;
		}
		
		// If they converted vB > XF > IPB then passwords may be in vB format still - try that.
		if ( $this->vbulletin( $member, $password ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * XenForo 1.2
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function xenforo12( $member, $password )
	{
		if ( !isset( \IPS\Settings::i()->xenforo_password_iterations ) )
		{
			\IPS\Settings::i()->xenforo_password_iterations = 10;
		}
		
		$ph = new PasswordHash( \IPS\Settings::i()->xenforo_password_iterations, false );
		
		if ( $ph->CheckPassword( $password, $member->conv_password ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * XenForo 1.1
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function xenforo11( $member, $password )
	{
		if ( extension_loaded( 'hash' ) )
		{
			$hashedPassword = hash( 'sha256', hash( 'sha256', $password ) . $member->misc );
		}
		else
		{
			$hashedPassword = sha1( sha1( $password ) . $member->misc );
		}
		
		if ( \IPS\Login::compareHashes( $member->conv_password, $hashedPassword ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * PHP Fusion
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function phpfusion( $member, $password )
	{
		return ( bool ) \IPS\Login::compareHashes( $member->conv_password, md5( md5( $password ) ) );
	}
	
	/**
	 * fluxBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function fluxbb( $member, $password )
	{
		$success = false;
		$hash = $member->conv_password;
		
		if ( \strlen( $hash ) == 40 )
		{
			if ( \IPS\Login::compareHashes( $hash, sha1( $member->misc . sha1( $password ) ) ) )
			{
				$success = TRUE;
			}
			elseif ( \IPS\Login::compareHashes( $hash, sha1( $password ) ) )
			{
				$success = TRUE;
			}
		}
		else
		{
			$success = ( \IPS\Login::compareHashes( $hash, md5( $password ) ) ) ? TRUE : FALSE;
		}
		
		return $success;
	}
	
	/**
	 * punBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function punbb( $member, $password )
	{
		$success = FALSE;
		
		if ( mb_strlen( $member->conv_password ) == 40 )
		{
			/* Password with salt */
			$success = \IPS\Login::compareHashes( $member->conv_password, sha1( $member->conv_password_extra . sha1( $password ) ) );
			
			if ( !$success )
			{
				/* No salt */
				$success = \IPS\Login::compareHashes( $member->conv_password, sha1( $password ) );
			}
		}
		else
		{
			/* MD5 */
			$success = \IPS\Login::compareHashes( $member->conv_password, md5( $password ) );
		}
		
		return $success;
	}
	
	/**
	 * Simplepress Forum
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function simplepress( $member, $password )
	{
		return $this->wordpress( $member, $password );
	}
	
	/**
	 * UBB Threads
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function ubbthreads( $member, $password )
	{
		$hash = $member->members_pass_hash;
		$salt = $member->members_pass_salt;
		
		if ( \IPS\Login::compareHashes( $hash, md5( $password ) ) )
		{
			return TRUE;
		}
		
		// Not using md5, UBB salts the password with the password
		// IPB already md5'd it though, *sigh*
		if ( \IPS\Login::compareHashes( $hash, md5( md5( $salt ) . crypt( $password, $password ) ) ) )
		{
			return TRUE;
		}
		
		// Now standard IPB check.
		if ( \IPS\Login::compareHashes( $hash, md5( md5( $salt ) . md5( $password ) ) ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Wordpress
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function wordpress( $member, $password )
	{
		$success = FALSE;
		
		// If the hash is still md5...
		if ( \strlen( $member->conv_password ) <= 32 )
		{
			$success = ( \IPS\Login::compareHashes( $member->conv_password, md5( $password ) ) ) ? TRUE : FALSE;
		}
		// New pass hash check
		else
		{
			// Init the pass class
			$ph = new PasswordHash;
			$ph->PasswordHash( 8, TRUE );
			
			// Check it
			$success = $ph->CheckPassword( $password, $member->conv_password ) ? TRUE : FALSE;
		}
		
		return $success;
	}
	
	/**
	 * Private crypt hashing for phpBB 3
	 *
	 * @access	public
	 * @param	string		Password
	 * @param	string 		Settings
	 * @param	string		Hash-lookup
	 * @return	string		phpBB3 password hash
	 */
	protected function hashCryptPrivate( $password, $setting, &$itoa64 )
	{
		$output	= '*';
	
		// Check for correct hash
		if ( \substr( $setting, 0, 3 ) != '$H$' )
		{
			return $output;
		}
	
		$count_log2 = \strpos( $itoa64, $setting[3] );
	
		if ( $count_log2 < 7 || $count_log2 > 30 )
		{
			return $output;
		}
	
		$count	= 1 << $count_log2;
		$salt	= \substr( $setting, 4, 8 );
	
		if ( \strlen($salt) != 8 )
		{
			return $output;
		}
	
		/**
		 * We're kind of forced to use MD5 here since it's the only
		 * cryptographic primitive available in all versions of PHP
		 * currently in use.  To implement our own low-level crypto
		 * in PHP would result in much worse performance and
		 * consequently in lower iteration counts and hashes that are
		 * quicker to crack (by non-PHP code).
		 */
		if ( PHP_VERSION >= 5 )
		{
			$hash = md5( $salt . $password, true );
	
			do
			{
				$hash = md5( $hash . $password, true );
			}
			while ( --$count );
		}
		else
		{
			$hash = pack( 'H*', md5( $salt . $password ) );
	
			do
			{
				$hash = pack( 'H*', md5( $hash . $password ) );
			}
			while ( --$count );
		}
	
		$output	= \substr( $setting, 0, 12 );
		$output	.= $this->_hashEncode64( $hash, 16, $itoa64 );
	
		return $output;
	}
	
	/**
	 * Private function to encode phpBB3 hash
	 *
	 * @access	protected
	 * @param	string		Input
	 * @param	count 		Iteration
	 * @param	string		Hash-lookup
	 * @return	string		phpbb3 password hash encoded bit
	 */
	protected function _hashEncode64($input, $count, &$itoa64)
	{
		$output	= '';
		$i		= 0;
	
		do
		{
			$value	= ord( $input[$i++] );
			$output	.= $itoa64[$value & 0x3f];
	
			if ( $i < $count )
			{
				$value |= ord($input[$i]) << 8;
			}
	
			$output .= $itoa64[($value >> 6) & 0x3f];
	
			if ( $i++ >= $count )
			{
				break;
			}
	
			if ( $i < $count )
			{
				$value |= ord($input[$i]) << 16;
			}
	
			$output .= $itoa64[($value >> 12) & 0x3f];
	
			if ($i++ >= $count)
			{
				break;
			}
	
			$output .= $itoa64[($value >> 18) & 0x3f];
		}
		while ( $i < $count );
	
		return $output;
	}
}

/**
 * Portable PHP password hashing framework.
 * @package phpass
 * @since 2.5
 * @version 0.5
 * @link http://www.openwall.com/phpass/
 */

#
# Portable PHP password hashing framework.
#
# Version 0.5 / genuine.
#
# Written by Solar Designer <solar at openwall.com> in 2004-2006 and placed in
# the public domain.  Revised in subsequent years, still public domain.
#
# There's absolutely no warranty.
#
# The homepage URL for this framework is:
#
#	http://www.openwall.com/phpass/
#
# Please be sure to update the Version line if you edit this file in any way.
# It is suggested that you leave the main version number intact, but indicate
# your project name (after the slash) and add your own revision information.
#
# Please do not change the "private" password hashing method implemented in
# here, thereby making your hashes incompatible.  However, if you must, please
# change the hash type identifier (the "$P$") to something different.
#
# Obviously, since this code is in the public domain, the above are not
# requirements (there can be none), but merely suggestions.
#
class PasswordHash {
	var $itoa64;
	var $iteration_count_log2;
	var $portable_hashes;
	var $random_state;

	function __construct($iteration_count_log2, $portable_hashes)
	{
		$this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31)
			$iteration_count_log2 = 8;
		$this->iteration_count_log2 = $iteration_count_log2;

		$this->portable_hashes = $portable_hashes;

		$this->random_state = microtime();
		if (function_exists('getmypid'))
			$this->random_state .= getmypid();
	}

	function PasswordHash($iteration_count_log2, $portable_hashes)
	{
		self::__construct($iteration_count_log2, $portable_hashes);
	}

	function get_random_bytes($count)
	{
		$output = '';
		if (@is_readable('/dev/urandom') &&
		    ($fh = @fopen('/dev/urandom', 'rb'))) {
			$output = fread($fh, $count);
			fclose($fh);
		}

		if (strlen($output) < $count) {
			$output = '';
			for ($i = 0; $i < $count; $i += 16) {
				$this->random_state =
				    md5(microtime() . $this->random_state);
				$output .= md5($this->random_state, TRUE);
			}
			$output = substr($output, 0, $count);
		}

		return $output;
	}

	function encode64($input, $count)
	{
		$output = '';
		$i = 0;
		do {
			$value = ord($input[$i++]);
			$output .= $this->itoa64[$value & 0x3f];
			if ($i < $count)
				$value |= ord($input[$i]) << 8;
			$output .= $this->itoa64[($value >> 6) & 0x3f];
			if ($i++ >= $count)
				break;
			if ($i < $count)
				$value |= ord($input[$i]) << 16;
			$output .= $this->itoa64[($value >> 12) & 0x3f];
			if ($i++ >= $count)
				break;
			$output .= $this->itoa64[($value >> 18) & 0x3f];
		} while ($i < $count);

		return $output;
	}

	function gensalt_private($input)
	{
		$output = '$P$';
		$output .= $this->itoa64[min($this->iteration_count_log2 +
			((PHP_VERSION >= '5') ? 5 : 3), 30)];
		$output .= $this->encode64($input, 6);

		return $output;
	}

	function crypt_private($password, $setting)
	{
		$output = '*0';
		if (substr($setting, 0, 2) === $output)
			$output = '*1';

		$id = substr($setting, 0, 3);
		# We use "$P$", phpBB3 uses "$H$" for the same thing
		if ($id !== '$P$' && $id !== '$H$')
			return $output;

		$count_log2 = strpos($this->itoa64, $setting[3]);
		if ($count_log2 < 7 || $count_log2 > 30)
			return $output;

		$count = 1 << $count_log2;

		$salt = substr($setting, 4, 8);
		if (strlen($salt) !== 8)
			return $output;

		# We were kind of forced to use MD5 here since it's the only
		# cryptographic primitive that was available in all versions
		# of PHP in use.  To implement our own low-level crypto in PHP
		# would have resulted in much worse performance and
		# consequently in lower iteration counts and hashes that are
		# quicker to crack (by non-PHP code).
		$hash = md5($salt . $password, TRUE);
		do {
			$hash = md5($hash . $password, TRUE);
		} while (--$count);

		$output = substr($setting, 0, 12);
		$output .= $this->encode64($hash, 16);

		return $output;
	}

	function gensalt_blowfish($input)
	{
		# This one needs to use a different order of characters and a
		# different encoding scheme from the one in encode64() above.
		# We care because the last character in our encoded string will
		# only represent 2 bits.  While two known implementations of
		# bcrypt will happily accept and correct a salt string which
		# has the 4 unused bits set to non-zero, we do not want to take
		# chances and we also do not want to waste an additional byte
		# of entropy.
		$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		$output = '$2a$';
		$output .= chr(ord('0') + $this->iteration_count_log2 / 10);
		$output .= chr(ord('0') + $this->iteration_count_log2 % 10);
		$output .= '$';

		$i = 0;
		do {
			$c1 = ord($input[$i++]);
			$output .= $itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if ($i >= 16) {
				$output .= $itoa64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $itoa64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $itoa64[$c1];
			$output .= $itoa64[$c2 & 0x3f];
		} while (1);

		return $output;
	}

	function HashPassword($password)
	{
		$random = '';

		if (CRYPT_BLOWFISH === 1 && !$this->portable_hashes) {
			$random = $this->get_random_bytes(16);
			$hash =
			    crypt($password, $this->gensalt_blowfish($random));
			if (strlen($hash) === 60)
				return $hash;
		}

		if (strlen($random) < 6)
			$random = $this->get_random_bytes(6);
		$hash =
		    $this->crypt_private($password,
		    $this->gensalt_private($random));
		if (strlen($hash) === 34)
			return $hash;

		# Returning '*' on error is safe here, but would _not_ be safe
		# in a crypt(3)-like function used _both_ for generating new
		# hashes and for validating passwords against existing hashes.
		return '*';
	}

	function CheckPassword($password, $stored_hash)
	{
		$hash = $this->crypt_private($password, $stored_hash);
		if ($hash[0] === '*')
			$hash = crypt($password, $stored_hash);

		# This is not constant-time.  In order to keep the code simple,
		# for timing safety we currently rely on the salts being
		# unpredictable, which they are at least in the non-fallback
		# cases (that is, when we use /dev/urandom and bcrypt).
		return $hash === $stored_hash;
	}
}
