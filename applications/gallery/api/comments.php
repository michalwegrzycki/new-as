<?php
/**
 * @brief		Gallery Comments API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		8 Dec 2015
 */

namespace IPS\gallery\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Gallery Comments API
 */
class _comments extends \IPS\Content\Api\CommentController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\gallery\Image\Comment';
	
	/**
	 * GET /gallery/comments
	 * Get list of comments
	 *
	 * @apiparam	string	categories		Comma-delimited list of category IDs (will also include images in albums in those categories)
	 * @apiparam	string	albums			Comma-delimited list of album IDs
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only topics started by those members are returned
	 * @apiparam	int		locked			If 1, only comments from images which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden			If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		featured		If 1, only comments from  images which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy			What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @return		\IPS\Api\PaginatedResponse<IPS\gallery\Image\Comment>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();
		
		/* Albums */
		if ( isset( \IPS\Request::i()->albums ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'image_album_id', array_filter( explode( ',', \IPS\Request::i()->albums ) ) ) );
		}
		
		return $this->_list( $where, 'categories' );
	}
	
	/**
	 * GET /gallery/comments/{id}
	 * View information about a specific comment
	 *
	 * @param		int		$id			ID Number
	 * @throws		2L297/1	INVALID_ID	The comment ID does not exist
	 * @return		\IPS\gallery\Image\Comment
	 */
	public function GETitem( $id )
	{
		try
		{
			return new \IPS\Api\Response( 200, \IPS\gallery\Image\Comment::load( $id )->apiOutput() );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G317/1', 404 );
		}
	}
	
	/**
	 * POST /gallery/comments
	 * Create a comment
	 *
	 * @reqapiparam	int			image				The ID number of the image the comment is for
	 * @reqapiparam	int			author				The ID number of the member making the comment (0 for guest)
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @reqapiparam	string		content				The comment content as HTML (e.g. "<p>This is a comment.</p>")
	 * @apiparam	datetime	date				The date/time that should be used for the comment date. If not provided, will use the current date/time
	 * @apiparam	string		ip_address			The IP address that should be stored for the comment. If not provided, will use the IP address from the API request
	 * @apiparam	int			hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @throws		2G317/3		INVALID_ID	The comment ID does not exist
	 * @throws		1G317/4		NO_AUTHOR	The author ID does not exist
	 * @throws		1G317/5		NO_CONTENT	No content was supplied
	 * @return		\IPS\gallery\Image\Comment
	 */
	public function POSTindex()
	{
		/* Get topic */
		try
		{
			$image = \IPS\gallery\Image::load( \IPS\Request::i()->image );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2L297/3', 403 );
		}
		
		/* Get author */
		if ( \IPS\Request::i()->author )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author );
			if ( !$author->member_id )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G317/4', 404 );
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
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1G317/5', 403 );
		}
		
		/* Do it */
		return $this->_create( $image, $author );
	}
	
	/**
	 * POST /gallery/comments/{id}
	 * Edit a comment
	 *
	 * @param		int			$id					ID Number
	 * @apiparam	int			author				The ID number of the member making the comment (0 for guest)
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @apiparam	string		content				The comment content as HTML (e.g. "<p>This is a comment.</p>")
	 * @apiparam	int			hidden				1/0 indicating if the topic should be hidden
	 * @throws		2G317/6		INVALID_ID			The comment ID does not exist
	 * @throws		1G317/7		NO_AUTHOR			The author ID does not exist
	 * @return		\IPS\gallery\Image\Comment
	 */
	public function POSTitem( $id )
	{
		try
		{
			/* Load */
			$comment = \IPS\gallery\Image\Comment::load( $id );
						
			/* Do it */
			try
			{
				return $this->_edit( $comment );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G317/7', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G317/6', 404 );
		}
	}
		
	/**
	 * DELETE /gallery/comments/{id}
	 * Deletes a comment
	 *
	 * @param		int			$id			ID Number
	 * @throws		2G317/8		INVALID_ID	The comment ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{			
			\IPS\gallery\Image\Comment::load( $id )->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G317/8', 404 );
		}
	}
}