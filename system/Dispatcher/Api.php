<?php
/**
 * @brief		API Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Dec 2015
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	API Dispatcher
 */
class _Api extends \IPS\Dispatcher
{
	/**
	 * @brief Controller Location
	 */
	public $controllerLocation = 'api';
	
	/**
	 * Init
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function init()
	{
		
	}
	
	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		/* Init */
		$shouldLog = FALSE;
		$isBadKey = FALSE;
		$output = NULL;
		$httpResponseCode = 500;
		
		/* Decode URL */
		if ( \IPS\Settings::i()->use_friendly_urls and \IPS\Settings::i()->htaccess_mod_rewrite )
		{
			/* We are using Mod Rewrite URL's, so look in the path */
			$path = mb_substr( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_PATH ], mb_strpos( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_PATH ], '/api/' ) + 5 );
			
			/* nginx won't convert the 'fake' query string to $_GET params, so do this now */
			if ( ! empty( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ] ) )
			{
				parse_str( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ], $params );
				foreach ( $params as $k => $v )
				{
					if ( ! isset( \IPS\Request::i()->$k ) )
					{
						\IPS\Request::i()->$k = $v;
					}
				}
			}
		}
		else
		{
			/* Otherwise we are not, so we need the query string instead, which is actually easier */
			$path = \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ];

			/* However, if we passed any actual query string arguments, we need to strip those */
			if( mb_strpos( $path, '&' ) )
			{
				$path = mb_substr( $path, 0, mb_strpos( $path, '&' ) );
			}
		}
		$pathBits = array_filter( explode( '/', $path ) );
						
		/* Run */
		try
		{
			/* IP is banned? */
			if ( \IPS\Request::i()->ipAddressIsBanned() )
			{
				throw new \IPS\Api\Exception( 'IP_ADDRESS_BANNED', '1S290/A', 403 );
			}
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_api_logs', array( 'ip_address=? AND is_bad_key=1', \IPS\Request::i()->ipAddress() ) )->first() > 10 )
			{
				\IPS\Db::i()->insert( 'core_banfilters', array(
					'ban_type'		=> 'ip',
					'ban_content'	=> \IPS\Request::i()->ipAddress(),
					'ban_date'		=> time(),
					'ban_reason'	=> 'API',
				) );
				unset( \IPS\Data\Store::i()->bannedIpAddresses );
				throw new \IPS\Api\Exception( 'IP_ADDRESS_BANNED', '1S290/A', 403 );
			}
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_api_logs', array( 'ip_address=? AND is_bad_key=1 AND date>?', \IPS\Request::i()->ipAddress(), \IPS\DateTime::create()->sub( new \DateInterval( 'PT5M' ) )->getTimestamp() ) )->first() > 1 )
			{
				throw new \IPS\Api\Exception( 'TOO_MANY_REQUESTS_WITH_BAD_KEY', '1S290/A', 429 );
			}
			
			/* Work out API key */
			$rawApiKey = NULL;
			
			if ( isset( $_SERVER['PHP_AUTH_USER'] ) )
			{
				$rawApiKey = $_SERVER['PHP_AUTH_USER'];
			}
			else
			{
				foreach ( $_SERVER as $k => $v )
				{
					if ( mb_substr( $k, -18 ) == 'HTTP_AUTHORIZATION' )
					{
						$exploded = explode( ':', base64_decode( mb_substr( $v, 6 ) ) );
						if ( isset( $exploded[0] ) )
						{
							$rawApiKey = $exploded[0];
						}
					}
				}
			}
			
			/* Fall back to URL key if set */
			if ( ! $rawApiKey and isset( \IPS\Request::i()->key ) )
			{
				$rawApiKey = \IPS\Request::i()->key;
			}
			
			/* Get API key */
			if ( !isset( $rawApiKey ) )
			{
				throw new \IPS\Api\Exception( 'NO_API_KEY', '2S290/6', 401 );
			}
			try
			{
				$apiKey = \IPS\Api\Key::load( $rawApiKey );
				
				if ( $rawApiKey != 'test' and $apiKey->allowed_ips and !in_array( \IPS\Request::i()->ipAddress(), explode( ',', $apiKey->allowed_ips ) ) )
				{
					$shouldLog = TRUE;
					throw new \IPS\Api\Exception( 'IP_ADDRESS_NOT_ALLOWED', '2S290/8', 403 );
				}
				
				if ( $rawApiKey != 'test' and ! \IPS\Request::i()->isCgi() and isset( \IPS\Request::i()->key ) )
				{
					$shouldLog = TRUE;
					throw new \IPS\Api\Exception( 'CANNOT_USE_KEY_AS_URL_PARAM', '2S290/B', 403 );
				}
			}
			catch ( \OutOfRangeException $e )
			{
				if ( $rawApiKey != 'test' )
				{
					$shouldLog = TRUE;
					$isBadKey = TRUE;
				}
				throw new \IPS\Api\Exception( 'INVALID_API_KEY', '3S290/7', 401 );
			}
			
			/* Get language */
			try
			{
				if ( isset( $_SERVER['HTTP_X_IPS_LANGUAGE'] ) )
				{
					$language = \IPS\Lang::load( intval( $_SERVER['HTTP_X_IPS_LANGUAGE'] ) );
				}
				else
				{
					$language = \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
				}
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'INVALID_LANGUAGE', '2S290/9', 400 );
			}
			
			/* Work out the app and controller. Both can only be alphanumeric - prevents include injections */
			$app = array_shift( $pathBits );
			if ( !preg_match( '/^[a-z0-9]+$/', $app ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_APP', '3S290/3', 400 );
			}
			$controller = array_shift( $pathBits );
			if ( !preg_match( '/^[a-z0-9]+$/', $controller ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_CONTROLLER', '3S290/4', 400 );
			}
			
			/* Load the app */
			try
			{
				$app = \IPS\Application::load( $app );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'INVALID_APP', '2S290/1', 404 );
			}
				
			/* Check it's enabled */
			if ( !$app->enabled )
			{
				throw new \IPS\Api\Exception( 'APP_DISABLED', '1S290/2', 503 );
			}
			
			/* Get the controller */
			$class = 'IPS\\' . $app->directory . '\\api\\' . $controller;
			if ( !class_exists( $class ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_CONTROLLER', '2S290/5', 404 );
			}
			
			/* Run it */
			$controller = new $class( $apiKey );
			$response = $controller->execute( $pathBits, $shouldLog );
							
			/* Output */
			$output = json_encode( $response->getOutput(), JSON_PRETTY_PRINT );
			$language->parseOutputForDisplay( $output );
			$httpResponseCode = $response->httpCode;
			
		}
		catch ( \IPS\Api\Exception $e )
		{
			if ( $shouldLog )
			{
				\IPS\Log::log( $e, 'api' );
			}

			$output = json_encode( array( 'errorCode' => $e->exceptionCode, 'errorMessage' => $e->getMessage() ), JSON_PRETTY_PRINT );
			$httpResponseCode = $e->getCode();
		}
		catch ( \Exception $e )
		{
			if ( $shouldLog )
			{
				\IPS\Log::log( $e, 'api' );
			}

			$output = json_encode( array( 'errorCode' => 'EX' . $e->getCode(), 'errorMessage' => \IPS\IN_DEV ? $e->getMessage() : 'UNKNOWN_ERROR' ), JSON_PRETTY_PRINT );
			$httpResponseCode = 500;
		}
		
		/* Log? */
		if ( $shouldLog )
		{
			/* Database logging errors should not prevent the output being sent */
			try
			{
				\IPS\Db::i()->insert( 'core_api_logs', array(
					'endpoint'			=> $path,
					'method'			=> $_SERVER['REQUEST_METHOD'],
					'api_key'			=> $rawApiKey,
					'ip_address'		=> \IPS\Request::i()->ipAddress(),
					'request_data'		=> json_encode( $_REQUEST, JSON_PRETTY_PRINT ),
					'response_code'		=> $httpResponseCode,
					'response_output'	=> $output,
					'date'				=> time(),
					'is_bad_key'		=> $isBadKey
				) );
			}
			catch ( \IPS\Db\Exception $e ) {}
		}
		
		/* Output */
		\IPS\Output::i()->sendOutput( $output, $httpResponseCode, 'application/json' );
	}

	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
	}
}