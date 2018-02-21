<?php
/**
 * @brief		Initiates Invision Community constants, autoloader and exception handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS;

/**
 * Class to contain Invision Community autoloader and exception handler
 */
class IPS
{	
	/**
	 * @brief	Classes that have hooks on
	 */
	public static $hooks = array();
	
	/**
	 * @brief	Unique key for this suite (used in http requests to defy browser caching)
	 */
	public static $suiteUniqueKey = NULL;
	
	/**
	 * @brief	Developer Code to be added to all namespaces
	 */
	private static $inDevCode = '';
	
	/**
	 * @brief	Namespaces developer code has been imported to
	 */
	private static $inDevCodeImportedTo = array();
	
	/**
	 * @brief	Vendors to use PSR-0 autoloader for
	 */
	public static $PSR0Namespaces = array();

	/**
	 * Get default constants
	 *
	 * @return	array
	 */
	public static function defaultConstants()
	{
		return array(
			'CIC'								=> isset( $_SERVER['IPS_CIC'] ),
			'CP_DIRECTORY'						=> 'admin',
			'IN_DEV'							=> FALSE,
			'DEV_USE_WHOOPS'					=> TRUE,
			'DEV_USE_FURL_CACHE'				=> FALSE,
			'DEV_USE_MENU_CACHE'				=> FALSE,
			'DEBUG_JS'							=> FALSE,
			'DEV_DEBUG_JS'						=> TRUE,
			'DEV_DEBUG_CSS'						=> FALSE,
			'DEBUG_TEMPLATES'					=> FALSE,
			'IPS_FOLDER_PERMISSION'				=> 0777,
			'FOLDER_PERMISSION_NO_WRITE'		=> 0755,
			'IPS_FILE_PERMISSION'				=> 0666,
			'FILE_PERMISSION_NO_WRITE'			=> 0644,
			'ROOT_PATH'							=> __DIR__,
			'NO_WRITES'							=> ( isset( $_SERVER['IPS_CIC'] ) and !isset( $_SERVER['IPS_CIC_ACP'] ) ),
			'DEBUG_LOG'							=> FALSE,
			'LOG_FALLBACK_DIR'					=> '{root}/uploads/logs',
			'STORE_METHOD'						=> ( isset( $_SERVER['IPS_CIC'] ) ? 'Database' : 'FileSystem' ),
			'STORE_CONFIG'						=> '{"path":"{root}/datastore"}',
			'CACHE_METHOD'						=> isset( $_SERVER['IPS_CLOUD_REDIS425'] ) ? 'Redis' : 'None',
			'CACHE_CONFIG'						=> isset( $_SERVER['IPS_CLOUD_REDIS425'] ) ? '{ "server": "' . $_SERVER['IPS_CLOUD_REDIS425'] . '", "port": 6379 }' : '{}',
			'CACHE_PAGE_TIMEOUT'				=> ( isset( $_SERVER['IPS_CIC'] ) ? 300 : 30 ),
			'TEST_CACHING'						=> FALSE,
			'EMAIL_DEBUG_PATH'					=> NULL,
			'BULK_MAILS_PER_CYCLE'				=> 50,
			'JAVA_PATH'							=> "",
			'ERROR_PAGE'						=> 'error.php',
			'UPGRADING_PAGE'					=> ( defined( 'CP_DIRECTORY' ) ) ? CP_DIRECTORY . '/upgrade/upgrading.html' : 'admin/upgrade/upgrading.html', # defined call here just as a sanity check
			'QUERY_LOG'							=> FALSE,
			'CACHING_LOG'						=> FALSE,
			'ENFORCE_ACCESS'					=> FALSE,
			'THUMBNAIL_SIZE'					=> '500x500',
			'PHOTO_THUMBNAIL_SIZE'				=> 240, // The max we display is 120x120, so this allows for double size for high dpi screens
			'COOKIE_DOMAIN'						=> NULL,
			'COOKIE_PREFIX'						=> 'ips4_',
			'COOKIE_PATH'						=> NULL,
			'COOKIE_BYPASS_SSLONLY'				=> FALSE,
			'CONNECT_NOSYNC_NAMES'				=> FALSE,
			'BYPASS_CURL'						=> FALSE,
			'FORCE_CURL'						=> FALSE,
			'NEXUS_TEST_GATEWAYS'				=> FALSE,
			'NEXUS_LKEY_API_DISABLE'			=> TRUE,
			'NEXUS_LKEY_API_CHECK_IP'			=> TRUE,
			'NEXUS_LKEY_API_ALLOW_IP_OVERRIDE'	=> FALSE,
			'UPGRADE_MANUAL_THRESHOLD'			=> 250000,
			'HTMLENTITIES'						=> ( version_compare( phpversion(), '5.4.0', '<' ) ) ? ENT_IGNORE : ENT_DISALLOWED,
			'SUITE_UNIQUE_KEY'					=> ( isset( $_SERVER['IPS_CIC'] ) and preg_match( '/^\/var\/www\/html\/(.+?)$/i', __DIR__, $matches ) ) ? str_replace( '/', '', $matches[1] ) : mb_substr( md5( '12627' . '$Rev: 3023$'), 10, 10 ),
			'CACHEBUST_KEY'						=> mb_substr( md5( '12627' . '$Rev: 3023$'), 10, 10 ), // This looks unnecessary but SUITE_UNIQUE_KEY can be set to a constant constant in constants.php whereas we need a version specific constant for cache busting.
			'TEXT_ENCRYPTION_KEY'				=> NULL,
			'CONNECT_MASTER_KEY'				=> NULL,
			'USE_DEVELOPMENT_BUILDS'			=> FALSE,
			'DEV_WHOOPS_EDITOR'					=> NULL,
			'DEFAULT_REQUEST_TIMEOUT'			=> 10,		// In seconds - default for most external connections
			'LONG_REQUEST_TIMEOUT'				=> 30,		// In seconds - used for specific API-based calls where we expect a slightly longer response time
			'TEMP_DIRECTORY'					=> sys_get_temp_dir(),
			'TEST_DELTA_ZIP'					=> '',
			'DELTA_FORCE_FTP'					=> FALSE,
			'BYPASS_ACP_IP_CHECK'				=> FALSE,
			'RECOVERY_MODE'						=> FALSE,
			'DEV_FORCE_MFA'						=> FALSE,
			'DISABLE_MFA'						=> FALSE,
			'BOT_SEARCH_FLOOD_SECONDS'			=> 30,
			'READ_WRITE_SEPARATION'				=> TRUE,
			'USE_MYSQL_SEARCH_BASIC_MODE_THRESHOLD'	=> 0,
			'UPGRADE_MD5_CHECK'					=> TRUE,
			'NOTIFICATIONS_PER_BATCH'			=> 30,
			);
	}
	
