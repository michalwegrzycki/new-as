<?php
/**
 * @brief		Database Records API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		9 Dec 2015
 */

namespace IPS\cms\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Database Records API
 */
class _records extends \IPS\Content\Api\ItemController
{
	/**
	 * Class
	 */
	protected $class = NULL;
	
	/**
	 * Get endpoint data
	 *
	 * @param	array	$pathBits	The parts to the path called
	 * @return	array
	 * @throws	\RuntimeException
	 */
	protected function _getEndpoint( $pathBits )
	{
		if ( !count( $pathBits ) )
		{
			throw new \RuntimeException;
		}
		
		$database = array_shift( $pathBits );
		if ( !count( $pathBits ) )
		{
			return array( 'endpoint' => 'index', 'params' => array( $database ) );
		}
		
		$nextBit = array_shift( $pathBits );
		if ( intval( $nextBit ) != 0 )
		{
			if ( count( $pathBits ) )
			{
				return array( 'endpoint' => 'item_' . array_shift( $pathBits ), 'params' => array( $database, $nextBit ) );
			}
			else
			{				
				return array( 'endpoint' => 'item', 'params' => array( $database, $nextBit ) );
			}
		}
				
		throw new \RuntimeException;
	}
		
	/**
	 * GET /cms/records/{database_id}
	 * Get list of records
	 *
	 * @param		int		$database			Database ID
	 * @apiparam	string	categories			Comma-delimited list of category IDs
	 * @apiparam	string	authors				Comma-delimited list of member IDs - if provided, only records started by those members are returned
	 * @apiparam	int		locked				If 1, only records which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden				If 1, only records which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		pinned				If 1, only records which are pinned are returned, if 0 only not pinned
	 * @apiparam	int		featured			If 1, only records which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy				What to sort by. Can be 'date' for creation date, 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @throws		2T306/1	INVALID_DATABASE	The database ID does not exist
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records>
	 */
	public function GETindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/1', 404 );
		}	
		
		/* Where clause */
		$where = array();
		
		/* Return */
		return $this->_list( $where, 'categories' );
	}
	
	/**
	 * GET /cms/records/{database_id}/{record_id}
	 * View details about a specific record
	 *
	 * @param		int		$database		Database ID Number
	 * @param		int		$record			Record ID Number
	 * @throws		2T306/2	INVALID_DATABASE	The database ID does not exist
	 * @throws		2T306/3	INVALID_ID			The record ID does not exist
	 * @return		\IPS\cms\Records
	 */
	public function GETitem( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/2', 404 );
		}
		
		/* Return */
		try
		{
			return new \IPS\Api\Response( 200, call_user_func( array( $this->class, 'load' ), $record )->apiOutput() );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/3', 404 );
		}
	}
	
	/**
	 * POST /cms/records/{database_id}
	 * Create a record
	 *
	 * @param		int					$database			Database ID Number
	 * @reqapiparam	int					category			The ID number of the category the record should be created in. If the database does not use categories, this is not required
	 * @reqapiparam	int					author				The ID number of the member creating the record (0 for guest)
	 * @reqapiparam	object				fields				Field values. Keys should be the field ID, and the value should be the value
	 * @apiparam	string				prefix				Prefix tag
	 * @apiparam	string				tags				Comma-separated list of tags (do not include prefix)
	 * @apiparam	datetime			date				The date/time that should be used for the record date. If not provided, will use the current date/time
	 * @apiparam	string				ip_address			The IP address that should be stored for the record. If not provided, will use the IP address from the API request
	 * @apiparam	int					locked				1/0 indicating if the record should be locked
	 * @apiparam	int					hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned				1/0 indicating if the record should be pinned
	 * @apiparam	int					featured			1/0 indicating if the record should be featured
	 * @throws		2T306/4				INVALID_DATABASE	The database ID does not exist
	 * @throws		1T306/5				NO_CATEGORY			The category ID does not exist
	 * @throws		1T306/6				NO_AUTHOR			The author ID does not exist
	 * @return		\IPS\cms\Records
	 */
	public function POSTindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/4', 404 );
		}
				
		/* Get category */
		try
		{
			if ( $database->use_categories )
			{
				$category = \IPS\cms\Categories::load( \IPS\Request::i()->category );
			}
			else
			{
				$category = \IPS\cms\Categories::load( $database->default_category );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_CATEGORY', '1T306/5', 400 );
		}
		
		/* Get author */
		if ( \IPS\Request::i()->author )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author );
			if ( !$author->member_id )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/6', 400 );
			}
		}
		else
		{
			$author = new \IPS\Member;
		}
				
		/* Do it */
		return new \IPS\Api\Response( 201, $this->_create( $category, $author )->apiOutput() );
	}
	
	/**
	 * POST /cms/records/{database_id}/{record_id}
	 * Edit a record
	 *
	 * @param		int					$database		Database ID Number
	 * @param		int					$record			Record ID Number
	 * @param		int					$database		Database ID Number
	 * @apiparam	int					category		The ID number of the category the record should be created in. If the database does not use categories, this is not required
	 * @apiparam	int					author			The ID number of the member creating the record (0 for guest)
	 * @apiparam	object				fields			Field values. Keys should be the field ID, and the value should be the value
	 * @apiparam	string				prefix			Prefix tag
	 * @apiparam	string				tags			Comma-separated list of tags (do not include prefix)
	 * @apiparam	datetime			date			The date/time that should be used for the record date. If not provided, will use the current date/time
	 * @apiparam	string				ip_address		The IP address that should be stored for the record. If not provided, will use the IP address from the API request
	 * @apiparam	int					locked			1/0 indicating if the record should be locked
	 * @apiparam	int					hidden			0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned			1/0 indicating if the record should be pinned
	 * @apiparam	int					featured		1/0 indicating if the record should be featured
	 * @throws		2T306/9				INVALID_DATABASE	The database ID does not exist
	 * @throws		2T306/6				INVALID_ID		The record ID is invalid
	 * @throws		1T306/7				NO_CATEGORY		The category ID does not exist
	 * @throws		1T306/8				NO_AUTHOR		The author ID does not exist
	 * @return		\IPS\cms\Records
	 */
	public function POSTitem( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/9', 404 );
		}
		
		/* Load record */
		try
		{
			$record = call_user_func( array( $this->class, 'load' ), $record );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/6', 404 );
		}
			
		/* New category */
		if ( $database->use_categories and isset( \IPS\Request::i()->category ) and \IPS\Request::i()->category != $record->category_id )
		{
			try
			{
				$newCategory = \IPS\blog\Blog::load( \IPS\Request::i()->blog );
				$record->move( $newCategory );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'NO_CATEGORY', '1T306/7', 400 );
			}
		}
		
		/* New author */
		if ( isset( \IPS\Request::i()->author ) )
		{				
			try
			{
				$member = \IPS\Member::load( \IPS\Request::i()->author );
				if ( !$member->member_id )
				{
					throw new \OutOfRangeException;
				}
				
				$record->changeAuthor( $member );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/8', 400 );
			}
		}
		
		/* Everything else */
		$this->_createOrUpdate( $record );
		
		/* Save and return */
		$record->save();
		return new \IPS\Api\Response( 200, $record->apiOutput() );
	}

	/**
	 * Create or update record
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	\IPS\Content\Item
	 */
	protected function _createOrUpdate( \IPS\Content\Item $item )
	{
		/* Set field values */
		if ( isset( \IPS\Request::i()->fields ) )
		{
			$fieldsClass = str_replace( 'Records', 'Fields', get_class( $item ) );
			foreach ( $fieldsClass::data() as $key => $field )
			{
				$key = "field_{$field->_id}";
				$item->$key = \IPS\Request::i()->fields[ $field->id ];
			}
		}
		
		/* Pass up */
		return parent::_createOrUpdate( $item );
	}
	
	/**
	 * GET /cms/records/{database_id}/{record_id}/comments
	 * Get comments on a record
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		hidden		If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortDir		Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page		Page number
	 * @throws		2T306/C		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T306/D		INVALID_ID	The entry ID does not exist
	 * @return		\IPS\Api\PaginatedResponse<IPS\calendar\Event\Comment>
	 */
	public function GETitem_comments( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/C', 404 );
		}
		
		/* Return */
		try
		{
			return $this->_comments( $record, 'IPS\cms\Records\Comment' . $database->id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/D', 404 );
		}
	}
	
	/**
	 * GET /cms/records/{database_id}/{record_id}/reviews
	 * Get reviews on a record
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		hidden		If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortDir		Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page		Page number
	 * @throws		2T306/E		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T306/F		INVALID_ID	The entry ID does not exist
	 * @return		\IPS\Api\PaginatedResponse<IPS\calendar\Event\Review>
	 */
	public function GETitem_reviews( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/E', 404 );
		}
		
		/* Return */
		try
		{
			return $this->_comments( $record, 'IPS\cms\Records\Review' . $database->id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/F', 404 );
		}
	}
		
	/**
	 * DELETE /cms/records/{database_id}/{record_id}
	 * Delete an entry
	 *
	 * @param		int			$database		Database ID Number
	 * @param		int			$record			Record ID Number
	 * @throws		2T306/A		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T306/B		INVALID_ID	The entry ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/A', 404 );
		}
		
		/* Load record */
		try
		{
			$record = call_user_func( array( $this->class, 'load' ), $record );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/B', 404 );
		}
		
		/* Delete and return */
		$record->delete();
		return new \IPS\Api\Response( 200, NULL );
	}
}