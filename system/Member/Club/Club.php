<?php
/**
 * @brief		Club Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Feb 2017
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Club Model
 */
class _Club extends \IPS\Patterns\ActiveRecord implements \IPS\Content\Embeddable
{	
	const TYPE_PUBLIC = 'public';
	const TYPE_OPEN = 'open';
	const TYPE_CLOSED = 'closed';
	const TYPE_PRIVATE = 'private';
	
	const STATUS_MEMBER = 'member';
	const STATUS_INVITED = 'invited';
	const STATUS_REQUESTED = 'requested';
	const STATUS_DECLINED = 'declined';
	const STATUS_BANNED = 'banned';
	const STATUS_MODERATOR = 'moderator';
	const STATUS_LEADER = 'leader';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_clubs';
	
	/* !Fetch Clubs */
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$return = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		
		if ( isset( $data['member_id'] ) and isset( $data['status'] ) )
		{
			$return->_memberStatuses[ $data['member_id'] ] = $data['status'];
		}
		
		return $return;
	}
		
	/**
	 * Get all clubs a member can see
	 *
	 * @param	\IPS\Member				$member		The member to base permission off or NULL for all clubs
	 * @param	int						$limit		Number to get
	 * @param	string					$sortOption	The sort option ('last_activity', 'members', 'content' or 'created')
	 * @param	bool|\IPS\Member		$mineOnly	Limit to clubs a particular member has joined (TRUE to use the same value as $member)
	 * @param	array					$filters	Custom field filters
	 * @param	mixed					$extraWhere	Additional WHERE clause
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public static function clubs( \IPS\Member $member = NULL, $limit, $sortOption, $mineOnly=FALSE, $filters=array(), $extraWhere=NULL )
	{
		$where = array();
		$joins = array();
		
		/* Restrict to clubs we can see */
		if ( $member and !$member->modPermission('can_access_all_clubs') )
		{
			/* Exclude clubs which are pending approval, unless we are the owner */
			if ( \IPS\Settings::i()->clubs_require_approval )
			{
				$where[] = array( '( approved=1 OR owner=? )', $member->member_id );
			}
			
			/* Specify our memberships */
			if ( $member->member_id )
			{
				$joins['membership'] = array( array( 'core_clubs_memberships', 'membership' ), array( 'membership.club_id=core_clubs.id AND membership.member_id=?', $member->member_id ) );
				$where[] = array( "( type<>? OR membership.status IN('" . static::STATUS_MEMBER .  "','" . static::STATUS_MODERATOR . "','" . static::STATUS_LEADER . "') )", static::TYPE_PRIVATE );
			}
			else
			{
				$where[] = array( 'type<>?', static::TYPE_PRIVATE );
			}
		}
		
		/* Restrict to clubs we have joined */
		if ( $mineOnly )
		{
			$mineOnly = ( $mineOnly === TRUE ) ? $member : $mineOnly;
			if ( !$mineOnly->member_id )
			{
				return array();
			}
			
			if ( $member and $mineOnly->member_id === $member->member_id and isset( $joins['membership'] ) )
			{
				$where[] = array( "membership.status IN('" . static::STATUS_MEMBER .  "','" . static::STATUS_MODERATOR . "','" . static::STATUS_LEADER . "')" );
			}
			else
			{
				$joins['others_membership'] = array( array( 'core_clubs_memberships', 'others_membership' ), array( 'others_membership.club_id=core_clubs.id AND others_membership.member_id=?', $mineOnly->member_id ) );
				$where[] = array( "others_membership.status IN('" . static::STATUS_MEMBER .  "','" . static::STATUS_MODERATOR . "','" . static::STATUS_LEADER . "')" );
			}
		}
		
		/* Other filters */
		if ( $filters )
		{
			$joins['core_clubs_fieldvalues'] = array( 'core_clubs_fieldvalues', array( 'core_clubs_fieldvalues.club_id=core_clubs.id' ) );
			foreach ( $filters as $k => $v )
			{
				if ( is_array( $v ) )
				{
					$where[] = array( \IPS\Db::i()->findInSet( "field_{$k}", $v ) );
				}
				else
				{
					$where[] = array( "field_{$k}=?", $v );
				}
			}
		}
		
		/* Additional where clause */
		if ( $extraWhere )
		{
			if ( is_array( $extraWhere ) )
			{
				$where = array_merge( $where, $extraWhere );
			}
			else
			{
				$where[] = array( $extraWhere );
			}
		}
		
		/* Query */
		$select = \IPS\Db::i()->select( '*', 'core_clubs', $where, ( $sortOption === 'name' ? "{$sortOption} ASC" : "{$sortOption} DESC" ), $limit, NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		foreach ( $joins as $join )
		{
			$select->join( $join[0], $join[1] );
		}
		$select->setKeyField( 'id' );

		/* Return */
		return new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\Member\Club' );
	}	
	
	/**
	 * Get number clubs a member is leader of
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	int
	 */
	public static function numberOfClubsMemberIsLeaderOf( \IPS\Member $member )
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_memberships', array( 'member_id=? AND status=?', $member->member_id, static::STATUS_LEADER ) );
	}
		
	/* !ActiveRecord */
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->type = static::TYPE_OPEN;
		$this->created = new \IPS\DateTime;
		$this->last_activity = time();
		$this->members = 1;
		$this->owner = NULL;
		$this->approved = \IPS\Settings::i()->clubs_require_approval ? 0 : 1;
	}
	
	/**
	 * Get owner
	 *
	 * @return	\IPS\Member|NULL
	 */
	public function get_owner()
	{
		try
		{
			$owner = \IPS\Member::load( $this->_data['owner'] );
			return $owner->member_id ? $owner : NULL;
		}
		catch( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set member
	 *
	 * @param	\IPS\Member
	 * @return	void
	 */
	public function set_owner( \IPS\Member $owner = NULL )
	{
		$this->_data['owner'] = $owner ? ( (int) $owner->member_id ) : NULL;
	}
	
	/**
	 * Get created date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_created()
	{
		return \IPS\DateTime::ts( $this->_data['created'] );
	}
	
	/**
	 * Set created date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_created( \IPS\DateTime $date )
	{
		$this->_data['created'] = $date->getTimestamp();
	}
			
	/**
	 * Get club URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		return \IPS\Http\Url::internal( "app=core&module=clubs&controller=view&id={$this->id}", 'front', 'clubs_view', \IPS\Http\Url\Friendly::seoTitle( $this->name ) );
	}
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		return array( 'id', 'name' );
	}
	
	/**
	 * Edit Club Form
	 *
	 * @param	bool	$acp			TRUE if editing in the ACP
	 * @param	bool	$new			TRUE if creating new
	 * @param	array	$availableTypes	If creating new, the available types
	 * @return	\IPS\Helpers\Form|NULL
	 */
	public function form( $acp=FALSE, $new=FALSE, $availableTypes=NULL )
	{
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Text( 'club_name', $this->name, TRUE, array( 'maxLength' => 255 ) ) );
		
		if ( $acp or ( $new and count( $availableTypes ) > 1 ) )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_type', $this->type, TRUE, array( 'options' => $new ? $availableTypes : array(
				\IPS\Member\Club::TYPE_PUBLIC	=> 'club_type_' . \IPS\Member\Club::TYPE_PUBLIC,
				\IPS\Member\Club::TYPE_OPEN	=> 'club_type_' . \IPS\Member\Club::TYPE_OPEN,
				\IPS\Member\Club::TYPE_CLOSED	=> 'club_type_' . \IPS\Member\Club::TYPE_CLOSED,
				\IPS\Member\Club::TYPE_PRIVATE	=> 'club_type_' . \IPS\Member\Club::TYPE_PRIVATE,
			) ) ) );
			
			if ( $acp )
			{
				$form->add( new \IPS\Helpers\Form\Member( 'club_owner', $this->owner, TRUE ) );
			}
		}
		
		$form->add( new \IPS\Helpers\Form\TextArea( 'club_about', $this->about ) );
		
		$form->add( new \IPS\Helpers\Form\Upload( 'club_profile_photo', $this->profile_photo ? \IPS\File::get( 'core_Clubs', $this->profile_photo ) : NULL, FALSE, array( 'storageExtension' => 'core_Clubs', 'image' => array( 'maxWidth' => 200, 'maxHeight' => 200 ) ) ) );
		
		if ( \IPS\Settings::i()->clubs_locations )
		{
			$form->add( new \IPS\Helpers\Form\Address( 'club_location', $this->location_json ? \IPS\GeoLocation::buildFromJson( $this->location_json ) : NULL, FALSE, array( 'requireFullAddress' => FALSE ) ) );
		}
		
		$fieldValues = $this->fieldValues();
		foreach ( \IPS\Member\Club\CustomField::roots() as $field )
		{
			$form->add( $field->buildHelper( isset( $fieldValues["field_{$field->id}"] ) ? $fieldValues["field_{$field->id}"] : NULL ) );
		}
		
		if ( $values = $form->values() )
		{
			$this->name = $values['club_name'];

			/* If there is only one type available, set it. */
			if( count( $availableTypes ) == 1 )
			{
				$values['club_type'] = key( $availableTypes );
			}

			$needToUpdatePermissions = FALSE;			
			if ( $acp )
			{
				if ( $this->type != $values['club_type'] )
				{
					$this->type = $values['club_type'];
					$needToUpdatePermissions = TRUE;
				}
				if ( $this->owner != $values['club_owner'] )
				{
					$this->owner = $values['club_owner'];
					$this->addMember( $values['club_owner'], \IPS\Member\Club::STATUS_LEADER, TRUE );
				}
			}
			elseif ( $new )
			{
				$this->type = $values['club_type'];
				$this->owner = \IPS\Member::loggedIn();
			}
			
			$this->about = $values['club_about'];
			$this->profile_photo = (string) $values['club_profile_photo'];
			
			if ( isset( $values['club_location'] ) )
			{
				$this->location_json = json_encode( $values['club_location'] );
				if ( $values['club_location']->lat and $values['club_location']->long )
				{
					$this->location_lat = $values['club_location']->lat;
					$this->location_long = $values['club_location']->long;
				}
				else
				{
					$this->location_lat = NULL;
					$this->location_long = NULL;
				}
			}
			else
			{
				$this->location_json = NULL;
				$this->location_lat = NULL;
				$this->location_long = NULL;
			}
			
			$this->save();
			
			if ( $new )
			{
				$this->addMember( \IPS\Member::loggedIn(), \IPS\Member\Club::STATUS_LEADER );
			}
			$this->recountMembers();
			
			$customFieldValues = array();
			foreach ( \IPS\Member\Club\CustomField::roots() as $field )
			{
				if ( isset( $values["core_clubfield_{$field->id}"] ) )
				{
					$helper							 			= $field->buildHelper();
					
					if ( $helper instanceof \IPS\Helpers\Form\Upload )
					{
						$customFieldValues[ "field_{$field->id}" ] = (string) $values["core_clubfield_{$field->id}"];
					}
					else
					{
						$customFieldValues[ "field_{$field->id}" ]	= $helper::stringValue( $values["core_clubfield_{$field->id}"] );
					}
					
					if ( $field->type === 'Editor' )
					{
						$field->claimAttachments( $this->id );
					}
				}
			}
			if ( count( $customFieldValues ) )
			{
				$customFieldValues['club_id'] = $this->id;
				\IPS\Db::i()->insert( 'core_clubs_fieldvalues', $customFieldValues, TRUE );
			}
						
			if ( $needToUpdatePermissions )
			{
				foreach ( $this->nodes() as $node )
				{
					try
					{
						$nodeClass = $node['node_class'];
						$node = $nodeClass::load( $node['node_id'] );
						$node->setPermissionsToClub( $this );
					}
					catch ( \Exception $e ) { }
				}
			}
			
			return NULL;
		}
		
		return $form;
	}
	
	/**
	 * Custom Field Values
	 *
	 * @return	array
	 */
	public function fieldValues()
	{
		try
		{
			return \IPS\Db::i()->select( '*', 'core_clubs_fieldvalues', array( 'club_id=?', $this->id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			return array();
		}
	}
	
	/**
	 * Cover Photo
	 *
	 * @param	bool	$getOverlay	If FALSE, will not set the overlay, which saves queries if it will not be used (such as in clubCard)
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto( $getOverlay=TRUE, $position='full' )
	{
		$photo = new \IPS\Helpers\CoverPhoto;
		if ( $this->cover_photo )
		{
			$photo->file = \IPS\File::get( 'core_Clubs', $this->cover_photo );
			$photo->offset = $this->cover_offset;
		}
		if ( $getOverlay )
		{
			$photo->overlay = \IPS\Theme::i()->getTemplate( 'clubs', 'core', 'front' )->coverPhotoOverlay( $this, $position );
		}
		$photo->editable = $this->isLeader();
		$photo->object = $this;
		return $photo;
	}
	
	/**
	 * Location
	 *
	 * @return	\IPS\GeoLocation|NULL
	 */
	public function location()
	{
		if ( $this->location_json )
		{
			return \IPS\GeoLocation::buildFromJson( $this->location_json );
		}
		return NULL;
	}
		
	/* !Manage Memberships */
		
	/**
	 * Get members
	 *
	 * @param	array	$statuses			The membership statuses to get
	 * @param	int		$limit				Number to get
	 * @param	string	$order				ORDER BY clause
	 * @param	int		$returnType			0 = core_clubs_memberships rows, 1 = core_clubs_memberships plus \IPS\Member::columnsForPhoto(), 2 = full core_members rows, 3 = same as 1 but also getting name of adder/invitee, 4 = count only
	 * @return	\IPS\Db\Select|int
	 */
	public function members( $statuses = array( 'member', 'moderator', 'leader' ), $limit = 25, $order = 'core_clubs_memberships.joined ASC', $returnType = 1 )
	{	
		if ( $returnType === 4 )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_memberships', array( array( 'club_id=?', $this->id ), array( \IPS\Db::i()->in( 'status', $statuses ) ) ) )->first();
		}
		else
		{
			if ( $returnType === 2 )
			{
				$columns = 'core_members.*';
			}
			else
			{
				$columns = 'core_clubs_memberships.member_id,core_clubs_memberships.joined,core_clubs_memberships.status,core_clubs_memberships.added_by,core_clubs_memberships.invited_by';
				if ( $returnType === 1 or $returnType === 3 )
				{
					$columns .= ',' . implode( ',', array_map( function( $column ) {
						return 'core_members.' . $column;
					}, \IPS\Member::columnsForPhoto() ) );
				}
				if ( $returnType === 3 )
				{
					$columns .= ',added_by.name,invited_by.name';
				}
			}
			
			$select = \IPS\Db::i()->select( $columns, 'core_clubs_memberships', array( array( 'club_id=?', $this->id ), array( \IPS\Db::i()->in( 'status', $statuses ) ) ), $order, $limit, NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS + \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS );
		}
		
		if ( $returnType === 1 or $returnType === 2 or $returnType === 3 )
		{
			$select->join( 'core_members', 'core_members.member_id=core_clubs_memberships.member_id' );
		}
		if ( $returnType === 3 )
		{
			$select->join( array( 'core_members', 'added_by' ), 'added_by.member_id=core_clubs_memberships.added_by' );
			$select->join( array( 'core_members', 'invited_by' ), 'invited_by.member_id=core_clubs_memberships.invited_by' );
		}
						
		return $select;
	}	
	
	/**
	 * @brief	Cache of randomTenMembers()
	 */
	protected $_randomTenMembers = NULL;
	
	/**
	 * Get basic data of a random ten members in the club (for cards)
	 *
	 * @return	array
	 */
	public function randomTenMembers()
	{
		if ( !isset( $this->_randomTenMembers ) )
		{
			$this->_randomTenMembers = iterator_to_array( $this->members( array( 'leader', 'moderator', 'member' ), 10, 'RAND()' ) );
		}
		return $this->_randomTenMembers;
	}
	
	/**
	 * Add a member
	 *
	 * @param	\IPS\Member			$member		The member
	 * @param	bool				$status		Status
	 * @param	bool				$update		Update membership if already a member?
	 * @param	\IPS\Member|NULL	$addedBy	The leader who added them, or NULL if joining themselves
	 * @param	\IPS\Member|NULL	$invitedBy	The member who invited them, or NULL if joining themselves
	 * @param	bool				$updateJoinedDate	Whether to update the joined date or not (FALSE by default, set to TRUE when an invited member accepts)
	 * @return	void
	 * @throws	\OverflowException	Member is already in the club and $update was FALSE
	 */
	public function addMember( \IPS\Member $member, $status = 'member', $update = FALSE, \IPS\Member $addedBy = NULL, \IPS\Member $invitedBy = NULL, $updateJoinedDate = FALSE )
	{
		try
		{
			\IPS\Db::i()->insert( 'core_clubs_memberships', array(
				'club_id'	=> $this->id,
				'member_id'	=> $member->member_id,
				'joined'	=> time(),
				'status'	=> $status,
				'added_by'	=> $addedBy ? $addedBy->member_id : NULL,
				'invited_by'=> $invitedBy ? $invitedBy->member_id : NULL
			) );

			$member->rebuildPermissionArray();
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( $e->getCode() === 1062 )
			{
				if ( $update )
				{
					$save = array( 'status'	=> $status );
					if ( $addedBy )
					{
						$save['added_by'] = $addedBy->member_id;
					}

					if( $updateJoinedDate === TRUE )
					{
						$save['joined']	= time();
					}
					
					\IPS\Db::i()->update( 'core_clubs_memberships', $save, array( 'club_id=? AND member_id=?', $this->id, $member->member_id ) );
					
					$member->rebuildPermissionArray();
				}
				else
				{
					throw new \OverflowException;
				}
			}
			else
			{
				throw $e;
			}			
		}
	}
	
	/**
	 * Remove a member
	 *
	 * @param	\IPS\Member	$member		The member
	 * @return	void
	 */
	public function removeMember( \IPS\Member $member )
	{
		\IPS\Db::i()->delete( 'core_clubs_memberships', array( 'club_id=? AND member_id=?', $this->id, $member->member_id ) );
		$member->rebuildPermissionArray();
	}
	
	/**
	 * Recount members
	 *
	 * @return	void
	 */
	public function recountMembers()
	{
		$this->members = \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_memberships', array( 'club_id=? AND ( status=? OR status=? OR status=? )', $this->id, static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) );
		$this->save();
	}
	
	/* !Manage Nodes */
	
	/**
	 * Get available features
	 *
	 * @param	\IPS\Member|NULL	If a member object is provided, will opnly get the types that member can create
	 * @return	array
	 */
	public static function availableNodeTypes( \IPS\Member $member = NULL )
	{
		$return = array();
						
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter' ) as $contentRouter )
		{
			foreach ( $contentRouter->classes as $class )
			{
				if ( isset( $class::$containerNodeClass ) and \IPS\IPS::classUsesTrait( $class::$containerNodeClass, 'IPS\Content\ClubContainer' ) )
				{					
					if ( $member === NULL or $member->group['g_club_allowed_nodes'] === '*' or in_array( $class::$containerNodeClass, explode( ',', $member->group['g_club_allowed_nodes'] ) ) )
					{
						$return[] = $class::$containerNodeClass;
					}
				}
			}
		}
				
		return array_unique( $return );
	}
	
	/**
	 * Get Node names and URLs
	 *
	 * @return	array
	 */
	public function nodes()
	{
		$return = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_clubs_node_map', array( 'club_id=?', $this->id ) ) as $row )
		{
			$class		= $row['node_class'];
			$classBits	= explode( '\\', $class );

			if( !\IPS\Application::load( $classBits[1] )->_enabled )
			{
				continue;
			}
			
			$return[ $row['id'] ] = array(
				'name'			=> $row['name'],
				'url'			=> \IPS\Http\Url::internal( $class::$urlBase . $row['node_id'], 'front', $class::$urlTemplate, array( \IPS\Http\Url\Friendly::seoTitle( $row['name'] ) ) ),
				'node_class'	=> $row['node_class'],
				'node_id'		=> $row['node_id'],
			);
		}
		
		return $return;
	}
	
	/* !Permissions */
	
	/**
	 * Can a member see this club and who's in it?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canView( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* If we can't access the module, stop here */
		if ( !$member->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
		{
			return FALSE;
		}

		/* If it's not approved, only moderators and the person who created it can see it */
		if ( \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			return ( $member->modPermission('can_access_all_clubs') or ( $this->owner AND $member->member_id == $this->owner->member_id ) );
		}
		
		/* Unless it's private, everyone can see it exists */
		if ( $this->type !== static::TYPE_PRIVATE )
		{
			return TRUE;
		}
		
		/* Moderators can see everything */
		if ( $member->modPermission('can_access_all_clubs') )
		{
			return TRUE;
		}
				
		/* Otherwise, only if they're a member or have been invited */		
		return in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_INVITED ) );
	}
	
	/**
	 * Can a member join (or ask to join) this club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canJoin( \IPS\Member $member = NULL )
	{
		/* If it's not approved, nobody can join it */
		if ( \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			return FALSE;
		}
		
		/* Nobody can join public clubs */
		if ( $this->type === static::TYPE_PUBLIC )
		{
			return FALSE;
		}
		
		/* Guests cannot join clubs */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* If they're already a member, or have aleready asked to join, they can't join again */
		$memberStatus = $this->memberStatus( $member );
		if ( in_array( $memberStatus, array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_REQUESTED, static::STATUS_DECLINED ) ) )
		{
			return FALSE;
		}

		/* If they are banned, they cannot join */
		if ( $memberStatus === static::STATUS_BANNED )
		{
			return FALSE;
		}
		
		/* If it's private, they have to be invited */
		if ( $this->type === static::TYPE_PRIVATE )
		{
			return $memberStatus === static::STATUS_INVITED;
		}
		
		/* Otherwise they can join */
		return TRUE;
	}
	
	/**
	 * Can a member see the posts in this club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canRead( \IPS\Member $member = NULL )
	{
		switch ( $this->type )
		{
			case static::TYPE_PUBLIC:
			case static::TYPE_OPEN:
				return TRUE;
				
			case static::TYPE_CLOSED:
			case static::TYPE_PRIVATE:
				$member = $member ?: \IPS\Member::loggedIn();
				return $member->modPermission('can_access_all_clubs') or in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) );
		}
	}
	
	/**
	 * Can a member participate this club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canPost( \IPS\Member $member = NULL )
	{
		switch ( $this->type )
		{
			case static::TYPE_PUBLIC:
				return TRUE;
				
			case static::TYPE_OPEN:
			case static::TYPE_CLOSED:
			case static::TYPE_PRIVATE:
				$member = $member ?: \IPS\Member::loggedIn();
				return $member->modPermission('can_access_all_clubs') or in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) );
		}
	}
	
	/**
	 * Can a member invite other members
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canInvite( \IPS\Member $member = NULL )
	{
		if ( \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			return FALSE;
		}
		
		switch ( $this->type )
		{
			case static::TYPE_PUBLIC:
				return FALSE;
				
			case static::TYPE_OPEN:
				$member = $member ?: \IPS\Member::loggedIn();
				return $member->modPermission('can_access_all_clubs') or in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) );
				
			case static::TYPE_CLOSED:
			case static::TYPE_PRIVATE:
				return $this->isLeader( $member );
		}
	}
	
	/**
	 * Does this user have leader permissions in the club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function isLeader( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->modPermission('can_access_all_clubs') or $this->memberStatus( $member ) === static::STATUS_LEADER;
	}
	
	/**
	 * Does this user have moderator permissions in the club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function isModerator( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->modPermission('can_access_all_clubs') or in_array( $this->memberStatus( $member ), array( static::STATUS_MODERATOR, static::STATUS_LEADER ) );
	}

	
	/**
	 * @brief	Membership status cache
	 */
	protected $_memberStatuses = array();
	
	/**
	 * Get status of a particular member
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	int			$returnType	1 will return a string with the type or NULL if not applicable. 2 will return array with status, joined, accepted_by, invited_by
	 * @return	mixed
	 */
	public function memberStatus( \IPS\Member $member, $returnType = 1 )
	{
		if ( !$member->member_id )
		{
			return NULL;
		}
				
		if ( !isset( $this->_memberStatuses[ $member->member_id ] ) or $returnType === 2 )
		{
			try
			{
				$val = \IPS\Db::i()->select( $returnType === 2 ? '*' : array( 'status' ), 'core_clubs_memberships', array( 'club_id=? AND member_id=?', $this->id, $member->member_id ) )->first();
				
				if ( $returnType === 2 )
				{
					return $val;
				}
				else
				{
					$this->_memberStatuses[ $member->member_id ] = $val;
				}
			}
			catch ( \UnderflowException $e )
			{
				$this->_memberStatuses[ $member->member_id ] = NULL;
			}
		}
		
		return $this->_memberStatuses[ $member->member_id ];
	}
	
	/* ! Utility */
	
	/**
	 * Remove nodes that are owned by a specific application. Used when uninstalling an app
	 *
	 * @param	\IPS\Application	$app	The application being deleted
	 * @return void
	 */
	public static function deleteByApplication( \IPS\Application $app )
	{
		foreach( \IPS\Db::i()->select( 'node_class', 'core_clubs_node_map', NULL, NULL, NULL, 'node_class' ) as $class )
		{
			if ( isset( $class::$contentItemClass ) )
			{
				$contentItemClass = $class::$contentItemClass;

				if ( $contentItemClass::$application == $app->directory )
				{
					\IPS\Db::i()->delete( 'core_clubs_node_map', array( 'node_class=?', $class  ) );
				}
			}
		}
	}
}