	/**
	 * Initiate Invision Community constants, autoloader and exception handler
	 *
	 * @return	void
	 */
	public static function init()
	{
		/* Set timezone */
		date_default_timezone_set( 'UTC' );

		/* Set default MB internal encoding */
		mb_internal_encoding('UTF-8');

		/* Define the IN_IPB constant - this needs to be in the global namespace for backwards compatibility */
		define( 'IN_IPB', TRUE );
			
		/* Load constants.php */
		if( file_exists( __DIR__ . '/constants.php' ) )
		{
			@include_once( __DIR__ . '/constants.php' );
		}
				
		/* Import and set defaults */
		$defaultConstants = static::defaultConstants();

		foreach ( $defaultConstants as $k => $v )
		{
			if( defined( $k ) )
			{
				define( 'IPS\\' . $k, constant( $k ) );
			}
			else
			{
				define( 'IPS\\' . $k, $v );
			}
		}

		/* If they have customized the ACP directory but it doesn't exist, throw an error */
		if( !is_dir( ROOT_PATH . '/' . CP_DIRECTORY ) AND CP_DIRECTORY != $defaultConstants['CP_DIRECTORY'] )
		{
			die( "You have defined a custom ACP directory (CP_DIRECTORY) in constants.php, however it is not valid.  Please remove or correct this constant definition." );
		}
		
		/* Load developer code */
		if( IN_DEV and file_exists( ROOT_PATH . '/dev/function_overrides.php' ) )
		{
			self::$inDevCode = file_get_contents( ROOT_PATH . '/dev/function_overrides.php' );
		}
		
		/* Set autoloader */
		spl_autoload_register( '\IPS\IPS::autoloader', true, true );
				
		/* Set error handlers */
		if ( \IPS\IN_DEV AND \IPS\DEV_USE_WHOOPS and file_exists( ROOT_PATH . '/dev/Whoops/Run.php' ) )
		{
			self::$PSR0Namespaces['Whoops'] = ROOT_PATH . '/dev/Whoops';
			$whoops = new \Whoops\Run;
			$handler =  new \Whoops\Handler\PrettyPageHandler;
			if ( \IPS\DEV_WHOOPS_EDITOR )
			{
				$handler->setEditor( \IPS\DEV_WHOOPS_EDITOR );
			}
			$whoops->pushHandler( $handler );
			$whoops->register();
		}
		else
		{
			set_error_handler( '\IPS\IPS::errorHandler' );
			set_exception_handler( '\IPS\IPS::exceptionHandler' );
		}

		/* Init hooks */
		if ( file_exists( \IPS\ROOT_PATH . '/plugins/hooks.php' ) )
		{						
			if ( !( self::$hooks = require( \IPS\ROOT_PATH . '/plugins/hooks.php' ) ) )
			{
				self::$hooks = array();
			}
		}
	}

