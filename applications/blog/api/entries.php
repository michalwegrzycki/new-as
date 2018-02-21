<?php
/**
 * @brief		Blog Entries API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		9 Dec 2015
 */

namespace IPS\blog\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Blog Entries API
 */
class _entries extends \IPS\Content\Api\ItemController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\blog\Entry';
	
	/**
	 * GET /blog/entries
	 * Get list of entries
	 *
	 * @apiparam	string	blogs			Comma-delimited list of blog IDs
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only entries started by those members are returned
	 * @apiparam	int		locked			If 1, only entries which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden			If 1, only entries which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		pinned			If 1, only entries which are pinned are returned, if 0 only not pinned
	 * @apiparam	int		featured		If 1, only entries which are featured are returned, if 0 only not featured
	 * @apiparam	int		draft			If 1, only draft entries are returned, if 0 only published
	 * @apiparam	string	sortBy			What to sort by. Can be 'date' for creation date, 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();
		
		/* Draft */
		if ( isset( \IPS\Request::i()->draft ) )
		{
			$where[] = array( 'entry_status=?', \IPS\Request::i()->draft ? 'draft' : 'published' );
		}
				
		/* Return */
		return $this->_list( $where, 'blogs' );
	}
	
	/**
	 * GET /blog/entries/{id}
	 * View information about a specific blog entry
	 *
	 * @param		int		$id				ID Number
	 * @throws		2B300/A	INVALID_ID		The entry ID does not exist
	 * @return		\IPS\blog\Entry
	 */
	public function GETitem( $id )
	{
		try
		{
			return $this->_view( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/A', 404 );
		}
	}

	/**
	 * GET /blog/entries/{id}/comments
	 * View comments on an entry
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		page		Page number
	 * @throws		2B300/1	INVALID_ID	The entry ID does not exist
	 * @return		\IPS\Api\PaginatedResponse<IPS\blog\Entry\Comment>
	 */
	public function GETitem_comments( $id )
	{
		try
		{
			return $this->_comments( $id, 'IPS\blog\Entry\Comment' );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/1', 404 );
		}
	}
	
	/**
	 * POST /blog/entries
	 * Create an entry
	 *
	 * @reqapiparam	int					blog			The ID number of the blog the entry should be created in
	 * @reqapiparam	int					author			The ID number of the member creating the entry (0 for guest)
	 * @reqapiparam	string				title			The entry title
	 * @reqapiparam	string				entry			The entry content as HTML (e.g. "<p>This is a blog entry.</p>")
	 * @apiparam	bool				draft			If this is a draft
	 * @apiparam	string				prefix			Prefix tag
	 * @apiparam	string				tags			Comma-separated list of tags (do not include prefix)
	 * @apiparam	datetime			date			The date/time that should be used for the entry date. If not provided, will use the current date/time
	 * @apiparam	string				ip_address		The IP address that should be stored for the entry/post. If not provided, will use the IP address from the API request
	 * @apiparam	int					locked			1/0 indicating if the entry should be locked
	 * @apiparam	int					hidden			0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned			1/0 indicating if the entry should be pinned
	 * @apiparam	int					featured		1/0 indicating if the entry should be featured
	 * @throws		1B300/2				NO_BLOG			The blog ID does not exist
	 * @throws		1B300/3				NO_AUTHOR		The author ID does not exist
	 * @throws		1B300/4				NO_TITLE		No title was supplied
	 * @throws		1B300/5				NO_CONTENT		No content was supplied
	 * @return		\IPS\blog\Entry
	 */
	public function POSTindex()
	{
		/* Get blog */
		try
		{
			$blog = \IPS\blog\Blog::load( \IPS\Request::i()->blog );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_BLOG', '1B300/2', 400 );
		}
		
		/* Get author */
		if ( \IPS\Request::i()->author )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author );
			if ( !$author->member_id )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1B300/3', 400 );
			}
		}
		else
		{
			$author = new \IPS\Member;
		}
		
		/* Check we have a title and a description */
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1B300/4', 400 );
		}
		if ( !\IPS\Request::i()->entry )
		{
			throw new \IPS\Api\Exception( 'NO_CONTENT', '1B300/5', 400 );
		}
		
		/* Do it */
		return new \IPS\Api\Response( 201, $this->_create( $blog, $author )->apiOutput() );
	}
	
	/**
	 * POST /blog/entries/{id}
	 * Edit a blog entry
	 *
	 * @reqapiparam	int					blog			The ID number of the blog the entry should be created in
	 * @reqapiparam	int					author			The ID number of the member creating the entry (0 for guest)
	 * @reqapiparam	string				title			The entry title
	 * @reqapiparam	string				entry			The entry content as HTML (e.g. "<p>This is a blog entry.</p>")
	 * @apiparam	bool				draft			If this is a draft
	 * @apiparam	string				prefix			Prefix tag
	 * @apiparam	string				tags			Comma-separated list of tags (do not include prefix)
	 * @apiparam	datetime			date			The date/time that should be used for the entry date. If not provided, will use the current date/time
	 * @apiparam	string				ip_address		The IP address that should be stored for the entry/post. If not provided, will use the IP address from the API request
	 * @apiparam	int					locked			1/0 indicating if the entry should be locked
	 * @apiparam	int					hidden			0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned			1/0 indicating if the entry should be pinned
	 * @apiparam	int					featured		1/0 indicating if the entry should be featured
	 * @throws		2B300/6				INVALID_ID		The entry ID is invalid
	 * @throws		1B300/7				NO_BLOG			The blog ID does not exist
	 * @throws		1B300/8				NO_AUTHOR		The author ID does not exist
	 * @return		\IPS\blog\Entry
	 */
	public function POSTitem( $id )
	{
		try
		{
			$entry = \IPS\blog\Entry::load( $id );
			
			/* New blog */
			if ( isset( \IPS\Request::i()->blog ) and \IPS\Request::i()->blog != $entry->forum_id )
			{
				try
				{
					$newBlog = \IPS\blog\Blog::load( \IPS\Request::i()->blog );
					$entry->move( $newBlog );
				}
				catch ( \OutOfRangeException $e )
				{
					throw new \IPS\Api\Exception( 'NO_BLOG', '1B300/7', 400 );
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
					
					$entry->changeAuthor( $member );
				}
				catch ( \OutOfRangeException $e )
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1B300/8', 400 );
				}
			}
			
			/* Everything else */
			$this->_createOrUpdate( $entry );
			
			/* Save and return */
			$entry->save();
			return new \IPS\Api\Response( 200, $entry->apiOutput() );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/6', 404 );
		}
	}

	/**
	 * Create or update entry
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	\IPS\Content\Item
	 */
	protected function _createOrUpdate( \IPS\Content\Item $item )
	{
		/* Is draft */
		if ( isset( \IPS\Request::i()->draft ) )
		{
			$item->status = \IPS\Request::i()->draft ? 'draft' : 'published';
		}
		
		/* Content */
		if ( isset( \IPS\Request::i()->entry ) )
		{
			$item->content = \IPS\Request::i()->entry;
		}
		
		/* Pass up */
		return parent::_createOrUpdate( $item );
	}
		
	/**
	 * DELETE /blog/entries/{id}
	 * Delete an entry
	 *
	 * @param		int		$id			ID Number
	 * @throws		2B300/9	INVALID_ID	The entry ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{
			\IPS\blog\Entry::load( $id )->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2B300/9', 404 );
		}
	}
}