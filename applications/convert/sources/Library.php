<?php

/**
 * @brief		Converter Library Master Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 */

namespace IPS\convert;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

abstract class _Library
{
	/**
	 * @brief	Flag to indicate that we are using a specific key, and should do WHERE static::$currentKeyName > static::$currentKeyValue rather than LIMIT 0,1000
	 */
	public static $usingKeys			= FALSE;
	
	/**
	 * @brief	The name of the current key in the database for this step
	 */
	public static $currentKeyName	= NULL;
	
	/**
	 * @brief	The current value of the key
	 */
	public static $currentKeyValue	= 0;
	
	/**
	 * @brief	Amount of data being processed per cycle.
	 */
	public static $perCycle				= 2000;
	
	/**
	 * @brief	If not using keys, the current start value for LIMIT clause.
	 */
	public static $startValue		= 0;
	
	/**
	 * @brief	The current conversion step
	 */
	public static $action			= NULL;
	
	/**
	 * @brief	\IPS\convert\Software instance for the software we are converting from
	 */
	public $software					= NULL;

	/**
	 * @brief	Obscure filenames?
	 *
	 * This is only referenced in certain content types that may be referenced in already parsed data such as attachments and emoticons
	 * - Designed for use with the Invision Community converter.
	 */
	public static $obscureFilenames		= TRUE;
	
	/**
	 * @brief	Array of field types
	 */
	protected static $fieldTypes = array( 'Address', 'Checkbox', 'CheckboxSet', 'Codemirror', 'Color', 'Date', 'Editor', 'Email', 'Member', 'Number', 'Password', 'Poll', 'Radio', 'Rating', 'Select', 'Tel', 'Text', 'TextArea', 'Upload', 'Url', 'YesNo' );
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\Software	$software	Software Instance we are converting from
	 * @return	void
	 */
	public function __construct( \IPS\convert\Software $software )
	{
		$this->software = $software;
	}
	
	/**
	 * When called at the start of a conversion step, indicates that we are using a specific key for WHERE clauses which nets performance improvements
	 *
	 * @param	string	$key	The key to use
	 * @return	void
	 */
	public static function setKey( $key )
	{
		static::$usingKeys		= TRUE;
		static::$currentKeyName	= $key;
	}
	
	/**
	 * When using a key reference, sets the current value of that key for the WHERE clause
	 *
	 * @param	mixed	$value	The current value
	 * @return	void
	 */
	public function setLastKeyValue( $value )
	{
		$_SESSION['currentKeyValue'] = $value;
		static::$currentKeyValue = $value;
	}
	