	/**
	 * Autoloader
	 *
	 * @param	string	$classname	Class to load
	 * @return	void
	 */
	public static function autoloader( $classname )
	{
		/* Separate by namespace */
		$bits = explode( '\\', ltrim( $classname, '\\' ) );
								
		/* If this doesn't belong to us, try a PSR-0 loader or ignore it */
		$vendorName = array_shift( $bits );
		if( $vendorName !== 'IPS' )
		{			
			if ( isset( self::$PSR0Namespaces[ $vendorName ] ) )
			{
				@include_once( self::$PSR0Namespaces[ $vendorName ] . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $bits ) . '.php' );
			}
			
			return;
		}
		
		/* Work out what namespace we're in */
		$class = array_pop( $bits );
		$namespace = empty( $bits ) ? 'IPS' : ( 'IPS\\' . implode( '\\', $bits ) );
		$inDevCode = '';
				
		/* We only need to load the file if we don't have the underscore-prefixed one */
		if( !class_exists( "{$namespace}\\_{$class}", FALSE ) )
		{			
			/* Locate file */
			$path = ROOT_PATH . '/';
			$sourcesDirSet = FALSE;
			foreach ( array_merge( $bits, array( $class ) ) as $i => $bit )
			{
				if( preg_match( "/^[a-z0-9]/", $bit ) )
				{
					if( $i === 0 )
					{
						$path .= 'applications/';
					}
					else
					{
						$sourcesDirSet = TRUE;
					}
				}
				elseif ( $i === 3 and $bit === 'Upgrade' )
				{
					$bit = mb_strtolower( $bit );
				}
				elseif( $sourcesDirSet === FALSE )
				{
					if( $i === 0 )
					{
						$path .= 'system/';
					}
					elseif ( $i === 1 and $bit === 'Application' )
					{
						// do nothing
					}
					else
					{
						$path .= 'sources/';
					}
					$sourcesDirSet = TRUE;
				}
							
				$path .= "{$bit}/";
			}
						
			/* Load it */
			$path = \substr( $path, 0, -1 ) . '.php';
			if( !file_exists( $path ) )
			{
				$path = \substr( $path, 0, -4 ) . \substr( $path, \strrpos( $path, '/' ) );
				if ( !file_exists( $path ) )
				{
					return FALSE;
				}
			}			
			require_once( $path );
			
			/* Is it an interface? */
			if ( interface_exists( "{$namespace}\\{$class}", FALSE ) )
			{
				return;
			}
			
			/* Is it a trait? The function_exists call is just so PHP 5.3 doesn't throw a fatal error (even though it's required, we want it to fail gracefully) */
			if ( function_exists('trait_exists') and trait_exists( "{$namespace}\\{$class}", FALSE ) )
			{
				return;
			}
							
			/* Doesn't exist? */
			if( !class_exists( "{$namespace}\\_{$class}", FALSE ) )
			{
				trigger_error( "Class {$classname} could not be loaded. Ensure it has been properly prefixed with an underscore and is in the correct namespace.", E_USER_ERROR );
			}
						
			/* Stuff for developer mode */
			if( IN_DEV )
			{
				$reflection = new \ReflectionClass( "{$namespace}\\_{$class}" );
				
				/* Import our code to override forbidden functions */
				if( !in_array( \strtolower( $namespace ), self::$inDevCodeImportedTo ) )
				{
					$inDevCode = self::$inDevCode;
					self::$inDevCodeImportedTo[] = \strtolower( $namespace );
				}
									
				/* Any classes which extend a core PHP class are exempt from our rules */
				$extendsCorePhpClass = FALSE;
				for ( $workingClass = $reflection; $parent = $workingClass->getParentClass(); $workingClass = $parent )
				{
					if ( \substr( $parent->getNamespaceName(), 0, 3 ) !== 'IPS' )
					{
						$extendsCorePhpClass = TRUE;
						break;
					}
				}
				if ( !$extendsCorePhpClass )
				{
					/* Make sure it's name follows our standards */
					if( !preg_match( '/^_[A-Z0-9]+$/i', $reflection->getShortName() ) )
					{
						trigger_error( "{$classname} does not follow our naming conventions. Please rename using only alphabetic characters and PascalCase. (PHP Coding Standards: Classes.5)", E_USER_ERROR );
					}
					
					/* Loop methods */
					$hasNonAbstract = FALSE;
					$hasNonStatic = FALSE;
					foreach ( $reflection->getMethods() as $method )
					{	
						if ( \substr( $method->getDeclaringClass()->getName(), 0, 3 ) === 'IPS' )
						{
							/* Make sure it's not private */
							if( $method->isPrivate() )
							{
								trigger_error( "{$classname}::{$method->name} is declared as private. In order to ensure that hooks are able to work freely, please use protected instead. (PHP Coding Standards: Functions and Methods.4)", E_USER_ERROR );
							}
						
							/* We need to know for later if we have non-abstract methods */
							if( !$method->isAbstract() )
							{
								$hasNonAbstract = TRUE;
							}
							
							/* We need to know for later if we have non-static methods */
							if( !$method->isStatic() )
							{
								$hasNonStatic = TRUE;
							}
							
							/* Make sure the name follows our conventions */
							if(
								!preg_match( '/^_?[a-z][A-Za-z0-9]*$/', $method->name )	// Normal pattern most methods should match
								and
								!preg_match( '/^get_/i', $method->name )		// get_* is allowed
								and
								!preg_match( '/^set_/i', $method->name )		// set_* is allowed
								and
								!preg_match( '/^parse_/i', $method->name )		// parse_* is allowed
								and
								!preg_match( '/^setBitwise_/i', $method->name )	// set_Bitiwse_* is allowed
								and
								!preg_match( '/^(GET|POST|PUT|DELETE)[a-zA-Z_]+$/', $method->name )	// API methods have a specific naming format
								and
								!in_array( $method->name, array(					// PHP's magic methods are allowed (except __sleep and __wakeup as we don't allow serializing)
									'__construct',
									'__destruct',
									'__call',
									'__callStatic',
									'__get',
									'__set',
									'__isset',
									'__unset',
									'__toString',
									'__invoke',
									'__set_state',
									'__clone',
								) )
							) {
								trigger_error( "{$classname}::{$method->name} does not follow our naming conventions. Please rename using only alphabetic characters and camelCase. (PHP Coding Standards: Functions and Methods.1-3)", E_USER_ERROR );
							}
						}
					}
					
					/* Loop properties */
					foreach ( $reflection->getProperties() as $property )
					{
						$hasNonAbstract = TRUE;
						
						/* Make sure it's not private */
						if( $property->isPrivate() )
						{
							trigger_error( "{$classname}::\${$property->name} is declared as private. In order to ensure that hooks are able to work freely, please use protected instead. (PHP Coding Standards: Properties and Variables.3)", E_USER_ERROR );
						}
					
						/* Make sure the name follows our conventions */
						if( !preg_match( '/^_?[a-z][A-Za-z]*$/', $property->name ) )
						{
							trigger_error( "{$classname}::\${$property->name} does not follow our naming conventions. Please rename using only alphabetic characters and camelCase. (PHP Coding Standards: Properties and Variables.1-2)", E_USER_ERROR );
						}
					}
					
					/* Check an interface wouldn't be more appropriate */
					if( !$hasNonAbstract )
					{
						trigger_error( "You do not have any non-abstract methods in {$classname}. Please use an interface instead. (PHP Coding Standards: Classes.7)", E_USER_ERROR );
					}
					
					/* Check we have at least one non-static method (unless this class is abstract or has a parent) */
					elseif( !$reflection->isAbstract() and $reflection->getParentClass() === FALSE and !$hasNonStatic and $reflection->getNamespacename() !== 'IPS\Output\Plugin' and !in_array( 'extensions', $bits ) and !in_array( 'templateplugins', $bits ) )
					{
						trigger_error( "You do not have any methods in {$classname} which are not static. Please refactor. (PHP Coding Standards: Functions and Methods.6)", E_USER_ERROR );
					}
				}
			}
		}
										
