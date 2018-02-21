<?php
/**
 * @brief		Pages Database Comments Comments API
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
 * @brief	Pages Database Comments API
 */
class _comments extends \IPS\Content\Api\CommentController
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
	 * GET /cms/comments/{database_id}
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
	 * @throws		2T311/1	INVALID_DATABASE	The database ID does not exist
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records\Comment>
	 */
	public function GETindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T311/1', 404 );
		}	
		
		/* Return */
		return $this->_list( array( array( 'comment_database_id=?', $database->id ) ), 'categories' );
	}
	
	/**
	 * GET /cms/comments/{database_id}/{id}
	 * View information about a specific comment
	 *
	 * @param		int		$database			Database ID
	 * @param		int		$comment			Comment ID
	 * @throws		2T311/1	INVALID_DATABASE	The database ID does not exist
	 * @throws		2T311/3	INVALID_ID	The comment ID does not exist
	 * @return		\IPS\cms\Records\Comment
	 */
	public function GETitem( $database, $comment )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T311/2', 404 );
		}	
		
		/* Return */
		try
		{
			$class = 'IPS\cms\Records\Comment' . $database->id;
			return new \IPS\Api\Response( 200, $class::load( $comment )->apiOutput() );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T311/3', 404 );
		}
	}
	
	/**
	 * POST /cms/comments/{database_id}
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
	 * @throws		2T311/4		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T311/5		INVALID_ID			The comment ID does not exist
	 * @throws		1T311/6		NO_AUTHOR			The author ID does not exist
	 * @throws		1T311/7		NO_CONTENT			No content was supplied
	 * @return		\IPS\cms\Records\Comment
	 */
	public function POSTindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T311/4', 404 );
		}	
		
		/* Get record */
		try
		{
			$recordClass = 'IPS\cms\Records' . $database->id;
			$record = $recordClass::load( \IPS\Request::i()->record );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T311/5', 403 );
		}
		
		/* Get author */
		if ( \IPS\Request::i()->author )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author );
			if ( !$author->member_id )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T311/6', 404 );
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
		
		/* Do it */
		return $this->_create( $record, $author );
	}
	
	/**
	 * POST /cms/comments/{database_id}/{comment_id}
	 * Edit a comment
	 *
	 * @param		int			$database		Database ID
	 * @param		int			$comment		Comment ID
	 * @apiparam	int			author			The ID number of the member making the comment (0 for guest)
	 * @apiparam	string		author_name		If author is 0, the guest name that should be used
	 * @apiparam	string		content			The comment content as HTML (e.g. "<p>This is a comment.</p>")
	 * @apiparam	int			hidden				1/0 indicating if the topic should be hidden
	 * @throws		2T311/7		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T311/8		INVALID_ID			The comment ID does not exist
	 * @throws		1T311/9		NO_AUTHOR			The author ID does not exist
	 * @return		\IPS\cms\Records\Comment
	 */
	public function POSTitem( $database, $comment )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T311/7', 404 );
		}	
		
		/* Do it */
		try
		{
			/* Load */
			$comment = call_user_func( array( $this->class, 'load' ), $comment );
						
			/* Do it */
			try
			{
				return $this->_edit( $comment );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T311/9', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T311/8', 404 );
		}
	}
		
	/**
	 * DELETE /cms/comments/{database_id}/{comment_id}
	 * Deletes a comment
	 *
	 * @param		int			$database			Database ID
	 * @param		int			$comment			Comment ID
	 * @throws		2T311/A		INVALID_DATABASE	The database ID does not exist
	 * @throws		2T311/B		INVALID_ID			The comment ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $database, $comment )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records\Comment' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T311/A', 404 );
		}	
		
		/* Do it */
		try
		{			
			call_user_func( array( $this->class, 'load' ), $comment )->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T311/B', 404 );
		}
	}
}