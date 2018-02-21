<?php
/**
 * @brief		Reaction Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Reaction Model
 */
class _Reaction extends \IPS\Node\Model
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_reactions';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'reaction_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'reactions';
	
	/**
	 * @brief	[Node] Sortable
	 */
	public static $nodeSortable = TRUE;
	
	/**
	 * @brief	[Node] Positon Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Modal Forms because Charles loves them so
	 */
	public static $modalForms = TRUE;
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'reaction_title_';
	
	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = 'enabled';
	
	/**
	 * Form
	 *
	 * @param	\IPS\Helpers\Form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'reaction_title', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? 'reaction_title_' . $this->id : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'reaction_value', $this->id ? $this->value : 1, TRUE, array( 'options' => array( 1 => 'positive', 0 => 'neutral', -1 => 'negative' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'reaction_icon', $this->id ? \IPS\File::get( 'core_Reaction', $this->icon ) : NULL, TRUE, array( 'image' => TRUE, 'storageExtension' => 'core_Reaction', 'storageContainer' => 'reactions', 'obscure' => FALSE ) ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->save();
		}
		
		\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $this->id, $values['reaction_title'] );
		unset( $values['reaction_title'] );
		
		return parent::formatFormValues( $values );
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		if ( $this->id === 1 )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Get Icon
	 *
	 * @return	\IPS\File
	 */
	public function get__icon()
	{
		return \IPS\File::get( 'core_Reaction', $this->_data['icon'] );
	}

	/**
	 * Get Description
	 *
	 * @return	strong
	 */
	public function get__description()
	{
		if ( $this->value == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('positive');
		}
		elseif ( $this->value == -1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('negative');
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('neutral');
		}
	}
	
	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function roots( $permissionCheck='view', $member=NULL, $where=array() )
	{
		if ( !count( $where ) )
		{
			$return = array();
			foreach( static::reactionStore() AS $reaction )
			{
				$return[ $reaction['reaction_id'] ] = static::constructFromData( $reaction );
			}
			
			return $return;
		}
		else
		{
			return parent::roots( $permissionCheck, $member, $where );
		}
	}
	
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		if ( $this->id == 1 )
		{
			return NULL;
		}
		
		return parent::get__enabled();
	}
	
	/**
	 * Set Enabled
	 *
	 * @return	void
	 */
	public function set__enabled( $enabled )
	{
		parent::set__enabled( $enabled );
		
		unset( \IPS\Data\Store::i()->reactions );
	}
	
	/**
	 * Reaction Store
	 *
	 * @return	array
	 */
	public static function reactionStore()
	{
		if ( !isset( \IPS\Data\Store::i()->reactions ) )
		{
			\IPS\Data\Store::i()->reactions = iterator_to_array( \IPS\Db::i()->select( '*', 'core_reactions', NULL, "reaction_position ASC" )->setKeyField( 'reaction_id' ) );
		}
		
		return \IPS\Data\Store::i()->reactions;
	}
	
	/**
	 * Is Like Mode
	 *
	 * @return	bool
	 */
	public static function isLikeMode()
	{
		$i = 0;
		foreach( static::roots() AS $row )
		{
			if ( $row->_enabled !== FALSE )
			{
				$i++;
			}
		}
		
		return ( $i == 1 );
	}
	
	/**
	 * Load Record
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed		$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		if ( ( $idField === NULL or $idField === 'reaction_id' ) and $extraWhereClause === NULL )
		{
			$reactions = static::reactionStore();
			if ( isset( $reactions[ $id ] ) )
			{
				return static::constructFromData( $reactions[ $id ] );
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
			
		return parent::load( $id, $idField, $extraWhereClause );		
	}
	
	/**
	 * [ActiveRecord] Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		
		unset( \IPS\Data\Store::i()->reactions );
	}
	
	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		
		unset( \IPS\Data\Store::i()->reactions );
	}
	
	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	array(
	array(
	'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	'title'	=> 'foo',		// Language key to use for button's title parameter
	'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	),
	...							// Additional buttons
	);
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );
		
		if ( $this->canDelete() )
		{
			$buttons['delete'] = array(
				'icon'	=> 'times-circle',
				'title'	=> 'delete',
				'link'	=> $url->setQueryString( array( 'do' => 'delete', 'id' => $this->_id ) ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ),
				'hotkey'=> 'd'
			);
		}
		return $buttons;
	}
}