	/**
	 * Processes a conversion cycle.
	 *
	 * @param	integer									$data	Data from the previous step.
	 * @param	\IPS\convert\App	$app	Application Class for the current conversion
	 * @return	array|NULL	Data for the MultipleRedirect
	 */
	public function process( $data, $method, $perCycle=NULL )
	{
		if ( !is_null( $perCycle ) )
		{
			static::$perCycle = $perCycle;
		}
		
		/* temp */
		$classname			= get_class( $this->software );
		$canConvert			= $classname::canConvert();

		if( $canConvert === NULL )
		{
			return NULL;
		}

		static::$action		= $canConvert[ $method ]['table'];
		static::$startValue	= $data;
		
		if ( !isset( $_SESSION['convertCountRows'] ) )
		{
			$_SESSION['convertCountRows'] = array();
		}
		
		if ( !isset( $_SESSION['convertCountRows'][ $method ] ) )
		{
			$_SESSION['convertCountRows'][ $method ] = $this->software->countRows( static::$action, $canConvert[ $method ]['where'], true );
		}
		
		$total = $_SESSION['convertCountRows'][ $method ];
		
		if ( $data >= $total )
		{
			$completed	= $this->software->app->_session['completed'];
			$more_info	= $this->software->app->_session['more_info'];
			if ( !in_array( $method, $completed ) )
			{
				$completed[] = $method;
			}
			$this->software->app->_session = array( 'working' => array(), 'more_info' => $more_info, 'completed' => $completed );
			unset( $_SESSION['currentKeyValue'] );
			unset( $_SESSION['convertCountRows'][ $method ] );
			unset( $_SESSION['convertContinue'] );
			return NULL;
		}
		else
		{
			/* Are we continuing? Let's figure out where we are... unfortunately, this only works when we are using keys (which is 99% of the time) */
			if ( isset( $_SESSION['convertContinue'] ) AND $_SESSION['convertContinue'] === TRUE )
			{
				$table = 'convert_link';

				switch( $method )
				{
					case 'convertForumsPosts':
						$table = 'convert_link_posts';
						break;
					
					case 'convertForumsTopics':
						$table = 'convert_link_topics';
						break;
					
					case 'convertPrivateMessages':
						$table = 'convert_link_pms';
						break;
				}
				
				/* Select our max foreign ID */
				$type = $this->getMethodFromMenuRows( $method )['link_type'];

				/* @todo If we ever support converting more than one CMS database, we'll need to change this */
				if( is_array( $type ) )
				{
					$type = $type[0];
				}

				$lastId = \IPS\Db::i()->select( 'MAX(CAST( foreign_id AS UNSIGNED) )', $table, array( "app=? AND type=?", $this->software->app->app_id, $type ) )->first();

				/* Set the data count for MR */
				$data = \IPS\Db::i()->select( 'COUNT(*)', $table, array( "app=? AND type=?", $this->software->app->app_id, $type ) )->first();

				/* Set as last key value */
				$this->setLastKeyValue( $lastId );

				/* Unset this so we don't do this again. */
				unset( $_SESSION['convertContinue'] );
			}
			
			/* Fetch data from the software */
			try
			{
				$this->software->$method();
			}
			catch( \IPS\convert\Software\Exception $e )
			{
				/* A Software Exception indicates we are done */
				$completed	= $this->software->app->_session['completed'];
				$more_info	= $this->software->app->_session['more_info'];
				if ( !in_array( $method, $completed ) )
				{
					$completed[] = $method;
				}
				$this->software->app->_session = array( 'working' => array(), 'more_info' => $more_info, 'completed' => $completed );
				unset( $_SESSION['currentKeyValue'] );
				unset( $_SESSION['convertContinue'] );
				return NULL;
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'converters' );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING );
				throw new \IPS\convert\Exception;
			}
			catch( \ErrorException $e )
			{
				\IPS\Log::log( $e, 'converters' );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING );
				throw new \IPS\convert\Exception;
			}
			
			$this->software->app->_session = array_merge( $this->software->app->_session, array( 'working' => array( $method => $data + static::$perCycle ) ) );
			return array( $data + static::$perCycle, sprintf( \IPS\Member::loggedIn()->language()->get( 'converted_x_of_x' ), ( $data + static::$perCycle > $total ) ? $total : $data + static::$perCycle, \IPS\Member::loggedIn()->language()->addToStack( $this->getMethodFromMenuRows( $method )['step_title'] ), $total ), 100 / $total * ( $data + static::$perCycle ) );
		}
	}
	
	/**
	 * Empty Conversion Data
	 *
	 * @param	integer									$data	Data from the previous step.
	 * @param	\IPS\convert\App	$app	Application Class for the current conversion
	 * @return	array|NULL	Data for the MultipleRedirect
	 */
	public function emptyData( $data, $method )
	{
		$perCycle = 500;
		
		/* temp */
		$classname			= get_class( $this->software );
		$canConvert			= $this->menuRows();
		
		if ( !isset( $canConvert[ $method ]['link_type'] ) )
		{
			return NULL;
		}
		
		$type = $canConvert[ $method ]['link_type'];
		
		if ( !isset( $_SESSION['emptyConvertedDataCount'] ) )
		{
			$count = 0;
			/* Just one type? */
			if ( !is_array( $type ) )
			{
				foreach( array( 'convert_link', 'convert_link_pms', 'convert_link_posts', 'convert_link_topics' ) as $table )
				{
					$count += \IPS\Db::i()->select( 'COUNT(*)', $table, array( "type=? AND app=?", $type, $this->software->app->app_id ) )->first();
				}
			}
			else
			{
				foreach( $type as $t )
				{
					foreach( array( 'convert_link', 'convert_link_pms', 'convert_link_posts', 'convert_link_topics' ) as $table )
					{
						$count += \IPS\Db::i()->select( 'COUNT(*)', $table, array( "type=? AND app=?", $t, $this->software->app->app_id ) )->first();
					}
				}
			}
			
			$_SESSION['emptyConvertedDataCount'] = $count;
		}
		
		if ( $data >= $_SESSION['emptyConvertedDataCount'] )
		{
			unset( $_SESSION['emptyConvertedDataCount'] );
			return NULL;
		}
		else
		{
			/* Fetch data from the software */
			try
			{
				/* If we're dealing with more than one type, then we can just delete from any one at random until we're done */
				if ( is_array( $type ) )
				{
					$type = array_rand( $type );
				}
				
				switch( $type )
				{
					case 'forums_topics':
						$table = 'convert_link_topics';
						break;
					
					case 'forums_posts':
						$table = 'convert_link_posts';
						break;
					
					case 'core_message_topics':
					case 'core_message_posts':
					case 'core_message_topic_user_map':
						$table = 'convert_link_pms';
						break;
					
					default:
						$table = 'convert_link';
						break;
				}
				
				$total	= (int) \IPS\Db::i()->select( 'COUNT(*)', $table, array( "type=? AND app=?", $type, $this->software->app->app_id ) )->first();
				$rows	= iterator_to_array( \IPS\Db::i()->select( 'link_id, ipb_id', $table, array( "type=? AND app=?", $type, $this->software->app->app_id ), "link_id ASC", array( 0, $perCycle ) )->setKeyField( 'link_id' )->setValueField( 'ipb_id' ) );
				$def	= \IPS\Db::i()->getTableDefinition( $type );
				
				if ( isset( $def['indexes']['PRIMARY']['columns'] ) )
				{
					$id = array_pop( $def['indexes']['PRIMARY']['columns'] );
				}
				
				\IPS\Db::i()->delete( $type, array( \IPS\Db::i()->in( $id, array_values( $rows ) ) ) );
				\IPS\Db::i()->delete( $table, array( \IPS\Db::i()->in( 'link_id', array_keys( $rows ) ) ) );
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'converters' );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING );
				throw new \IPS\convert\Exception;
			}
			catch( \ErrorException $e )
			{
				\IPS\Log::log( $e, 'converters' );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_ERROR );
				throw new \IPS\convert\Exception;
			}
			
			return array( $data + $perCycle, sprintf( \IPS\Member::loggedIn()->language()->get( 'removed_x_of_x' ), ( $data + $perCycle > $total ) ? $_SESSION['emptyConvertedDataCount'] : $data + $perCycle, \IPS\Member::loggedIn()->language()->addToStack( $method ), $_SESSION['emptyConvertedDataCount'] ), 100 / $_SESSION['emptyConvertedDataCount'] * ( $data + $perCycle ) );
		}
	}
	
	/**
	 * Truncates data from local database
	 *
	 * @param	string	Convert method to run truncate call for.
	 * @return	void
	 */
	public function emptyLocalData( $method )
	{
		$truncate = $this->truncate( $method );
		foreach( $truncate as $table => $where )
		{
			/* Kind of a hacky way to make sure we truncate the right forums archive table */
			if ( $table === 'forums_archive_posts' )
			{
				\IPS\forums\Topic\ArchivedPost::db()->delete( $table, $where );
			}
			else
			{
				\IPS\Db::i()->delete( $table, $where );
			}
			
			/* Remove links... we don't really care about which table they are in right now */
			foreach( array( 'convert_link', 'convert_link_pms', 'convert_link_posts', 'convert_link_topics' ) as $linkTable )
			{
				\IPS\Db::i()->delete( $linkTable, array( "type=? AND app=?", $table, $this->software->app->app_id ) );
			}
		}
		unset( $_SESSION['currentKeyValue'] );
	}

	/**
	 * @brief	Cached convertable items
	 */
	protected $convertable	= NULL;

	/**
	 * Return the items we can convert. Removes things that are empty.
	 *
	 * @return	array
	 */
	public function getConvertableItems()
	{
		if( $this->convertable !== NULL )
		{
			return $this->convertable;
		}

		$classname			= get_class( $this->software );
		$this->convertable	= $classname::canConvert();

		if( $this->convertable === NULL )
		{
			$this->convertable = array();
		}

		foreach( $this->convertable as $k => $v )
		{
			if( $this->software->countRows( $v['table'], $v['where'] ) == 0 )
			{
				unset( $this->convertable[ $k ] );
			}
		}

		return $this->convertable;
	}
	
	/**
	 * Magic __call() method
	 *
	 * @param	string	$name			The method to call without convert prefix.
	 * @param	mixed	$arguements		Arguments to pass to the method
	 * @return 	mixed
	 */
	public function __call( $name, $arguments )
	{
		if ( method_exists( $this, 'convert' . $name ) )
		{
			return call_user_func( array( $this, 'convert' . $name ), $arguments );
		}
		elseif ( method_exists( $this, $name ) )
		{
			return call_user_func( array( $this, $name ), $arguments );
		}
		else
		{
			\IPS\Log::log( "Call to undefined method in " . get_class( $this ) . "::{$name}", 'converters' );
			return NULL;
		}
	}
	
	/**
	 * Returns a block of text, or a language string, that explains what the admin must do to complete this conversion
	 *
	 * @return	string
	 */
	public function getPostConversionInformation()
	{
		$appId	= $this->software->app->parent ?: $this->software->app->app_id;
		return \IPS\Member::loggedIn()->language()->addToStack( 'conversion_info_message', FALSE, array( 'sprintf' => array(
			(string) \IPS\Http\Url::internal( "app=convert&module=manage&controller=create&_new=1" ),
			(string) \IPS\Http\Url::internal( "app=convert&module=manage&controller=convert&do=finish&id={$appId}" )
		) ) );
	}
	
	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @return	array
	 */
	abstract public function menuRows();

	/**
	 * Get method from menu rows - abstracted to allow 'fake' entries not in menuRows()
	 *
	 * @param	string	$method	Method requested
	 * @return	array
	 */
	public function getMethodFromMenuRows( $method )
	{
		$menuRows = $this->menuRows();
		if( isset( $menuRows[ $method ] ) )
		{
			return $menuRows[ $method ];
		}

		return array();
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	The method to truncate
	 * @return	array
	 */
	abstract protected function truncate( $method );
}