		/* Monkey Patch */
		self::monkeyPatch( $namespace, $class, $inDevCode );
	}
	
	/**
	 * Monkey Patch
	 *
	 * @param	string	$namespace	The namespace
	 * @param	string	$finalClass	The final class name we want to be able to use (without namespace)
	 * @param	string	$extraCode	Any additonal code to import before the class is defined
	 * @return	null
	 */
	public static function monkeyPatch( $namespace, $finalClass, $extraCode = '' )
	{		
		$realClass = "_{$finalClass}";
		if( isset( self::$hooks[ "\\{$namespace}\\{$finalClass}" ] ) AND \IPS\RECOVERY_MODE === FALSE )
		{
			foreach ( self::$hooks[ "\\{$namespace}\\{$finalClass}" ] as $id => $data )
			{
				if ( file_exists( ROOT_PATH . '/' . $data['file'] ) )
				{
					$contents = "namespace {$namespace}; ". str_replace( '_HOOK_CLASS_', $realClass, file_get_contents( ROOT_PATH . '/' . $data['file'] ) );
					try
					{
						if( @eval( $contents ) !== FALSE )
						{
							$realClass = $data['class'];
						}
					}
					catch ( \ParseError $e )
					{
						/* Show the error if we have development mode enabled */
						if( \IPS\IN_DEV )
						{
							throw $e;
						}
					}
				}
			}
		}
		
		$reflection = new \ReflectionClass( "{$namespace}\\_{$finalClass}" );
		if( eval( "namespace {$namespace}; ". $extraCode . ( $reflection->isAbstract() ? 'abstract' : '' )." class {$finalClass} extends {$realClass} {}" ) === FALSE )
		{
			trigger_error( "There was an error initiating the class {$namespace}\\{$finalClass}.", E_USER_ERROR );
		}		
	}

	/**
	 * Error Handler
	 *
	 * @param	int		$errno		Error number
	 * @param	string	$errstr		Error message
	 * @param	string	$errfile	File
	 * @param	int		$errline	Line
	 * @param	array	$trace		Backtrace
	 * @return	void
	 */
	public static function errorHandler( $errno, $errstr, $errfile, $errline, $trace=NULL )
	{
		/* We don't care about these in production */
		if ( in_array( $errno, array( E_WARNING, E_NOTICE, E_STRICT, E_DEPRECATED ) ) )
		{
			return;
		}

		/* This means the error suppressor was used, so we should ignore any non-fatal errors */
		if ( error_reporting() === 0 )
		{
			return false;
		}
		
		throw new \ErrorException( $errstr, $errno, 0, $errfile, $errline );
	}
	
	/**
	 * Exception Handler
	 *
	 * @param	\Throwable	$exception	Exception class
	 * @return	void
	 */
	public static function exceptionHandler( $exception )
	{
		/* Should we show the exception message? */
		$showMessage = ( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation == 'admin' );
		if ( method_exists( $exception, 'isServerError' ) and $exception->isServerError() )
		{
			$showMessage = TRUE;
		}
		
		/* Work out what we'll log - exception classes can provide extra data */
		$log = '';
		if ( method_exists( $exception, 'extraLogData' ) )
		{
			$log .= $exception->extraLogData() . "\n";
		}
		$log .= get_class( $exception ) . ": " . $exception->getMessage() . " (" . $exception->getCode() . ")\n" . $exception->getTraceAsString();
		
		/* Log it */
		\IPS\Log::log( $log, 'uncaught_exception' );
		
		/* Report it */
		try
		{
			if ( !\IPS\IN_DEV and \IPS\Settings::i()->diagnostics_reporting and !\IPS\Settings::i()->theme_designers_mode and !self::exceptionWasThrownByThirdParty( $exception ) and ( !method_exists( $exception, 'isServerError' ) or !$exception->isServerError() ) )
			{
				self::reportExceptionToIPS( $exception );
			}
		}
		catch ( \Exception $e ) { }
		
		/* Try to display a friendly error page */
		try
		{
			/* If we couldn't connect to the database, don't bother trying to show the friendly page because nope */
			if( $exception instanceof \IPS\Db\Exception AND $exception->getCode() === 0 )
			{
				throw new \RuntimeException;
			}

			/* If we're in the installer/upgrader, show the raw message */
			$message = 'generic_error';
			if( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation == 'setup' )
			{
				$message = $exception->getMessage();
			}

			$faultyAppOrHookId = static::exceptionWasThrownByThirdParty( $exception );

			/* Output */
			\IPS\Output::i()->error( $message, "EX{$exception->getCode()}", 500, NULL, array(), $log, $faultyAppOrHookId );
		}
		/* And if *that* fails, show our generic page */
		catch ( \Exception $e )
		{
			static::genericExceptionPage( $showMessage ? $exception->getMessage() : NULL );
		}
		catch ( \Throwable $e )
		{
			static::genericExceptionPage( $showMessage ? $exception->getMessage() : NULL );
		}

		exit;
	}
		
	/**
	 * Should a given exception be reported to IPS? Filter out 3rd party etc.
	 *
	 * @param	\Throwable	$exception	The exception
	 * @return	void
	 */
	final public static function reportExceptionToIPS( $exception )
	{
		$response = \IPS\Http\Url::external('https://invisionpowerdiagnostics.com')->request()->post( array(
			'version'	=> \IPS\Application::getAvailableVersion('core'),
			'class'		=> get_class( $exception ),
			'message'	=> $exception->getMessage(),
			'code'		=> $exception->getCode(),
			'file'		=> str_replace( \IPS\ROOT_PATH, '', $exception->getFile() ),
			'line'		=> $exception->getLine(),
			'backtrace'	=> str_replace( \IPS\ROOT_PATH, '', $exception->getTraceAsString() )
		) );
		
		if ( $response->httpResponseCode == 410 )
		{
			\IPS\Settings::i()->changeValues( array( 'diagnostics_reporting' => 0 ) );
		}
	}

	/**
	 * Generic exception page
	 *
	 * @param	\Exception	$exception	Exception class
	 * @return	void
	 * @note	Abstracted so Theme can call this if templates are in the process of building
	 */
	public static function genericExceptionPage( $message = NULL )
	{
		if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
		{
			header( "HTTP/1.0 500 Internal Server Error" );
		}
		else
		{
			header( "HTTP/1.1 500 Internal Server Error" );
		}
					
		require \IPS\ROOT_PATH . '/' . \IPS\ERROR_PAGE;
		exit;
	}
	
	/**
	 * Small utility function to check if a class has a trait as PHP doesn't have an operator
	 * for this and the monkey patching means we can't use class_uses() directly
	 *
	 * @param	string|object	$class 	The class
	 * @param	string			$trait	Trait name to look for
	 * @return	bool
	 */
	public static function classUsesTrait( $class, $trait )
	{
	    do 
	    { 
		    if ( in_array( $trait, class_uses( $class ) ) )
		    {
			    return TRUE;
		    }
	    }
	    while( $class = get_parent_class( $class ) );
	    
	    return FALSE;
	} 
	
	/**
	 * Get license key data
	 *
	 * @param	bool	$forceRefresh	If TRUE, will get data from server
	 * @return	array|NULL
	 */
	public static function licenseKey( $forceRefresh = FALSE )
	{
		/* Get the cached value */
		$cached = NULL;
		$setFetched = FALSE;
		if ( isset( \IPS\Data\Store::i()->license_data ) )
		{
			$cached = \IPS\Data\Store::i()->license_data;

			/* If it's younger than 21 days, just use that */
			if ( $cached['fetched'] > ( time() - 1814400 ) and !$forceRefresh )
			{
				/* If the license is not expired, return the data */
				if( !$cached['data']['expires'] OR strtotime( $cached['data']['expires'] ) > time() )
				{
					return $cached['data'];
				}
				/* Otherwise if the license is expired but we've automatically refetched, return the data */
				else if( $cached['data']['expires'] AND strtotime( $cached['data']['expires'] ) < time() AND isset( $cached['refetched'] ) )
				{
					return $cached['data'];
				}
				/* Otherwise remember to set the 'refetched' flag */
				else
				{
					$setFetched = TRUE;
				}
			}
		}

		/* Call the main server */
		try
		{
			/* Prevent a race condition and set the next check cycle to be 10 mins from the 21 day cut off in case this request fails */
			\IPS\Data\Store::i()->license_data	= array( 'fetched' => time() - 1813800, 'data' => NULL );
			
			$response = \IPS\Http\Url::ips( 'license/' . trim( \IPS\Settings::i()->ipb_reg_number ) )->request()->get();
			if ( $response->httpResponseCode == 404 )
			{
				\IPS\Data\Store::i()->license_data	= array( 'fetched' => time() - 1728000, 'data' => NULL );
				return $cached;
			}
			$response = $response->decodeJson();
		}
		catch ( \Exception $e )
		{
			/* If we can't access the license server right now, store something in cache to prevent a request on every page load. We
				set fetched to 20 days ago so that this cache is only good for 1 day instead of 21 days however. */
			if( $cached === NULL )
			{
				\IPS\Data\Store::i()->license_data	= array( 'fetched' => time() - 1728000, 'data' => NULL );
			}
			else
			{
				/* We wipe the data to prevent a race condition, but the license server failed so restore the data and set to try again in 1 day */
				\IPS\Data\Store::i()->license_data	= array( 'fetched' => time() - 1728000, 'data' => ( isset( $cached['data'] ) ? $cached['data'] : NULL ) );
			}

			/* If the server is offline right now, use the cached value from above */
			return $cached;
		}
		
		/* Update the license info in the store */
		$licenseData = array( 'fetched' => time(), 'data' => $response );

		if( $setFetched )
		{
			$licenseData['refetched']	= 1;
		}

		\IPS\Data\Store::i()->license_data	= $licenseData;

		/* Return */
		return $response;
	}
	
	/**
	 * Check license key
	 *
	 * @param	string	The license key
	 * @param	string	The site URL
	 * @return	void
	 * @throws	\DomainException
	 */
	public static function checkLicenseKey( $val, $url )
	{
		$test = FALSE;
		if ( mb_substr( $val, -12 ) === '-TESTINSTALL' )
		{
			$test = TRUE;
			$val = mb_substr( $val, 0, -12 );
		}
		$urlKey = $test ? 'test_url' : 'url';
						
		try
		{
			$response = \IPS\Http\Url::ips( 'license/' . $val )->setQueryString( $urlKey, $url )->request()->get();			
			switch ( $response->httpResponseCode )
			{
				case 200:
					$response = json_decode( $response, TRUE );
					if ( $response['legacy'] )
					{
						throw new \DomainException( 'license_key_legacy' );
					}
					
					if ( !$response[ $urlKey ] )
					{
						\IPS\Http\Url::ips( 'license/' . $val )->request()->post( array(
							$urlKey	=> $url
						) );
					}
					elseif ( $response[ $urlKey ] != $url )
					{
						if ( rtrim( preg_replace( '/^https?:\/\//', '', $response[ $urlKey ] ), '/' ) == rtrim( preg_replace( '/^https?:\/\//', '', $url ), '/' ) ) // Allow changing if the difference is http/https or just a trailing slash
						{
							\IPS\Http\Url::ips( 'license/' . $val )->request()->post( array(
								$urlKey	=> $url
							) );
						}
						else
						{							
							throw new \DomainException( $test ? 'license_key_test_active' : 'license_key_active' );
						}
					}
					break;
					
				case 404:
					throw new \DomainException( 'license_key_not_found' );
				
				default:
					throw new \DomainException( 'license_generic_error' );
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \DomainException( sprintf( \IPS\Member::loggedIn()->language()->get( 'license_server_error' ), $e->getMessage() ) );
		}
	}

	/**
	 * Was the exception thrown by a third party app/plugin?
	 *
	 * @param	\Throwable			$exception	The exception
	 * @return	string|int|NULL		string = application directory, int = hook id, null means that it was probably caused by an application
	 */
	public static function exceptionWasThrownByThirdParty( $exception )
	{		
		$trace = $exception->getTraceAsString();
		
		/* Did it happen in a hook? */
		if ( preg_match( '/init\.php\(\d*\) : eval\(\)\'d code$/', $exception->getFile() ) )
		{
			/* Did it happen inside a plugin hook? */
			if ( preg_match( '/hook(\d+)/', $trace, $matches ) )
			{
				/* Return the hook id, the error method will fetch the plugin name */
				return $matches[1];
			}
			/* Did it happen inside an applications hook? */
			else if ( preg_match_all( '/([a-zA-Z]+)_hook/', $trace, $matches ) )
			{
				foreach ( $matches[1] as $appKey )
				{
					if ( !in_array( $appKey, \IPS\Application::$ipsApps ) )
					{
						return $appKey;
					}
				}
			}

			return NULL;
		}
		/* Exception was thrown by 'normal code', check if it's from a third-party app */
		else
		{
			foreach ( explode( "\n", $trace ) as $line )
			{
				if ( preg_match( '/' . preg_quote( DIRECTORY_SEPARATOR, '/' ) . 'applications' . preg_quote( DIRECTORY_SEPARATOR, '/' ) . '([a-zA-Z]+)/', str_replace( \IPS\ROOT_PATH, '', $line ), $matches ) )
				{
					if ( !in_array( $matches[1], \IPS\Application::$ipsApps ) )
					{
						return $matches[1];
					}
					else
					{
						return NULL;
					}
				}
			}
		}

		/* Still here? Probably system */
		return NULL;
	}

	/**
	 * Resync IPS Cloud File System
	 * Must be called when writing any files to disk on IPS Community in the Cloud
	 *
	 * @param	string	$reason	Reason
	 * @return	void
	 */
	public static function resyncIPSCloud( $reason = NULL )
	{
		if ( \IPS\CIC )
		{
			if ( preg_match( '/^\/var\/www\/html\/(.+?)(?:\/|$)/i', \IPS\ROOT_PATH, $matches ) )
			{
				try
				{
					\IPS\Http\Url::external('http://ips-cic-fileupdate.invisioncic.com/')
						->setQueryString( array( 'user' => $matches[1], 'reason' => $reason ) )
						->request()
						->post();
				}
				catch ( \Exception $e ) { }
			}
		}
	}
	
	/**
	 * CiC Tracking ID
	 *
	 * @return		NULL
	 * @deprecated	4.3
	 */
	public static function cicTrackingId()
	{
		return NULL;
	}
}

/* Init */
IPS::init();