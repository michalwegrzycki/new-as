<?php
/**
 * @brief		Gallery Reviews API
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
 * @brief	Gallery Reviews API
 */
class _reviews extends \IPS\Content\Api\CommentController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\gallery\Image\Review';
	
	/**
	 * GET /gallery/reviews
	 * Get list of reviews
	 *
	 * @apiparam	string	categories		Comma-delimited list of category IDs (will also include images in albums in those categories)
	 * @apiparam	string	albums			Comma-delimited list of album IDs
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only topics started by those members are returned
	 * @apiparam	int		locked			If 1, only reviews from images which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden			If 1, only reviews which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		featured		If 1, only reviews from  images which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy			What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @return		\IPS\Api\PaginatedResponse<IPS\gallery\Image\Review>
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
	 * GET /gallery/reviews/{id}
	 * View information about a specific review
	 *
	 * @param		int		$id			ID Number
	 * @throws		2G318/1	INVALID_ID	The review ID does not exist
	 * @return		\IPS\gallery\Image\Review
	 */
	public function GETitem( $id )
	{
		try
		{
			return new \IPS\Api\Response( 200, \IPS\gallery\Image\Review::load( $id )->apiOutput() );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G318/1', 404 );
		}
	}
	
	/**
	 * POST /gallery/reviews
	 * Create a review
	 *
	 * @reqapiparam	int			image				The ID number of the image the review is for
	 * @reqapiparam	int			author				The ID number of the member making the review (0 for guest)
	 * @apiparam	string		author_name			If author is 0, the guest name that should be used
	 * @reqapiparam	string		content				The review content as HTML (e.g. "<p>This is a review.</p>")
	 * @apiparam	datetime	date				The date/time that should be used for the review date. If not provided, will use the current date/time
	 * @apiparam	string		ip_address			The IP address that should be stored for the review. If not provided, will use the IP address from the API request
	 * @apiparam	int			hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @reqapiparam	int			rating				Star rating
	 * @throws		2G318/8		INVALID_ID	The forum ID does not exist
	 * @throws		1G318/3		NO_AUTHOR	The author ID does not exist
	 * @throws		1L298/4		NO_CONTENT	No content was supplied
	 * @throws		1G318/5		INVALID_RATING	The rating is not a valid number up to the maximum rating
	 * @return		\IPS\gallery\Image\Review
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
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G318/2', 403 );
		}
		
		/* Get author */
		if ( \IPS\Request::i()->author )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author );
			if ( !$author->member_id )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G318/3', 404 );
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
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1G318/4', 403 );
		}
		
		/* Check we have a rating */
		if ( !\IPS\Request::i()->rating or !in_array( (int) \IPS\Request::i()->rating, range( 1, \IPS\Settings::i()->reviews_rating_out_of ) ) )
		{
			throw new \IPS\Api\Exception( 'INVALID_RATING', '2G318/8', 403 );
		}
		
		/* Do it */
		return $this->_create( $image, $author );
	}
	
	/**
	 * POST /gallery/reviews/{id}
	 * Edit a review
	 *
	 * @param		int			$id				ID Number
	 * @apiparam	int			author			The ID number of the member making the review (0 for guest)
	 * @apiparam	string		author_name		If author is 0, the guest name that should be used
	 * @apiparam	string		content			The review content as HTML (e.g. "<p>This is a review.</p>")
	 * @apiparam	int			hidden			1/0 indicating if the topic should be hidden
	 * @apiparam	int			rating			Star rating
	 * @throws		2L298/5		INVALID_ID		The review ID does not exist
	 * @throws		1G318/B		NO_AUTHOR		The author ID does not exist
	 * @throws		1G318/A		INVALID_RATING	The rating is not a valid number up to the maximum rating
	 * @return		\IPS\gallery\Image\Review
	 */
	public function POSTitem( $id )
	{
		try
		{
			/* Load */
			$comment = \IPS\gallery\Image\Review::load( $id );
			
			/* Check */
			if ( isset( \IPS\Request::i()->rating ) and !in_array( (int) \IPS\Request::i()->rating, range( 1, \IPS\Settings::i()->reviews_rating_out_of ) ) )
			{
				throw new \IPS\Api\Exception( 'INVALID_RATING', '1G318/A', 403 );
			}						
			/* Do it */
			try
			{
				return $this->_edit( $comment );
			}
			catch ( \InvalidArgumentException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1G318/B', 400 );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G318/9', 404 );
		}
	}
		
	/**
	 * DELETE /gallery/reviews/{id}
	 * Deletes a review
	 *
	 * @param		int			$id			ID Number
	 * @throws		2G318/C		INVALID_ID	The review ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{			
			\IPS\gallery\Image\Review::load( $id )->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2G318/C', 404 );
		}
	}
}