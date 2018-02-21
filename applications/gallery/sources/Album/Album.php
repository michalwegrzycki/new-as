<?php
/**
 * @brief		Album Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Album Node
 */
class _Album extends \IPS\Node\Model implements \IPS\Node\Ratings, \IPS\Content\Embeddable
{
	/**
	 * @brief	Define access levels
	 */
	const AUTH_TYPE_PUBLIC		= 1;
	const AUTH_TYPE_PRIVATE		= 2;
	const AUTH_TYPE_RESTRICTED	= 3;
	const AUTH_TYPE_DELETED		= 4;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'gallery_albums';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'album_';

	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'category_id';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'albums';

	/**
	 * @brief	[Node] Node Database Order Column
	 */
	public static $databaseColumnOrder = 'name';

	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = FALSE;

	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\gallery\Image';

	/**
	 * @brief	[Node] If the node can be "owned", the owner "type" (typically "member" or "group") and the associated database column
	 */
	public static $ownerTypes = array( 'member' => 'owner_id' );

	/**
	 * @brief	[Node] By mapping appropriate columns (rating_average and/or rating_total + rating_hits) allows to cache rating values
	 */
	public static $ratingColumnMap	= array(
		'rating_average'	=> 'rating_aggregate',
		'rating_total'		=> 'rating_total',
		'rating_hits'		=> 'rating_count',
	);

	/**
	 * Return this album as a content item instead of a node
	 *
	 * @return \IPS\gallery\Album\Item
	 */
	public function asItem()
	{
		$data = $this->_data;

		foreach( $this->_data as $k => $v )
		{
			$data['album_' . $k ] = $v;
		}

		return \IPS\gallery\Album\Item::constructFromData( $data );
	}

	/**
	 * [Node] Return the owner if this node can be owned
	 *
	 * @throws	\RuntimeException
	 * @return	\IPS\Member|null
	 */
	public function owner()
	{
		$owner = parent::owner();

		/* Gallery albums have to be owned by a user, so return a guest user if the owner is invalid */
		if( $owner === NULL OR $owner->member_id === null )
		{
			return new \IPS\Member;
		}
		
		return $owner;
	}

