<?php
/**
 * @brief		activeUsers Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Nov 2013
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * activeUsers Widget
 */
class _activeUsers extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'activeUsers';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* Do we have permission? */
		if ( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'online' ) ) )
		{
			return "";
		}
		
		$members     = array();
		$memberCount = 0;
				
		/* Build WHERE clause */
		$parts = parse_url( (string) \IPS\Request::i()->url()->stripQueryString( array( 'page' ) ) );
		
		if ( \IPS\Settings::i()->htaccess_mod_rewrite )
		{
			$url = $parts['scheme'] . "://" . $parts['host'] . $parts['path'];
		}
		else
		{
			$url = $parts['scheme'] . "://" . $parts['host'] . $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		}
		
		$notInMyName = array();
		foreach( \IPS\Member\Group::groups() as $group )
		{
			if ( $group->g_hide_online_list )
			{
				$notInMyName[] = $group->g_id;
			}
		}
		
		$where = array(
			array( 'core_sessions.login_type=' . \IPS\Session\Front::LOGIN_TYPE_MEMBER ),
			array( 'core_sessions.current_appcomponent=?', \IPS\Dispatcher::i()->application->directory ),
			array( 'core_sessions.current_module=?', \IPS\Dispatcher::i()->module->key ),
			array( 'core_sessions.current_controller=?', \IPS\Dispatcher::i()->controller ),
			array( 'core_sessions.running_time>' . \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() ),
			array( 'core_sessions.location_url IS NOT NULL AND location_url LIKE ?', "{$url}%" ),
			array( 'core_sessions.member_id IS NOT NULL' )
		);

		if( \IPS\Request::i()->id )
		{
			$where[] = array( 'core_sessions.current_id = ?', \IPS\Request::i()->id );
		}

		if ( count( $notInMyName ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'core_sessions.member_group', $notInMyName, TRUE ) );
		}
		
		foreach( \IPS\Db::i()->select( 'core_sessions.member_id,core_sessions.member_name,core_sessions.seo_name,core_sessions.member_group,core_sessions.login_type', 'core_sessions', $where, 'core_sessions.running_time DESC' ) as $row )
		{
			if( $row['login_type'] == \IPS\Session\Front::LOGIN_TYPE_MEMBER and $row['member_id'] != \IPS\Member::loggedIn()->member_id and $row['member_name'] )
			{
				$members[ $row['member_id'] ] = $row;
			}
		}
		
		$memberCount = count( $members );
		
		/* If it's on the sidebar (rather than at the bottom), we want to limit it to 60 so we don't take too much space */
		if ( $this->orientation === 'vertical' and count( $members ) >= 60 )
		{
			$members = array_slice( $members, 0, 60 );
		}

		if( \IPS\Member::loggedIn()->member_id )
		{
			if( !\IPS\Member::loggedIn()->group['g_hide_online_list'] )
			{
				if( !isset( $members[ \IPS\Member::loggedIn()->member_id ] ) )
				{
					$memberCount++;
				}

				$members = array_merge( array( \IPS\Member::loggedIn()->member_id => array(
					'member_id'			=> \IPS\Member::loggedIn()->member_id,
					'member_name'		=> \IPS\Member::loggedIn()->name,
					'seo_name'			=> \IPS\Member::loggedIn()->members_seo_name,
					'member_group'		=> \IPS\Member::loggedIn()->member_group_id
				) ), $members );

			}
		}

		/* Display */
		return $this->output( $members, $memberCount );
	}
}