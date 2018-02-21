<?php
/**
 * @brief		Stats Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Nov 2013
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stats Widget
 */
class _stats extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'stats';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Specify widget configuration
	 *
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
 		if ( $form === null )
 		{
	 		$form = new \IPS\Helpers\Form;
 		} 

		$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );
		$form->add( new \IPS\Helpers\Form\Number( 'stats_most_online', $mostOnline['count'], TRUE ) );
		
		return $form;
 	}
 	
 	/**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
 		if ( \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_recount_content' ) )
 		{
 			$mostOnline = array( 'count' => $values['stats_most_online'], 'time' => time() );
			\IPS\Settings::i()->changeValues( array( 'most_online' => json_encode( $mostOnline ) ) );

			unset( $values['stats_most_online'] );

 			\IPS\Widget::deleteCaches( 'stats', 'core' );
 		}

 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$stats = array();
		$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );

		/* fetch only successful registered members ; if this needs to be changed, please review the other areas where we have the name<>? AND email<>? condition */
		$where = array( 'name<>? AND email<>?', '', '' );

		/* Member count */
		$stats['member_count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
		
		/* Most online */
		$select = \IPS\Db::i()->select( 'COUNT(*)', 'core_sessions', array( 'login_type<>? and running_time > ?', \IPS\Session\Front::LOGIN_TYPE_SPIDER, \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() ), NULL, NULL, 'member_id',  NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		$count = $select->count(TRUE);
		if( $count > $mostOnline['count'] )
		{
			$mostOnline = array( 'count' => $count, 'time' => time() );
			\IPS\Settings::i()->changeValues( array( 'most_online' => json_encode( $mostOnline ) ) );
		}
		$stats['most_online'] = $mostOnline;
				
		/* Last Registered Member */
		$exclude = array();
		$where   = array( array( "name IS NOT NULL AND name != '' AND temp_ban != -1 AND email != ''" ) );
		foreach( \IPS\Member\Group::groups() as $id => $group )
		{
			if ( $group->g_hide_online_list )
			{
				$exclude[] = $group->g_id;
			}
		}
		if ( count( $exclude ) )
		{
			$where[] = array( '( ! ( ' . \IPS\Db::i()->in( 'member_group_id', $exclude ) . ' ) )' );
		}
		$where[] = array( '( ! ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'bw_is_spammer' ) . ' )' );
		$where[] = array( 'member_id NOT IN(?)', \IPS\Db::i()->select( 'member_id', 'core_validating', array( 'new_reg=1' ) ) );
		
		try
		{
			$stats['last_registered'] = \IPS\Member::constructFromData( \IPS\Db::i()->select( 'core_members.*', 'core_members', $where, 'core_members.member_id DESC', array( 0, 1 ) )->first() );
		}
		catch( \UnderflowException $ex )
		{
			$stats['last_registered'] = NULL;
		}
		
		/* Display */		
		return $this->output( $stats );
	}
}