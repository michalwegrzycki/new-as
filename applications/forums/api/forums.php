<?php
/**
 * @brief		Forums API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		3 Apr 2017
 */

namespace IPS\forums\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Forums API
 */
class _forums extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Forum';
	
	/**
	 * GET /forums/forums
	 * Get list of forums
	 *
	 * @return		\IPS\Api\PaginatedResponse<IPS\forums\Forum>
	 */
	public function GETindex()
	{
		/* Return */
		return $this->_list();
	}

	/**
	 * GET /forums/forums/{id}
	 * Get specific forum
	 *
	 * @param		int		$id			ID Number
	 *
	 * @return		\IPS\Api\PaginatedResponse<IPS\forums\Forum>
	 */
	public function GETitem( $id )
	{
		/* Return */
		return $this->_view( $id );
	}

	/**
	 * POST /forums/forums
	 * Create a forum
	 *
	 * @reqapiparam	string		title				The forum title
	 * @apiparam	string		description			The forum description
	 * @apiparam	string		type				normal|qa|redirect|category
	 * @apiparam	int|null	parent				The ID number of the parent the forum should be created in. NULL for root.
	 * @apiparam	string		password			Forum password
	 * @apiparam	int			theme				Theme to use as an override
	 * @apiparam	int			sitemap_priority	1-9 1 highest priority
	 * @apiparam	int			min_content			The minimum amount of posts to be able to view
	 * @apiparam	int			can_see_others		0|1 Users can see topics posted by other users?
	 * @return		\IPS\forums\Forum
	 */
	public function POSTindex()
	{
		return $this->_create();
	}
	
	/**
	 * POST /forums/forums/{id}
	 * Edit a forum
	 *
	 * @reqapiparam	string		title				The forum title
	 * @apiparam	string		description			The forum description
	 * @apiparam	string		type				normal|qa|redirect|category
	 * @apiparam	int|null	parent				The ID number of the parent the forum should be created in. NULL for root.
	 * @apiparam	string		password			Forum password
	 * @apiparam	int			theme				Theme to use as an override
	 * @apiparam	int			sitemap_priority	1-9 1 highest priority
	 * @apiparam	int			min_content			The minimum amount of posts to be able to view
	 * @apiparam	int			can_see_others		0|1 Users can see topics posted by other users?
	 * @return		\IPS\forums\Topic
	 */
	public function POSTitem( $id )
	{
		$class = $this->class;
		$forum = $class::load( $id );
		$forum = $this->_createOrUpdate( $forum );

		return $forum;
	}
	
	/**
	 * DELETE /forums/forums/{id}
	 * Delete a forum
	 *
	 * @param		int		$id			ID Number
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		return $this->_delete( $id );
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\node\Model	$node				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\node\Model $forum )
	{
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '', 400 );
		}

		foreach ( array( 'title' => "forums_forum_{$forum->id}", 'description' => "forums_forum_{$forum->id}_desc" ) as $fieldKey => $langKey )
		{
			if ( isset( \IPS\Request::i()->$fieldKey ) )
			{
				\IPS\Lang::saveCustom( 'forums', $langKey, \IPS\Request::i()->$fieldKey );

				if ( $fieldKey === 'title' )
				{
					$forum->name_seo = \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->$fieldKey );
				}
			}
		}

		$forum->parent_id = (int) \IPS\Request::i()->parent?: -1;

		if ( isset( \IPS\Request::i()->password ) )
		{
			$forum->password = \IPS\Request::i()->password;
		}

		if ( isset( \IPS\Request::i()->theme ) )
		{
			$forum->skin_id = \IPS\Request::i()->theme;
		}

		return parent::_createOrUpdate( $forum );
	}
}