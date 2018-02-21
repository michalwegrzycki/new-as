<?php
/**
 * @brief		Trait for Content Containers which can be used in Clubs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Feb 2017
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Trait for Content Containers which can be used in Clubs
 */
trait ClubContainer
{
	/**
	 * Get the database column which stores the club ID
	 *
	 * @return	string
	 */
	public static function clubIdColumn()
	{
		return 'club_id';
	}
	
	/**
	 * Get front-end language string
	 *
	 * @return	string
	 */
	public static function clubFrontTitle()
	{
		$itemClass = static::$contentItemClass;
		return $itemClass::$title . '_pl';
	}
	
	/**
	 * Get acp language string
	 *
	 * @return	string
	 */
	public static function clubAcpTitle()
	{
		return static::$nodeTitle;
	}
	
	/**
	 * Get the associated club
	 *
	 * @return	\IPS\Member\Club|NULL
	 */
	public function club()
	{
		if ( \IPS\Settings::i()->clubs )
		{
			$clubIdColumn = $this->clubIdColumn();
			if ( $this->$clubIdColumn )
			{
				try
				{
					return \IPS\Member\Club::load( $this->$clubIdColumn );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		return NULL;
	}
		
	/**
	 * Set form for creating a node of this type in a club
	 *
	 * @param	\IPS\Helpers\Form	$form	Form object
	 * @return	void
	 */
	public function clubForm( \IPS\Helpers\Form $form )
	{
		$itemClass = static::$contentItemClass;
		$form->add( new \IPS\Helpers\Form\Text( 'club_node_name', $this->_id ? $this->_title : \IPS\Member::loggedIn()->language()->addToStack( static::clubFrontTitle() ), TRUE, array( 'maxLength' => 255 ) ) );
	}
	
	/**
	 * Save club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function saveClubForm( \IPS\Member\Club $club, $values )
	{
		$nodeClass = get_called_class();
		$itemClass = $nodeClass::$contentItemClass;
		
		$this->_saveClubForm( $club, $values );
		
		$needToUpdatePermissions = FALSE;
		if ( !$this->_id )
		{
			$clubIdColumn = $this->clubIdColumn();
			$this->$clubIdColumn = $club->id;
			$this->save();
			\IPS\Db::i()->insert( 'core_clubs_node_map', array(
				'club_id'		=> $club->id,
				'node_class'	=> $nodeClass,
				'node_id'		=> $this->_id,
				'name'			=> $values['club_node_name']
			) );
			
			$needToUpdatePermissions = TRUE;
		}
		else
		{
			$this->save();
			\IPS\Db::i()->update( 'core_clubs_node_map', array( 'name' => $values['club_node_name'] ), array( 'club_id=? AND node_class=? AND node_id=?', $club->id, $nodeClass, $this->_id ) );  
		}
		
		\IPS\Lang::saveCustom( $itemClass::$application, static::$titleLangPrefix . $this->_id, $values['club_node_name'] );
		\IPS\Lang::saveCustom( $itemClass::$application, static::$titleLangPrefix . $this->_id . '_desc', isset( $values['club_node_description'] ) ? $values['club_node_description'] : '' );
		
		if ( $needToUpdatePermissions )
		{
			$this->setPermissionsToClub( $club );
		}
	}
	
	/**
	 * Class-specific routine when saving club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function _saveClubForm( \IPS\Member\Club $club, $values )
	{
		// Deliberately does nothing so classes can override
	}
	
	/**
	 * Set the permission index permissions to a specific club
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @return  void
	 */
	public function setPermissionsToClub( \IPS\Member\Club $club )
	{
		/* Delete current rows */
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', static::$permApp, static::$permType, $this->_id ) );

		/* Build new rows */
		$insert = array( 'app' => static::$permApp, 'perm_type' => static::$permType, 'perm_type_id' => $this->_id );
		foreach ( static::$permissionMap as $k => $v )
		{
			if ( in_array( $k, array( 'view', 'read' ) ) )
			{
				switch ( $club->type )
				{
					case $club::TYPE_PUBLIC:
					case $club::TYPE_OPEN:
						$insert[ 'perm_' . $v ] = '*';
						break;
					
					case $club::TYPE_CLOSED:
					case $club::TYPE_PRIVATE:
						$insert[ 'perm_' . $v ] = "cm,c{$club->id}";
						break;
				}
			}
			else
			{
				switch ( $club->type )
				{
					case $club::TYPE_PUBLIC:
						$insert[ 'perm_' . $v ] = 'ca';
						break;
					
					case $club::TYPE_OPEN:
					case $club::TYPE_CLOSED:
					case $club::TYPE_PRIVATE:
						$insert[ 'perm_' . $v ] = "cm,c{$club->id}";
						break;
				}
			}
		}

		/* Insert */
		$permId = \IPS\Db::i()->insert( 'core_permission_index', $insert );
		
		/* Update tags permission cache */
		if ( isset( static::$permissionMap['read'] ) )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_text' => $insert[ 'perm_' . static::$permissionMap['read'] ] ), array( 'tag_perm_aap_lookup=?', md5( static::$permApp . ';' . static::$permType . ';' . $this->_id ) ) );
		}

		/* Make sure this object resets the permissions internally */
		$this->_permissions = array_merge( array( 'perm_id' => $permId ), $insert );
		
		/* Update search index */
		$this->updateSearchIndexPermissions();
	}

	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULL to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function rootsWithClubs( $permissionCheck='view', $member=NULL, $where=array() )
	{
		/* Will we need to check permissions? */
		if ( $permissionCheck !== NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
		}

		if( static::$databaseColumnParent !== NULL )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnParent . '=?', static::$databaseColumnParentRootValue );
		}

		return static::nodesWithPermission( $permissionCheck, $member, $where );
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	string|NULL					$contentClass	The content class to check
	 * @return	bool
	 */
	public function modPermission( $type, \IPS\Member $member, $contentClass = NULL )
	{
		if ( \IPS\Settings::i()->clubs )
		{
			$clubIdColumn = $this->clubIdColumn();
			if ( $this->$clubIdColumn and $club = $this->club() )
			{
				if ( in_array( $club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR ) ) )
				{
					if ( in_array( $type, explode( ',', \IPS\Settings::i()->clubs_modperms ) ) )
					{
						return TRUE;
					}
				}
			}		
		}
		
		return parent::modPermission( $type, $member, $contentClass );
	}
		
	/**
	 * [Node] Get parent list
	 *
	 * @return	\SplStack
	 */
	public function parents()
	{
		$clubIdColumn = static::clubIdColumn();
		
		/* Only do this if this node is actually associated with a club */
		if ( $this->$clubIdColumn )
		{
			return new \SplStack;
		}
		else
		{
			return parent::parents();
		}
	}
}