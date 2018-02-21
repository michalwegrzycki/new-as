<?php
/**
 * @brief		Pages Database Reviews API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		10 Dec 2015
 */

namespace IPS\cms\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Pages Database Reviews API
 */
class _reviews extends \IPS\Content\Api\CommentController
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
	 * GET /cms/reviews/{database_id}
	 * Get list of comments
	 *
	 * @param		int		$database			Database ID
	 * @apiparam	string	categories			Comma-delimited list of category IDs
	 * @apiparam	string	authors				Comma-delimited list of member IDs - if provided, only topics started by those members are returned
	 * @apiparam	int		locked				If 1, only comments from events which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden				If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		featured			If 1, only comments from  events which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy				What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @throws		2T312/1	INVALID_DATABASE	The database ID does not exist
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records\Review>
	 */
	public function GETindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/1', 404 );
		}	
		
		/* Return */
		return $this->_list( array( array( 'review_database_id=?', $database->id ) ), 'categories' );
	}
	
	/**
	 * GET /cms/reviews/{database_id}/{id}
	 * View information about a specific comment
	 *
	 * @param		int		$database			Database ID
	 * @param		int		$review			Comment ID
	 * @throws		2T312/2	INVALID_DATABASE	The database ID does not exist
	 * @throws		2T311/3	INVALID_ID	The comment ID does not exist
	 * @return		\IPS\cms\Records\Review
	 */
	public function GETitem( $database, $review )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/2', 404 );
		}	
		
		/* Return */
		try
		{
			$class = 'IPS\cms\Records\Review' . $database->id;
			return new \IPS\Api\Response( 200, $class::load( $review )->apiOutput() );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/3', 404 );
		}
	}
	
	/**
	 * POST /cms/reviews/{database_id}
	 * Create a comment
	 *
	 * @param		int			$database			Database ID
	 * @reqapiparam	int			record				The ID number of the record the comment is for
	 * @reqapiparam	int			author				The ID number of the member making the comment (0 for guest)
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @reqapiparam	string		content				The comment content as HTML (e.g. "<p>This is a comment.</p>")
	 * @apiparam	datetime	date				The date/time that should be used for the comment date. If not provided, will use the current date/time
	 * @apiparam	string		ip_address			The IP address that should be stored for the comment. If not provided, will use the IP address from the API request
	 * @apiparam	int			hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @reqapiparam	int			rating				Star rating
	 * @throws		2T312/4		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T312/5		INVALID_ID			The comment ID does not exist
	 * @throws		1T312/6		NO_AUTHOR			The author ID does not exist
	 * @throws		1T312/7		NO_CONTENT			No content was supplied
	 * @throws		1T312/8		INVALID_RATING		The rating is not a valid number up to the maximum rating
	 * @return		\IPS\cms\Records\Review
	 */
	public function POSTindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/4', 404 );
		}	
		
		/* Get record */
		try
		{
			$recordClass = 'IPS\cms\Records' . $database->id;
			$record = $recordClass::load( \IPS\Request::i()->record );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/5', 403 );
		}
		
		/* Get author */
		if ( \IPS\Request::i()->author )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author );
			if ( !$author->member_id )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T312/6', 404 );
			}
		}
		else
		{
			$author = new \IPS\Member;
			$author->name = \IPS\Request::i()->author_name;
		}
		
		/* Check we have a post */
		if ( !\IPS\Request::i()->content )
		{
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1T311/7', 403 );
		}
		
		/* Check we have a rating */
		if ( !\IPS\Request::i()->rating or !in_array( (int) \IPS\Request::i()->rating, range( 1, \IPS\Settings::i()->reviews_rating_out_of ) ) )
		{
			throw new \IPS\Api\Exception( 'INVALID_RATING', '1T312/8', 403 );
		}
		
		/* Do it */
		return $this->_create( $record, $author );
	}
	
	/**
	 * POST /cms/reviews/{database_id}/{review_id}
	 * Edit a comment
	 *
	 * @param		int			$database		Database ID
	 * @param		int			$review			Review ID
	 * @apiparam	int			author			The ID number of the member making the review (0 for guest)
	 * @apiparam	string		author_name		If author is 0, the guest name that should be used
	 * @apiparam	string		content			The comment content as HTML (e.g. "<p>This is a comment.</p>")
	 * @apiparam	int			hidden				1/0 indicating if the topic should be hidden
	 * @apiparam	int			rating				Star rating
	 * @throws		2T312/9		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T312/A		INVALID_ID			The comment ID does not exist
	 * @throws		1T312/B		NO_AUTHOR			The author ID does not exist
	 * @return		\IPS\cms\Records\Review
	 */
	public function POSTitem( $database, $review )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/9', 404 );
		}	
		
		/* Do it */
		try
		{
			/* Load */
			$review = call_user_func( array( $this->class, 'load' ), $review );
						
			/* Do it */
			try
			{
				return $this->_edit( $review );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T312/B', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/A', 404 );
		}
	}
		
	/**
	 * DELETE /cms/reviews/{database_id}/{review_id}
	 * Deletes a comment
	 *
	 * @param		int			$database			Database ID
	 * @param		int			$review				Comment ID
	 * @throws		2T312/C		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T312/D		INVALID_ID			The comment ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $database, $review )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Review' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T312/C', 404 );
		}	
		
		/* Do it */
		try
		{			
			call_user_func( array( $this->class, 'load' ), $review )->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T312/D', 404 );
		}
	}
}