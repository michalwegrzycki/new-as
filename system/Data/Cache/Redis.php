<?php
/**
 * @brief		Redis Cache Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Oct 2013
 */

namespace IPS\Data\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redis Cache Class
 */
class _Redis extends \IPS\Data\Cache
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return class_exists('Redis');
	}
	
	/**
	 * Configuration
	 *
	 * @param	array	$configuration	Existing settings
	 * @return	array	\IPS\Helpers\Form\FormAbstract elements
	 */
	public static function configuration( $configuration )
	{
		return array(
			'server'	=> new \IPS\Helpers\Form\Text( 'server_host', isset( $configuration['server'] ) ? $configuration['server'] : '', FALSE, array( 'placeholder' => '127.0.0.1' ), function( $val )
			{
				if ( \IPS\Request::i()->cache_method === 'Redis' and empty( $val ) )
				{
					throw new \DomainException( 'datastore_redis_servers_err' );
				}
			} ),
			'port'		=> new \IPS\Helpers\Form\Number( 'server_port', isset( $configuration['port'] ) ? $configuration['port'] : NULL, FALSE, array( 'placeholder' => '6379' ), function( $val )
			{
				if ( \IPS\Request::i()->cache_method === 'Redis' AND $val AND ( $val < 0 OR $val > 65535 ) )
				{
					throw new \DomainException( 'datastore_redis_servers_err' );
				}
			} ),
			'password'	=> new \IPS\Helpers\Form\Password( 'server_password', isset( $configuration['password'] ) ? $configuration['password'] : '', FALSE ),
		);
	}

	/**
	 * @brief	Connection resource
	 */
	protected static $links	= array();

	/**
	 * @brief	Connection key
	 */
	protected $connectionKey	= NULL;

	/**
	 * @brief	Connection timeout - keep low or you negate the benefits of caching
	 */
	protected $timeout	= 2;

	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Configuration
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		/* Figure out our connection key, as you could theoretically attempt to connect to more than one Redis server */
		$this->connectionKey	= md5( $configuration['server'] . ':' . $configuration['port'] );

		/* If we've already attempted to establish this link, just return now */
		if( isset( static::$links[ $this->connectionKey ] ) )
		{
			return;
		}

		/* Connect to server */
		try
		{
			static::$links[ $this->connectionKey ]	= new \Redis;

			if( static::$links[ $this->connectionKey ]->connect( $configuration['server'], $configuration['port'], $this->timeout ) === FALSE )
			{
				$this->resetConnection();

				throw new \RedisException;
			}
			else
			{
				if( isset( $configuration['password'] ) AND $configuration['password'] )
				{
					if( static::$links[ $this->connectionKey ]->auth( $configuration['password'] ) === FALSE )
					{
						$this->resetConnection();

						throw new \RedisException;
					}
				}
			}

			if( static::$links[ $this->connectionKey ] !== NULL )
			{
				static::$links[ $this->connectionKey ]->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP );
			}

			/* If connection times out, connect can return TRUE and we won't know until our next attempt to talk to the server,
				so we should ping now to verify we were able to connect successfully */
			static::$links[ $this->connectionKey ]->ping();

			register_shutdown_function( function( $object ){
				try
				{
					if( isset( static::$links[ $object->connectionKey ] ) AND static::$links[ $object->connectionKey ] )
					{
						static::$links[ $object->connectionKey ]->close();
						unset( static::$links[ $object->connectionKey ] );
					}
				}
				catch( \RedisException $e ){}
			}, $this );

			\IPS\Log::debug( "Connected to Redis", 'cache' );
		}
		catch( \RedisException $e )
		{
			$this->resetConnection( $e );
			\IPS\Log::debug( "Connection to Redis failed", 'cache' );
		}
	}
	
	/**
	 * Needs cache key check with this storage engine to maintain integrity
	 *
	 * @return boolean
	 */
	public function checkKeys()
	{
		/* We do not need to check keys with redis because redis is amazing <3 */
		return false;
	}
	
	/**
	 * Redis key
	 */
	protected $_redisKey;
		
	/**
	 * Get random string used in the keys to identify this site compared to other sites
	 *
	 * @param   string          $key
	 * @return  string|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	protected function _getRedisKey()
	{
		if ( !$this->_redisKey )
		{
			if ( !( $this->_redisKey = static::$links[ $this->connectionKey ]->get( \IPS\SUITE_UNIQUE_KEY . '_redisKey' ) ) )
			{
				$this->_redisKey = md5( uniqid() );
				static::$links[ $this->connectionKey ]->setex( \IPS\SUITE_UNIQUE_KEY . '_redisKey', 604800, $this->_redisKey );
			}
		}
		
		return $this->_redisKey;
	}

	/**
	 * Abstract Method: Get
	 *
	 * @param   string          $key
	 * @return  string|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	protected function get( $key )
	{
		if( array_key_exists( $key, $this->cache ) )
		{
			\IPS\Log::debug( "Get {$key} from Redis (already loaded)", 'cache' );
			return $this->cache[ $key ];
		}

		if ( static::$links[ $this->connectionKey ] )
		{
			\IPS\Log::debug( "Get {$key} from Redis", 'cache' );

			try
			{
				$this->cache[ $key ]	= static::$links[ $this->connectionKey ]->get( \IPS\SUITE_UNIQUE_KEY . '_' . $this->_getRedisKey() . '_' . $key );

				return $this->cache[ $key ];
			}
			catch( \RedisException $e )
			{
				$this->resetConnection( $e );

				return FALSE;
			}
		}

		/* No connection */
		return FALSE;
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string			$key	Key
	 * @param	string			$value	Value
	 * @param	\IPS\DateTime	$expire	Expreation time, or NULL for no expiration
	 * @return	bool
	 */
	protected function set( $key, $value, \IPS\DateTime $expire = NULL )
	{
		if ( static::$links[ $this->connectionKey ] )
		{
			\IPS\Log::debug( "Set {$key} in Redis", 'cache' );

			try
			{
				if ( $expire )
				{
					return (bool) static::$links[ $this->connectionKey ]->setex( \IPS\SUITE_UNIQUE_KEY . '_' . $this->_getRedisKey() . '_' . $key, $expire->getTimestamp() - time(), $value );
				}
				else
				{
					/* Set for 24 hours */
					return (bool) static::$links[ $this->connectionKey ]->setex( \IPS\SUITE_UNIQUE_KEY . '_' . $this->_getRedisKey() . '_' . $key, 86400, $value );
				}
			}
			catch( \RedisException $e )
			{
				$this->resetConnection( $e );

				return FALSE;
			}
		}

		/* No connection */
		return FALSE;
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function exists( $key )
	{
		if( array_key_exists( $key, $this->cache ) )
		{
			\IPS\Log::debug( "Check exists {$key} from Redis (already loaded)", 'cache' );
			return ( $this->cache[ $key ] === FALSE ) ? FALSE : TRUE;
		}

		\IPS\Log::debug( "Check exists {$key} from Redis", 'cache' );

		/* We do a get instead of an exists() check because it will cause the cache value to be fetched and cached inline, saving another call to the server */
		return ( $this->get( $key ) === FALSE ) ? FALSE : TRUE;
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function delete( $key )
	{		
		if ( static::$links[ $this->connectionKey ] )
		{
			\IPS\Log::debug( "Delete {$key} from Redis", 'cache' );

			try
			{
				return (bool) static::$links[ $this->connectionKey ]->delete( \IPS\SUITE_UNIQUE_KEY . '_' . $this->_getRedisKey() . '_' . $key );
			}
			catch( \RedisException $e )
			{
				$this->resetConnection( $e );
			}
		}

		/* No connection */
		return FALSE;
	}

	/**
	 * Reset connection
	 *
	 * @param	\RedisException|NULL	If this was called as a result of an exception, log that to the debug log
	 * @return void
	 */
	protected function resetConnection( \RedisException $e = NULL )
	{
		if ( $e !== NULL )
		{
			\IPS\Log::debug( $e, 'redis_exception' );
		}
		static::$links[ $this->connectionKey ]	= NULL;
	}

	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		parent::clearAll();
		
		if ( static::$links[ $this->connectionKey ] )
		{
			$this->_redisKey = md5( uniqid() );
			
			static::$links[ $this->connectionKey ]->setex( \IPS\SUITE_UNIQUE_KEY . '_redisKey', 604800, $this->_redisKey );
			return;
		}
		
		/* No connection */
		\IPS\Log::debug( "clearAll called with invalid Redis connection", 'cache' );
		return;
	}
	
	/**
	 * Log a page hit
	 *
	 * @param	\IPS\Http\Url|NULL	$url		URL to log (or null)
	 * @return void
	 */
	public function logPageHit( $url=NULL )
	{
		$url = $url ? $url : \IPS\Request::i()->url();
		$key = 'counter-member';
		
		if ( ! \IPS\Member::loggedIn()->member_id )
		{
			$key = \IPS\Session::i()->userAgent->spider ? 'counter-spider' : 'counter-guest';
		}
		
		
		$value = static::$links[ $this->connectionKey ]->hIncrBy( \IPS\SUITE_UNIQUE_KEY . '_' . $key, ( \IPS\Session::i()->userAgent->spider ? '/' : ( $url->getFurlQuery() ?: '/' ) ), 1 );
		
		static::$links[ $this->connectionKey ]->expire( \IPS\SUITE_UNIQUE_KEY . '_' . $key, 604800 );
	}
}