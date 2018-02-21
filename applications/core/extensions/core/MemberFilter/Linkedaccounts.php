<?php
/**
 * @brief		Member filter extension: Linked accounts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Mar 2017
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member filter: Member last visit date
 */
class _Linkedaccounts
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return in_array( $area, array( 'bulkmail' ) );
	}

	/** 
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		$services = array();
		$options = array( 'any' => 'any', 'linked' => 'mf_profile_linked', 'unlinked' => 'mf_profile_unlinked' );
		foreach ( \IPS\core\ProfileSync\ProfileSyncAbstract::services() as $key => $class )
		{
			if( $class::memberFilterLinkedWhere() )
			{
				$services[] = new \IPS\Helpers\Form\Radio( 'profilesync__' . $key, isset( $criteria['profilesync__' . $key] ) ? $criteria['profilesync__' . $key] : 'any', FALSE, array( 'options' => $options ) );
			}
		}

		return $services;
	}
	
	/**
	 * Save the filter data
	 *
	 * @param	array	$post	Form values
	 * @return	mixed			False, or an array of data to use later when filtering the members
	 * @throws \LogicException
	 */
	public function save( $post )
	{
		$return = array();
		foreach ( \IPS\core\ProfileSync\ProfileSyncAbstract::services() as $key => $class )
		{
			if( isset( $post['profilesync__' . $key] ) and in_array( $post['profilesync__' . $key], array( 'linked', 'unlinked' ) ) )
			{
				$return['profilesync__' . $key] = $post['profilesync__' . $key];
			}
		}

		return count( $return ) ? $return : FALSE;
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	string|array|NULL	Where clause
	 */
	public function getQueryWhereClause( $data )
	{
		$return = array();
		foreach ( \IPS\core\ProfileSync\ProfileSyncAbstract::services() as $key => $class )
		{
			if( !isset( $data['profilesync__' . $key] ) )
			{
				continue;
			}

			switch( $data['profilesync__' . $key] )
			{
				case 'linked':
					$where = $class::memberFilterLinkedWhere();
					break;
				case 'unlinked':
					$where = $class::memberFilterLinkedWhere( TRUE );
					break;

				default:
					break;
			}
			if( $where )
			{
				$return[] = $where;
			}
		}

		return $return;
	}
}