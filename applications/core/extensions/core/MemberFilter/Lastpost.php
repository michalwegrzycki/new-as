<?php
/**
 * @brief		Member filter extension: member last post date
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
 * @brief	Member filter: Member last post date
 */
class _Lastpost
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return in_array( $area, array( 'bulkmail', 'group_promotions' ) );
	}

	/** 
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		return array(
			new \IPS\Helpers\Form\Custom( 'mf_members_last_post', array( 0 => isset( $criteria['range'] ) ? $criteria['range'] : '', 1 => isset( $criteria['days'] ) ? $criteria['days'] : NULL ), FALSE, array(
				'getHtml'	=> function( $element )
				{
					$dateRange = new \IPS\Helpers\Form\DateRange( "{$element->name}[0]", $element->value[0], FALSE );

					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->dateFilters( $dateRange, $element );
				}
			) )
		);
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
		if( isset( $post['mf_members_last_post'][2] ) AND $post['mf_members_last_post'][2] == 'days' )
		{
			return $post['mf_members_last_post'][1] ? array( 'days' => intval( $post['mf_members_last_post'][1] ) ) : FALSE;
		}
		else
		{
			/* Normalize objects to their array form. Bulk mailer stores options as a json array where as member export does not, so $data['range']['start'] is a DateTime object */
			return ( empty($post['mf_members_last_post'][0]) ) ? FALSE : array( 'range' => json_decode( json_encode( $post['mf_members_last_post'][0] ), TRUE ) );
		}
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	string|array|NULL	Where clause
	 */
	public function getQueryWhereClause( $data )
	{
		if( !empty($data['range']) AND !empty($data['range']['end']) )
		{
			$start	= ( $data['range']['start'] ) ? new \IPS\DateTime( $data['range']['start'] ) : NULL;
			$end	= ( $data['range']['end'] ) ? new \IPS\DateTime( $data['range']['end'] ) : NULL;

			if( $start and $end )
			{
				return "core_members.member_last_post BETWEEN {$start->getTimestamp()} AND {$end->getTimestamp()}";
			}
		}
		elseif( !empty($data['days']) )
		{
			$date = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $data['days'] . 'D' ) );

			return "( core_members.member_last_post < {$date->getTimestamp()} OR core_members.member_last_post IS NULL )";
		}

		return NULL;
	}

	/**
	 * Determine if a member matches specified filters
	 *
	 * @note	This is only necessary if availableIn() includes group_promotions
	 * @param	\IPS\Member	$member		Member object to check
	 * @param	array 		$filters	Previously defined filters
	 * @return	bool
	 */
	public function matches( \IPS\Member $member, $filters )
	{
		/* If we aren't filtering by this, then any member matches */
		if( ( !isset( $filters['range'] ) OR !$filters['range'] OR empty( $filters['range']['end'] ) ) AND ( !isset( $filters['days'] ) OR !$filters['days'] ) )
		{
			return TRUE;
		}

		/* If the member hasn't posted anything yet, he has NULL as member_last_post  */
		if ( !$member->member_last_post )
		{
			/* But if we want members who haven't posted in more than X days, return anyone who hasn't posted at all as well */
			if( !empty( $filters['days'] ) )
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}

		$lastPost = \IPS\DateTime::ts( $member->member_last_post );

		if( !empty( $filters['range'] ) AND !empty( $filters['range']['end'] ) )
		{
			$start	= ( $filters['range']['start'] ) ? new \IPS\DateTime( $filters['range']['start'] ) : NULL;
			$end	= ( $filters['range']['end'] ) ? new \IPS\DateTime( $filters['range']['end'] ) : NULL;

			if( $start and $end )
			{
				return (bool) ( $lastPost->getTimestamp() > $start->getTimestamp() AND $lastPost->getTimestamp() < $end->getTimestamp() );
			}
		}
		elseif( !empty( $filters['days'] ) )
		{
			return (bool) ( $lastPost->add( new \DateInterval( 'P' . $filters['days'] . 'D' ) )->getTimestamp() < time() );
		}
	}
}