	/**
	 * [Node] Load and check permissions
	 *
	 * @param	mixed	$id		ID
	 * @param	string	$perm	Permission Key
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id, $perm='view' )
	{
		$obj = parent::loadAndCheckPerms( $id );

		if ( !$obj->category()->can( $perm ) )
		{
			throw new \OutOfRangeException;
		}
		
		if ( $obj->type == static::AUTH_TYPE_DELETED )
		{
			throw new \OutOfRangeException;
		}
		
		/* If we don't have edit permission in the category, check album restrictions */
		if( !\IPS\gallery\Image::modPermission( 'edit', NULL, $obj->category() ) )
		{
			/* Throw exception if this is a private album we can't access */
			if( $obj->type == static::AUTH_TYPE_PRIVATE AND $obj->owner() != \IPS\Member::loggedIn() )
			{
				throw new \OutOfRangeException;
			}

			/* Throw exception if this is a restricted album we can't access */
			if( $obj->type == static::AUTH_TYPE_RESTRICTED AND $obj->owner() != \IPS\Member::loggedIn() )
			{
				/* This will throw an exception if the row does not exist... but we need it to throw an OutOfRangeException */
				try
				{
					if( !\IPS\Member::loggedIn()->member_id )
					{
						throw new \OutOfRangeException;
					}

					$member	= \IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'group_id=? AND member_id=?', $obj->allowed_access, \IPS\Member::loggedIn()->member_id ) )->first();
				}
				catch( \UnderflowException $e )
				{
					throw new \OutOfRangeException;
				}
			}
		}

		return $obj;
	}
	
	/**
	 * Fetch all nodes owned by a given user
	 *
	 * @param	\IPS\Member|NULL	$member		The member whose nodes to load
	 * @param	array				$where		Initial where clause
	 * @return	array
	 * @throws	\RuntimeException
	 */
	public static function loadByOwner( $member=NULL, $where=array() )
	{
		$where[] = array( 'album_type !=?', static::AUTH_TYPE_DELETED );
		
		return parent::loadByOwner( $member, $where );
	}

	/**
	 * @brief Cached approved members
	 */
	protected $_approvedMembers	= FALSE;

	/**
	 * Get members with access to restricted album
	 *
	 * @return	array|null
	 * @note	This list will NOT include the album owner, who also inherently has access
	 */
	protected function get_approvedMembers()
	{
		if( $this->_approvedMembers !== FALSE )
		{
			return $this->_approvedMembers;
		}

		if( $this->type === static::AUTH_TYPE_RESTRICTED )
		{
			$members	= array();

			foreach( \IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'group_id=?', $this->allowed_access ) ) as $member )
			{
				$members[]	= \IPS\Member::load( $member['member_id'] );
			}

			$this->_approvedMembers	= $members;

			return $this->_approvedMembers;
		}

		$this->_approvedMembers = NULL;

		return $this->_approvedMembers;
	}

	/**
	 * [Node] Get title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		return $this->name;
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_name_seo()
	{
		if( !$this->_data['name_seo'] )
		{
			$this->name_seo	= \IPS\Http\Url\Friendly::seoTitle( $this->name );

			if( $this->_data['name_seo'] )
			{
				$this->save();
			}
		}

		return $this->_data['name_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->name );
	}

	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return NULL;
	}

	/**
	 * Get sort order
	 *
	 * @return	string
	 */
	public function get__sortBy()
	{
		return $this->sort_options;
	}

	/**
	 * [Node] Get number of content items
	 *
	 * @return	int
	 */
	protected function get__items()
	{
		return $this->count_imgs;
	}

	/**
	 * Get items count language string
	 *
	 * @return string
	 * @throws	\BadMethodCallException
	 */
	public function get__countLanguageString()
	{
		return 'num_images';
	}

	/**
	 * [Node] Get number of content comments
	 *
	 * @return	int
	 */
	protected function get__comments()
	{
		return $this->count_comments;
	}

	/**
	 * [Node] Get number of content reviews
	 *
	 * @return	int
	 */
	protected function get__reviews()
	{
		return $this->count_reviews;
	}
	
	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unnapprovedItems()
	{
		return $this->count_imgs_hidden;
	}

	/**
	 * [Node] Get number of unapproved content reviews
	 *
	 * @return	int
	 */
	protected function get__unapprovedReviews()
	{
		return $this->count_reviews_hidden;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @return	int
	 */
	protected function get__unapprovedComments()
	{
		return $this->count_comments_hidden;
	}

	/**
	 * [Node] Get content table description
	 *
	 * @return	string
	 */
	protected function get_description()
	{
		return $this->_data['description'];
	}

	/**
	 * Set number of items
	 *
	 * @param	int	$val	Items
	 * @return	void
	 */
	protected function set__items( $val )
	{
		$this->count_imgs = (int) $val;
	}

	/**
	 * Set number of items
	 *
	 * @param	int	$val		Comments
	 * @return	void
	 */
	protected function set__comments( $val )
	{
		$this->count_comments = (int) $val;
	}

	/**
	 * Set number of items
	 *
	 * @param	int	$val		Reviews
	 * @return	void
	 */
	protected function set__reviews( $val )
	{
		$this->count_reviews = (int) $val;
	}

	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__unapprovedItems( $val )
	{
		$this->count_imgs_hidden = $val;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @param	int	$val	Unapproved Comments
	 * @return	void
	 */
	protected function set__unapprovedComments( $val )
	{
		$this->count_comments_hidden = $val;
	}

	/**
	 * [Node] Get number of unapproved content reviews
	 *
	 * @param	int	$val		Unapproved Reviews
	 * @return	void
	 */
	protected function set__unapprovedReviews( $val )
	{
		$this->count_reviews_hidden = $val;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		foreach ( static::formFields( $this->_id ? $this : NULL ) as $field )
		{
			$form->add( $field );
		}
	}
	
	/**
	 * Get fields
	 *
	 * @param	\IPS\gallery\Album|NULL	$album		The album
	 * @param	bool					$forOther	Is this specifically not for the current member (e.g. on a move form)?
	 * @param	bool					$required	If TRUE, required elements (like name are actually required) otherwise they just appear so (e.g. on move form)
	 * @return	array
	 */
	public static function formFields( \IPS\gallery\Album $album = NULL, $forOther = FALSE, $required = TRUE )
	{
		$return = array();
		
		$return[] = new \IPS\Helpers\Form\Text( 'album_name', $album ? $album->name : '', $required ?: NULL );

		$return[] = new \IPS\Helpers\Form\Editor( 'album_description', $album ? $album->description : '', FALSE, array( 'app' => 'gallery', 'key' => 'Albums', 'autoSaveKey' => ( $album ? "gallery_album_{$album->id}_desc" : "gallery-new-album" ), 'attachIds' => $album ? array( $album->id, NULL, 'description' ) : NULL, 'minimize' => 'cdesc_placeholder' ) );
		
		$return[] = new \IPS\Helpers\Form\Node( 'album_category', ( $album and $album->category_id ) ? \IPS\gallery\Category::load( $album->category_id ) : NULL, $required ?: NULL, array(
			'class'		      => 'IPS\gallery\Category',
			'disabled'	      => false,
			'permissionCheck' => function( $node )
			{
				if ( ! $node->allow_albums )
				{
					return false;
				}
				
				if ( ! $node->can( 'add' ) )
				{
					return false;
				}
				
				return true;
			}
		) );

		if( $forOther or \IPS\gallery\Album\Item::modPermission( 'edit', NULL, ( $album ? $album->category() : NULL ) ) )
		{
			if ( !$forOther )
			{
				$return[] = new \IPS\Helpers\Form\Radio( 'set_album_owner', ( $album AND $album->owner_id !== \IPS\Member::loggedIn()->member_id ) ? 'other' : 'me', $required ?: NULL, array( 'options' => array( 'me' => 'set_album_owner_me', 'other' => 'set_album_owner_other' ), 'toggles' => array( 'other' => array( 'album_owner' ) ) ), NULL, NULL, NULL, 'set_album_owner' );
			}
			$return[] = new \IPS\Helpers\Form\Member( 'album_owner', $album ? \IPS\Member::load( $album->owner_id )->name : NULL, NULL, array(), function( $val ) use ( $required, $forOther )
			{
				if ( !$val and $required and ( $forOther or \IPS\Request::i()->set_album_owner == 'other' ) )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, NULL, 'album_owner' );
		}

		$types		= array( static::AUTH_TYPE_PUBLIC => 'album_public' );
		$toggles	= array();

		if( \IPS\Member::loggedIn()->group['g_create_albums_private'] )
		{
			$types[ static::AUTH_TYPE_PRIVATE ]	= ( \IPS\gallery\Image::modPermission( 'edit', NULL, ( $album ? $album->category() : NULL ) ) ) ? 'album_private_mod' : 'album_private';
		}

		if( \IPS\Member::loggedIn()->group['g_create_albums_fo'] )
		{
			$types[ static::AUTH_TYPE_RESTRICTED ]	= 'album_friend_only';
			$toggles[ static::AUTH_TYPE_RESTRICTED ]	= array( 'album_allowed_access' );
		}
		$return[] = new \IPS\Helpers\Form\Radio( 'album_type', ( $album and $album->type ) ? $album->type : static::AUTH_TYPE_PUBLIC, FALSE, array( 'options' => $types, 'toggles' => $toggles ), NULL, NULL, NULL, 'album_type' );

		if( \IPS\Member::loggedIn()->group['g_create_albums_fo'] )
		{
			$return[] = new \IPS\Helpers\Form\SocialGroup( 'album_allowed_access', $album ? $album->allowed_access : NULL, FALSE, array( 'owner' => $album ? \IPS\Member::load( $album->owner_id ) : ( \IPS\Request::i()->album_owner ? \IPS\Member::load( \IPS\Request::i()->album_owner, 'name' ) : \IPS\Member::loggedIn() ) ), NULL, NULL, NULL, 'album_allowed_access' );
		}
		
		$return[] = new \IPS\Helpers\Form\Select( 'album_sort_options', ( $album and $album->sort_options ) ? $album->sort_options : 'updated', FALSE, array( 'options' => array( 'updated' => 'sort_updated', 'last_comment' => 'sort_last_comment', 'title' => 'sort_title', 'rating' => 'sort_rating', 'date' => 'sort_date', 'num_comments' => 'sort_num_comments', 'num_reviews' => 'sort_num_reviews', 'views' => 'sort_views' ) ), NULL, NULL, NULL, 'album_sort_options' );

		$return[] = new \IPS\Helpers\Form\YesNo( 'album_allow_rating', $album ? $album->allow_rating : TRUE, FALSE );

		if( ( ( $album and $album->category_id ) and ( \IPS\gallery\Category::load( $album->category_id )->allow_comments ) ) or ( ( isset( \IPS\Request::i()->chosenCategory ) or isset( \IPS\Request::i()->category ) ) and \IPS\gallery\Category::load( (int) \IPS\Request::i()->chosenCategory ?: \IPS\Request::i()->category )->allow_comments ) )
		{
			$return[] = new \IPS\Helpers\Form\YesNo('album_allow_comments', $album ? $album->allow_comments : TRUE, FALSE);
		}
		if( ( ( $album and $album->category_id ) and ( \IPS\gallery\Category::load( $album->category_id )->allow_reviews ) ) or ( ( isset( \IPS\Request::i()->chosenCategory ) or isset( \IPS\Request::i()->category ) ) and \IPS\gallery\Category::load( (int) \IPS\Request::i()->chosenCategory ?: \IPS\Request::i()->category )->allow_reviews ) )
		{
			$return[] = new \IPS\Helpers\Form\YesNo('album_allow_reviews', $album ? $album->allow_reviews : TRUE, FALSE);
		}

		if( ( ( $album and $album->category_id ) and ( \IPS\gallery\Category::load( $album->category_id )->allow_comments ) ) or ( ( isset( \IPS\Request::i()->chosenCategory ) or isset( \IPS\Request::i()->category ) ) and \IPS\gallery\Category::load( (int) \IPS\Request::i()->chosenCategory ?: \IPS\Request::i()->category )->allow_comments ) )
		{
			$return[] = new \IPS\Helpers\Form\YesNo('album_use_comments', $album ? $album->use_comments : TRUE, FALSE );
		}
		if( ( ( $album and $album->category_id ) and ( \IPS\gallery\Category::load( $album->category_id )->allow_reviews ) ) or ( ( isset( \IPS\Request::i()->chosenCategory ) or isset( \IPS\Request::i()->category ) ) and \IPS\gallery\Category::load( (int) \IPS\Request::i()->chosenCategory ?: \IPS\Request::i()->category )->allow_reviews ) )
		{
			$return[] = new \IPS\Helpers\Form\YesNo('album_use_reviews', $album ? $album->use_reviews : TRUE, FALSE );
		}
		
		return $return;
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		$this->postSaveIsEdit	= FALSE;

		/* Claim attachments */
		if ( !$this->id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'gallery-new-album', $this->id, NULL, 'description' );

			/* Update public/non-public album count */
			if( isset( $values['album_type'] ) AND isset( $values['album_category'] ) )
			{
				if( $values['album_type'] == static::AUTH_TYPE_PUBLIC )
				{
					$values['album_category']->public_albums	= $values['album_category']->public_albums + 1;
				}
				else
				{
					$values['album_category']->nonpublic_albums	= $values['album_category']->nonpublic_albums + 1;
				}

				$values['album_category']->save();
			}
		}
		else
		{
			$this->postSaveIsEdit = TRUE;
			
			/* Are we changing from a private / friends only album to a public one? */
			if ( $values['album_type'] != $this->type )
			{
				/* Remember the current type */
				$this->postSaveType	= $this->type;

				/* Private to Public */
				if ( $values['album_type'] == static::AUTH_TYPE_PUBLIC )
				{
					$values['album_category']->public_albums	= $values['album_category']->public_albums + 1;
					$values['album_category']->nonpublic_albums	= $values['album_category']->nonpublic_albums - 1;
					$values['album_category']->save();
				}
				else
				{
					/* Public to Private... but only if was really public previously (we don't want to do this for private to friends-only) */
					if ( $this->type == static::AUTH_TYPE_PUBLIC )
					{
						$values['album_category']->public_albums	= $values['album_category']->public_albums - 1;
						$values['album_category']->nonpublic_albums	= $values['album_category']->nonpublic_albums + 1;
						$values['album_category']->save();
					}
				}
			}
		}

		/* Custom language fields */
		if( isset( $values['album_name'] ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_album_{$this->id}", $values['album_name'] );
			$values['name_seo']	= \IPS\Http\Url\Friendly::seoTitle( is_array( $values['album_name'] ) ? $values['album_name'][ \IPS\Lang::defaultLanguage() ] : $values['album_name'] );
		}

		if( isset( $values['album_description'] ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_album_{$this->id}_desc", $values['album_description'] );
		}

		/* Related ID */
		if( isset( $values['album_category'] ) )
		{
			$this->postSaveCategory	= $this->category_id;
			$values['category_id']	= $values['album_category']->id;
			unset( $values['album_category'] );
		}
		
		if( isset( $values['set_album_owner'] ) )
		{
			$values['owner_id']		= ( isset( $values['set_album_owner'] ) and $values['set_album_owner'] == 'me' ) ? \IPS\Member::loggedIn()->member_id : ( ( $values['album_owner'] instanceof \IPS\Member ) ? $values['album_owner']->member_id : $values['album_owner'] );

			if( !$values['owner_id'] )
			{
				$values['owner_id']	= \IPS\Member::loggedIn()->member_id;
			}
			unset( $values['set_album_owner'] );

			if( array_key_exists( 'album_owner', $values ) )
			{
				unset( $values['album_owner'] );
			}
		}
		else if( array_key_exists( 'album_owner', $values ) )
		{
			$values['owner_id']	= ( $values['album_owner'] instanceof \IPS\Member ) ? $values['album_owner']->member_id : $values['album_owner'];
			unset( $values['album_owner'] );
		}
		else if ( !$this->postSaveIsEdit )
		{
			$values['owner_id']	= \IPS\Member::loggedIn()->member_id;
		}

		/* Send to parent */
		return $values;
	}

	/**
	 * @brief	Remember if we are editing or adding
	 */
	protected $postSaveIsEdit	= FALSE;

	/**
	 * @brief	Remember previous category when editing
	 */
	protected $postSaveCategory	= 0;

	/**
	 * @brief	Remember previous album status when editing
	 */
	protected $postSaveType		= NULL;

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		/* Update counts in categories if we move the album */
		if( $this->postSaveIsEdit and $this->postSaveCategory != $values['category_id'] )
		{
			$this->moveTo( \IPS\gallery\Category::load( $values['category_id'] ), \IPS\gallery\Category::load( $this->postSaveCategory ) );
		}
		
		/* Update search index */
		if( $this->postSaveIsEdit and ( $this->postSaveType != $values['album_type'] OR $this->postSaveCategory != $values['category_id'] ) )
		{
			\IPS\Content\Search\Index::i()->massUpdate( 'IPS\gallery\Image', $this->id, NULL, $this->searchIndexPermissions() );
		}
	}

	/**
	 * Get category album belongs to
	 *
	 * @return	\IPS\gallery\Category
	 */
	public function category()
	{
		return \IPS\gallery\Category::load( $this->category_id );
	}
	
	/**
	 * Move to a different category
	 *
	 * @param	\IPS\gallery\Category		$newCategory		New category
	 * @param	\IPS\gallery\Category|NULL	$existingCategory	Old category
	 * @return	void
	 */
	public function moveTo( \IPS\gallery\Category $newCategory, \IPS\gallery\Category $existingCategory = NULL )
	{
		if ( $existingCategory === NULL )
		{
			$existingCategory = $this->category();
		}
		$this->category_id	= $newCategory->id;
		$this->save();
		
		/* Update images */
		\IPS\Db::i()->update( 'gallery_images', array( 'image_category_id' => $newCategory->id ), array( 'image_album_id=?', $this->id ) );

		/* Update categories */
		foreach ( array( $newCategory, $existingCategory ) as $category )
		{
			$category->setLastComment();
			$category->public_albums			= (int) \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', array( 'album_category_id=? and album_type=1', $category->_id ) )->first();
			$category->nonpublic_albums			= (int) \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', array( 'album_category_id=? and album_type>1', $category->_id ) )->first();
			$category->save();
		}

		/* Tags */
		\IPS\Db::i()->update( 'core_tags', array(
			'tag_aap_lookup'		=> md5( 'gallery;category;' . $newCategory->_id ),
			'tag_meta_parent_id'	=> $newCategory->_id
		), array( 'tag_meta_app=? and tag_meta_area=? and tag_meta_parent_id=?', 'gallery', 'images', $existingCategory->_id ) );
		
		\IPS\Db::i()->update( 'core_tags_perms', array(
			'tag_perm_aap_lookup'	=> md5( 'gallery;category;' . $newCategory->_id ),
			'tag_perm_text'			=> \IPS\Db::i()->select( 'perm_2', 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', 'gallery', 'category', $newCategory->_id ) )->first()
		), array( 'tag_perm_aap_lookup=?', md5( 'gallery;category;' . $existingCategory->_id ) ) );

		/* Load the item */
		$asItem = $this->asItem();

		/* Add to search index */
		\IPS\Content\Search\Index::i()->index( $asItem );

		foreach ( array( 'commentClass', 'reviewClass' ) as $class )
		{
			$className = \IPS\gallery\Album\Item::$$class;
			\IPS\Content\Search\Index::i()->massUpdate( $className, NULL, $this->id, $this->searchIndexPermissions(), NULL, $newCategory->_id );
		}

		/* Update caches */
		$asItem->expireWidgetCaches();
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=gallery&module=gallery&controller=browse&album=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'gallery_album';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_seo';

	/**
	 * Get latest image information
	 *
	 * @return	\IPS\gallery\Image|NULL
	 */
	public function lastImage()
	{
		if( !$this->last_img_id )
		{
			return NULL;
		}

		try
		{
			return \IPS\gallery\Image::load( $this->last_img_id );
		}
		catch ( \Exception $e ) /* Catch both Underflow and OutOfRange */
		{
			return NULL;
		}
	}

	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The latest comment or NULL to work it out
	 * @return	int
	 * @note	We actually want to set the last image info, not the last comment, so we ignore $comment
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		$this->setLastImage();
	}

	/**
	 * Set last review
	 *
	 * @param	\IPS\Content\Review|NULL	$review	The latest review or NULL to work it out
	 * @return	int
	 * @note	We actually want to set the last image info, not the last review, so we ignore $review
	 */
	public function setLastReview( \IPS\Content\Review $review=NULL )
	{
		$this->setLastImage();
	}

	/**
	 * Set last image data
	 *
	 * @param	\IPS\gallery\Image|NULL	$image		The latest image or NULL to work it out
	 * @param	string					$sortBy		The column to sort by for last X images (defaults to image_date DESC; third parties can override)
	 * @return	void
	 * @note	This is called from the category, so we don't need to update our parent (the category)
	 */
	public function setLastImage( \IPS\gallery\Image $image=NULL, $sortBy='image_date DESC' )
	{
		/* Figure out our latest images in this album */
		$_latestImages	= array();
		
		$this->last_img_date	= 0;
		$this->last_img_id		= 0;
		foreach( \IPS\Db::i()->select( '*', 'gallery_images', array( 'image_album_id=? AND image_approved=1', $this->id ), $sortBy, array( 0, 20 ), NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER ) as $image )
		{
			if( $image['image_date'] > $this->last_img_date )
			{
				$this->last_img_date	= $image['image_date'];
				$this->last_img_id		= $image['image_id'];
			}

			$_latestImages[]	= $image['image_id'];
		}

		$this->last_x_images	= json_encode( $_latestImages );

		/* Now get the counts and set them for our images */
		$this->_items				= \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_images', array( 'image_album_id=? AND image_approved=1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->_unapprovedItems		= \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_images', array( 'image_album_id=? AND image_approved=0', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();

		$this->_comments			= \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_comments', array( 'gallery_images.image_album_id=? AND comment_approved=1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->join( 'gallery_images', 'image_id=comment_img_id' )->first();
		$this->_unapprovedComments	= \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_comments', array( 'gallery_images.image_album_id=? AND comment_approved=0', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->join( 'gallery_images', 'image_id=comment_img_id' )->first();
		
		$this->_reviews				= \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_reviews', array( 'gallery_images.image_album_id=? AND review_approved=1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->join( 'gallery_images', 'image_id=review_image_id' )->first();
		$this->_unapprovedReviews	= \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_reviews', array( 'gallery_images.image_album_id=? AND review_approved=0', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->join( 'gallery_images', 'image_id=review_image_id' )->first();

		/* And then get counts/latest data for the direct comments/reviews */
		$this->comments				= (int) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_album_comments', array( 'comment_album_id=? AND comment_approved=1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->comments_unapproved	= (int) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_album_comments', array( 'comment_album_id=? AND comment_approved=0', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->comments_hidden		= (int) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_album_comments', array( 'comment_album_id=? AND comment_approved=-1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		
		$this->reviews				= (int) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_album_reviews', array( 'review_album_id=? AND review_approved=1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->reviews_unapproved	= (int) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_album_reviews', array( 'review_album_id=? AND review_approved=0', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$this->reviews_hidden		= (int) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_album_reviews', array( 'review_album_id=? AND review_approved=-1', $this->id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();

		/* Save and then make sure search index is updated */
		\IPS\Content\Search\Index::i()->index( $this->asItem() );
	}
	
	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit	Number of attachments to fetch, or NULL for all
	 *
	 * @return	array|NULL	If array, then array( 'core_Attachment' => 'month_x/foo.gif', ... );
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL )
	{
		$images = array();
		
		foreach( \IPS\gallery\Image::getItemsWithPermission( array( array( 'image_album_id=?', $this->id ) ), NULL, $limit ? $limit : 10 ) as $image )
		{
			$images[] = array( 'gallery_Images' => $image->medium_file_name );
		}
		
		return count( $images ) ? $images : NULL;
	}
	
	/**
	 * Retrieve the latest images
	 *
	 * @return	array
	 */
	public function get__latestImages()
	{
		$_latestImages	= json_decode( $this->last_x_images, TRUE );

		if( !count( $_latestImages ) )
		{
			return array();
		}

		return \IPS\gallery\Image::getItemsWithPermission( array( array( 'image_id IN(' . implode( ',', $_latestImages ) . ')' ) ), NULL, 20 );
	}

	/**
	 * @brief	Cached calendar events
	 */
	protected $_events	= NULL;

	/**
	 * Get any associated calendar events
	 *
	 * @return	array
	 */
	public function get__event()
	{
		if( $this->_events !== NULL )
		{
			return $this->_events;
		}

		if( \IPS\Application::appIsEnabled( 'calendar' ) )
		{
			try
			{
				$events	= iterator_to_array( \IPS\calendar\Event::getItemsWithPermission( array( array( 'event_album=?', $this->id ) ) ) );

				if( !count( $events ) )
				{
					$this->_events	= array();
					return $this->_events;
				}

				\IPS\calendar\Calendar::addCss();
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar.css', 'calendar', 'front' ) );

				$this->_events	= $events;
				return $this->_events;
			}
			catch( \OutOfRangeException $e ){}
		}

		$this->_events	= array();
		return $this->_events;
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\File::unclaimAttachments( 'gallery_Albums', $this->id );
		parent::delete();

		\IPS\Lang::deleteCustom( 'gallery', "gallery_album_{$this->id}" );
		\IPS\Lang::deleteCustom( 'gallery', "gallery_album_{$this->id}_desc" );
		
		/* If there was a social group saved, delete it */
		if( $this->allowed_access )
		{
			\IPS\Db::i()->delete( 'core_sys_social_groups', array( 'group_id=?', $this->allowed_access ) );
			\IPS\Db::i()->delete( 'core_sys_social_group_members', array( 'group_id=?', $this->allowed_access ) );
		}

		/* If any calendar events are associated, unassociate */
		if( \IPS\Application::appIsEnabled( 'calendar' ) )
		{
			\IPS\Db::i()->update( 'calendar_events', array( 'event_album' => 0 ), array( 'event_album=?', $this->id ) );
		}

		/* Delete as an item to remove comments, meta data, search index, reviews, etc. */
		$this->asItem()->delete();

		/* Update category information */
		if( $this->type == static::AUTH_TYPE_PUBLIC )
		{
			$this->category()->public_albums	= $this->category()->public_albums - 1;
		}
		else if( $this->type == static::AUTH_TYPE_PRIVATE or $this->type == static::AUTH_TYPE_PRIVATE )
		{
			$this->category()->nonpublic_albums	= $this->category()->nonpublic_albums - 1;
		}

		$this->category()->save();
	}

	/**
	 * Retrieve the content item count
	 *
	 * @return	null|int
	 */
	public function getContentItemCount()
	{
		$contentItemClass = static::$contentItemClass;
		return (int) \IPS\Db::i()->select( 'COUNT(*)', $contentItemClass::$databaseTable, array( $contentItemClass::$databasePrefix . 'album_id=?', $this->id ) )->first();
	}
	
	/**
	 * Retrieve content items (if applicable) for a node.
	 *
	 * @param	int		$limit			The limit
	 * @param	int		$offset			The offset
	 * @param	array	$additional		Where Additional where clauses
	 * @param	int		$countOnly		If TRUE, will get the number of results
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 * @throws	\BadMethodCallException
	 */
	public function getContentItems( $limit, $offset, $additionalWhere = array(), $countOnly=FALSE )
	{
		if ( !isset( static::$contentItemClass ) )
		{
			throw new \BadMethodCallException;
		}

		$contentItemClass = static::$contentItemClass;

		$where		= array();
		$where[]	= array( $contentItemClass::$databasePrefix . 'album_id=?', $this->_id );

		if ( count( $additionalWhere ) )
		{
			foreach( $additionalWhere AS $clause )
			{
				$where[] = $clause;
			}
		}

		if ( $countOnly )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', $contentItemClass::$databaseTable, $where )->first();
		}
		else
		{
			$limit	= ( $offset !== NULL ) ? array( $offset, $limit ) : NULL;
			return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $contentItemClass::$databaseTable, $where, $contentItemClass::$databasePrefix . $contentItemClass::$databaseColumnId, $limit ), $contentItemClass );
		}
	}

	/**
	 * Text for use with data-ipsTruncate
	 * Returns the post with paragraphs turned into line breaks
	 *
	 * @return	string
	 */
	public function truncated()
	{
		$text = \IPS\Text\Parser::removeElements( $this->description, array( 'blockquote' ) );
		$text = str_replace( array( '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>' ), '<br>', $text );
		$text = strip_tags( str_replace( ">", "> ", $text ), '<br>' );

		return $text;
	}

	/**
	 * @brief	Cached cover photo
	 */
	protected $coverPhoto	= NULL;

	/**
	 * Retrieve a cover photo
	 *
	 * @param	string	$size	One of full, medium, small or thumb
	 * @return	string|null
	 */
	public function coverPhoto( $size='thumb' )
	{
		/* Can we view the images in this album? */
		if ( !$this->can( 'read' ) )
		{
			return NULL;
		}

		if( $size == 'full' )
		{
			$size = 'masked';
		}
		
		$property = $size . "_file_name";

		if( !$this->cover_img_id )
		{
			if( $lastImage = $this->lastImage() )
			{
				return (string) \IPS\File::get( 'gallery_Images', $lastImage->$property )->url;
			}

			return NULL;
		}

		if( !in_array( $size, array( 'masked', 'medium', 'small', 'thumb' ) ) )
		{
			throw new \InvalidArgumentException;
		}

		if( $this->coverPhoto === NULL )
		{
			$this->coverPhoto	= \IPS\gallery\Image::load( $this->cover_img_id );
		}

		return (string) \IPS\File::get( 'gallery_Images', $this->coverPhoto->$property )->url;
	}

	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		$qs = array_merge( $url->queryString, $url->hiddenQueryString );
		
		if ( isset( $qs['album'] ) )
		{
			if ( method_exists( get_called_class(), 'loadAndCheckPerms' ) )
			{
				return static::loadAndCheckPerms( $qs['album'] );
			}
			else
			{
				return static::load( $qs['album'] );
			}
		}
		
		throw new \InvalidArgumentException;
	}

	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		if( static::restrictionCheck( 'delete' ) )
		{
			return TRUE;
		}

		if( $this->owner_id == \IPS\Member::loggedIn()->member_id AND \IPS\Member::loggedIn()->group['g_delete_own_albums'] )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Check permissions
	 *
	 * @param	mixed								$permission		A key which has a value in static::$permissionMap['view'] matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member			The member or group to check (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in static::$permissionMap
	 * @note	Albums don't have permissions, but instead check against the category they are in
	 */
	public function can( $permission, $member=NULL )
	{
		/* Load the item */
		$asItem = $this->asItem();

		/* Albums can be hidden, so make sure to test permissions */
		if( $asItem->hidden() )
		{
			if( !$asItem->canView( $member ) )
			{
				return FALSE;
			}

			$methodToCheck = "can" . \ucwords( $permission );

			if( method_exists( $asItem, $methodToCheck ) )
			{
				return $asItem->$methodToCheck( $member );
			}
			else
			{
				return $asItem->can( $permission, $member );
			}
		}

		/* We'll just rely on category permissions if the album isn't hidden */
		try
		{
			return $this->category()->can( $permission, $member );
		}
		catch( \OutOfRangeException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		$return = $this->category()->searchIndexPermissions();
		
		if ( $this->type != static::AUTH_TYPE_PUBLIC )
		{
			$return = ( $return === '*' ) ? array() : explode( ',', $return );
			
			if ( $this->owner_id )
			{
				$return[] = "m{$this->owner_id}";
			}
			
			if ( $this->type == static::AUTH_TYPE_RESTRICTED )
			{
				$return[] = "s{$this->allowed_access}";
			}
			
			$return = implode( ',', array_unique( $return ) );
		}
		return $return;
	}

	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		if( parent::canRate( $member ) )
		{
			if( $this->category()->allow_rating )
			{
				return $this->category()->can( 'rate', $member );
			}
			else
			{
				return FALSE;
			}
		}

		return FALSE;
	}

	/**
	 * Get template for node tables
	 *
	 * @return	callable
	 */
	public static function nodeTableTemplate()
	{
		\IPS\gallery\Application::outputCss();
		
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'albums' );
	}
	
	/**
	 * Get output for API
	 *
	 * @return	array
	 * @apiresponse	int						id					ID number
	 * @apiresponse	string					name				Name
	 * @apiresponse	string					description			Description
	 * @apiresponse	\IPS\gallery\Category	category			The category
	 * @apiresponse	\IPS\Member				owner				The owner
	 * @apiresponse	string					privacy				'public', 'private' (can only be viewed by owner) or 'restricted' (can only be viewed by owner or approved members)
	 * @apiresponse	[\IPS\Member]			approvedMembers		If the album is restricted, the members who can view it, in addition to the owner and moderators with appropriate permission
	 * @apiresponse	int						images				Number of images
	 * @apiresponse	string					url					URL
	 */
	public function apiOutput()
	{
		return array(
			'id'				=> $this->id,
			'name'				=> $this->name,
			'description'		=> $this->description,
			'category'			=> $this->category()->apiOutput(),
			'owner'				=> $this->owner()->apiOutput(),
			'privacy'			=> ( $this->type == static::AUTH_TYPE_PUBLIC ) ? 'public' : ( $this->type == static::AUTH_TYPE_PRIVATE ? 'private' : ( $this->type == static::AUTH_TYPE_RESTRICTED ? 'restricted' : null ) ),
			'approvedMembers'	=> $this->approvedMembers ? array_map( function( $val ) {
				return $val->apiOutput();
			}, $this->approvedMembers ) : null,
			'images'			=> $this->count_imgs,
			'url'				=> (string) $this->url()
		);
	}

	/**
	 * Get template for managing this nodes follows
	 *
	 * @return	callable
	 */
	public static function manageFollowNodeRow()
	{
		\IPS\gallery\Application::outputCss();
		
		return array( \IPS\Theme::i()->getTemplate( 'global', 'gallery' ), 'manageFollowNodeRow' );
	}
}