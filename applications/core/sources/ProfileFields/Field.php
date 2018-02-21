<?php
/**
 * @brief		Custom Profile Field Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Apr 2013
 */

namespace IPS\core\ProfileFields;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom Profile Field Node
 */
class _Field extends \IPS\CustomField
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Access level constants
	 */
	const PROFILE 	= 0;
	const REG		= 1;
	const STAFF		= 2;
	const CONTENT	= 3;
	const SEARCH	= 4;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_pfields_data';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'pf_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'group_id';
	
	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\core\ProfileFields\Group';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[CustomField] Title/Description lang prefix
	 */
	protected static $langKey = 'core_pfield';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 */
	protected static $restrictions = array(
		'app'		=> 'core',
		'module'	=> 'membersettings',
		'prefix'	=> 'profilefields_',
	);
	
	/**
	 * @brief	[CustomField] Editor Options
	 */
	public static $editorOptions = array( 'app' => 'core', 'key' => 'CustomField' );

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'core_pfield_';
	
	/**
	 * @brief	[CustomField] FileStorage Extension for Upload fields
	 */
	public static $uploadStorageExtension = 'core_ProfileField';
	
	/**
	 * Get field data
	 *
	 * @return	array
	 */
	public static function fieldData()
	{
		if ( !isset( \IPS\Data\Store::i()->profileFields ) )
		{		
			$fields = array();
			$display = FALSE;
			
			foreach ( \IPS\Db::i()->select( '*', 'core_pfields_groups', NULL, 'pf_group_order' ) as $row )
			{
				$fields[ $row['pf_group_id'] ] = array();
			}
	
			foreach ( \IPS\Db::i()->select( '*', 'core_pfields_data', NULL, 'pf_position' ) as $row )
			{
				$fields[ $row['pf_group_id'] ][ $row['pf_id'] ] = $row;
				if ( $row['pf_format'] )
				{
					$display = TRUE;
				}
			}
			
			\IPS\Data\Store::i()->profileFields = array( 'fields' => $fields, 'display' => $display );
		}
		
		return \IPS\Data\Store::i()->profileFields['fields'];
	}
	
	/**
	 * Are there fields to display in content view?
	 *
	 * @return	bool
	 */
	public static function fieldsForContentView()
	{
		if ( !isset( \IPS\Data\Store::i()->profileFields ) )
		{
			static::fieldData();
		}
		return \IPS\Data\Store::i()->profileFields['display'];
	}
	
	/**
	 * Get Fields
	 *
	 * @param	array		$values		Current values
	 * @param	int			$location	\IPS\core\ProfileFields\Field::PROFILE for profile, \IPS\core\ProfileFields\Field::REG for registration screen or \IPS\core\ProfileFields\Field::STAFF for ModCP/ACP
	 * @return	array
	 */
	public static function fields( $values=array(), $location=0 )
	{
		if( !$values )
		{
			$values = array();
		}

		$return = array();

		foreach ( static::fieldData() as $groupId => $fields )
		{
			foreach ( $fields as $row )
			{
				if ( 
						( $row['pf_admin_only'] and $location !== static::STAFF ) or
						( $location === static::PROFILE and !$row['pf_member_edit'] ) or
						( $location === static::SEARCH and ( !$row['pf_search_type'] or $row['pf_member_hide'] ) ) or
						( $location === static::REG and !$row['pf_show_on_reg'] )
				)
				{
					continue;
				}
	
				if ( !array_key_exists( 'field_' . $row['pf_id'], $values ) )
				{
					$values['field_' . $row['pf_id'] ] = NULL;
				}
				
				/* @todo Remove reliance on \IPS\Request here and instead pass an \IPS\Member object to the method */
				static::$editorOptions['autoSaveKey'] = md5( get_called_class() . '-' . $row['pf_id'] ) . ( isset( \IPS\Request::i()->id ) ? '-' . \IPS\Request::i()->id : '' );

				if( $row['pf_type'] == 'Editor' )
				{
					static::$editorOptions['attachIds'] = array( ( isset( \IPS\Request::i()->id ) ? \IPS\Request::i()->id : 0 ), $row['pf_id'] );
				}
				
				if ( $location === static::STAFF )
				{
					$row['pf_not_null'] = 0;
				}
				
				$return[ $groupId ][ $row['pf_id'] ] = static::constructFromData( $row )->buildHelper( $values[ 'field_' . $row['pf_id'] ] );
			}
		}
				
		return $return;
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
		$result = parent::load( $id, $idField, $extraWhereClause );

		static::$editorOptions['autoSaveKey'] = md5( get_called_class() . '-' . $result->id ) . ( isset( \IPS\Request::i()->id ) ? '-' . \IPS\Request::i()->id : '' );

		if( $result->type == 'Editor' )
		{
			static::$editorOptions['attachIds'] = array( ( isset( \IPS\Request::i()->id ) ? \IPS\Request::i()->id : 0 ), $result->id );
		}

		return $result;
	}

	
	/**
	 * Get Values
	 *
	 * @param	array		$values		Current values
	 * @param	int			$location	\IPS\core\ProfileFields\Field::PROFILE for profile, \IPS\core\ProfileFields\Field::REG for registration screen or \IPS\core\ProfileFields\Field::STAFF for ModCP/ACP
	 * @return	array
	 */
	public static function values( $values, $location=0 )
	{
		$return = array();
		foreach ( static::fieldData() as $groupId => $fields )
		{
			foreach ( $fields as $row )
			{
				if ( $row['pf_admin_only'] and $location !== static::STAFF )
				{
					continue;
				}
	
				if ( $location == static::CONTENT and $row['pf_format'] != '' and isset( $values[ 'field_' . $row['pf_id'] ] ) and $row['pf_type'] != 'Poll' )
				{
					$row = static::constructFromData( $row );

					if( $row->displayValue( $values[ 'field_' . $row->id ] ) !== "" )
					{
						$value = $row->type == 'Url' ? (string) $values[ 'field_' . $row->id ] : $row->displayValue( $values[ 'field_' . $row->id ] );
						$return[ $row->group_id ][  $row->id ] = str_replace( array( '{title}', '{content}' ), array( \IPS\Member::loggedIn()->language()->addToStack( static::$langKey . '_' . $row->id ), $value ), $row->format );
					}
				}
				else if ( $location !== static::CONTENT )
				{
					$return[ $groupId ][ static::$langKey . '_' . $row['pf_id'] ] = static::constructFromData( $row )->displayValue( $values[ 'field_' . $row['pf_id'] ] );
				}
			}
		}

		return $return;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		parent::form( $form );
		
		$form->add( new \IPS\Helpers\Form\TextArea( 'pf_format', $this->id ? $this->format : '', FALSE, array( 'placeholder' => "<strong>{title}:</strong> {content}" ), NULL, NULL, NULL, 'pf_format' ) );
		$form->addheader( 'pfield_permissions' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'pf_admin_only', $this->id ? !$this->admin_only : TRUE, FALSE, array( 'togglesOn' => array( 'pf_show_on_reg', 'pf_member_edit', 'pf_member_hide' ) ) ) );
		
		if ( \IPS\Settings::i()->quick_register )
		{
			/* Quick register is enabled, so do not allow required to be set */
			if ( isset( $form->elements['']['pf_not_null'] ) )
			{
				unset( $form->elements['']['pf_not_null'] );
			}
		}
		else
		{
			/* Quick register is off, so show normal 'reg' field */
			$form->add( new \IPS\Helpers\Form\YesNo( 'pf_show_on_reg', $this->id ? $this->show_on_reg : TRUE, FALSE, array(), NULL, NULL, NULL, 'pf_show_on_reg' ) );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'pf_member_edit', $this->id ? $this->member_edit : TRUE, FALSE, array(), NULL, NULL, NULL, 'pf_member_edit' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'pf_member_hide', $this->id ? !$this->member_hide : TRUE, FALSE, array(), NULL, NULL, NULL, 'pf_member_hide' ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( isset( $values['pf_admin_only'] ) )
		{
			$values['pf_admin_only']	= $values['pf_admin_only'] ? FALSE : TRUE;
		}

		if( isset( $values['pf_member_hide'] ) )
		{
			$values['pf_member_hide']	= $values['pf_member_hide'] ? FALSE : TRUE;
		}
		
		if( array_key_exists( 'pf_search_type', $values ) )
		{
			$values['pf_search_type'] = (string) $values['pf_search_type'];
		}
		
		if ( $values['pf_type'] == 'Poll' )
		{
			$values['pf_format'] = '';
		}

		return parent::formatFormValues( $values );
	}

	/**
	 * [ActiveRecord] Save Record
	 *
	 * @return	void
	 */
	public function save()
	{
		if( $this->_new )
		{
			$return = parent::save();
			\IPS\Db::i()->addColumn( 'core_pfields_content', array( 'name' => "field_{$this->id}", 'type' => 'MEDIUMTEXT' ) );
		}
		else
		{
			$return = parent::save();
		}
		
		unset( \IPS\Data\Store::i()->profileFields );

		return $return;
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		\IPS\Db::i()->dropColumn( 'core_pfields_content', "field_{$this->id}" );
		unset( \IPS\Data\Store::i()->profileFields );
		
		if ( isset( static::$multitons[ $this->id ] ) )
		{
			unset( static::$multitons[ $this->id ] );
		}
		
		/* Do we need to do stuff with profile steps? */
		\IPS\Member\ProfileStep::resync();
	}
}

/* This is only here for backwards compatibility */
const PROFILE 	= _Field::PROFILE;
const REG		= _Field::REG;
const STAFF		= _Field::STAFF;
const CONTENT	= _Field::CONTENT;
const SEARCH	= _Field::SEARCH;
