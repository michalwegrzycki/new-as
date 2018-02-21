<?php
/**
 * @brief		Online Users
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Aug 2013
 */

namespace IPS\core\modules\front\online;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Online Users
 */
class _online extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Online Users
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=online&controller=online', 'front', 'online' ), array(), 'loc_viewing_online_users' );

		/* Sessions are written on shutdown so let's do it now instead */
		\IPS\Session\Front::i()->setTheme( \IPS\Member::loggedIn()->skin ?: 0 );
		session_write_close();
		
		$notInMyName = array();
		foreach( \IPS\Member\Group::groups() as $group )
		{
			if ( $group->g_hide_online_list )
			{
				$notInMyName[] = $group->g_id;
			}
		}
		
		/* Initial filters */
		$subWhere = array( 
			array( "s.running_time>?", \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() ),
			array( "s.login_type!=?", \IPS\Session\Front::LOGIN_TYPE_SPIDER )
		);
		if ( !\IPS\Member::loggedIn()->isAdmin() )
		{
			$subWhere[] = array( "s.login_type!=?", \IPS\Session\Front::LOGIN_TYPE_ANONYMOUS );
		}

		if ( count( $notInMyName ) )
		{
			$subWhere[] = array( \IPS\Db::i()->in( 's.member_group', $notInMyName, TRUE ) );
		}
		
		/* Ok, this looks odd, but the ONLY_FULL_GROUP_BY bites us here, so selecting max(id) allows us to return a session ID even though we're grouping on member_id */
		$where = array( array( "( core_sessions.id IN(?) OR ( core_sessions.member_id IS NULL AND core_sessions.login_type != ? AND core_sessions.running_time > ?) )", \IPS\Db::i()->select( 'MAX(id)', array( 'core_sessions', 's' ), $subWhere, NULL, NULL, 'member_id' ), \IPS\Session\Front::LOGIN_TYPE_SPIDER, \IPS\DateTime::create()->sub( new \DateInterval( 'PT30M' ) )->getTimeStamp() ) );
		
		if ( count( $notInMyName ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'core_sessions.member_group', $notInMyName, TRUE ) );
		}
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_sessions', \IPS\Http\Url::internal( 'app=core&module=online&controller=online', 'front', 'online' ), $where );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'online', 'core', 'front' ), 'onlineUsersTable' );
		$table->rowsTemplate	  = array( \IPS\Theme::i()->getTemplate( 'online', 'core', 'front' ), 'onlineUsersRow' );
		$table->langPrefix = 'online_users_';
		$table->include = array( 'photo', 'member_name', 'location_lang', 'running_time', 'ip_address', 'login_type' );
		$table->limit = 30;
		$table->noSort	= array( 'photo', 'location_lang' );

		/* Custom parsers */
		$table->parsers = array(
				'location_lang'	=> function( $val, $row )
				{
					return \IPS\Session\Front::getLocation( $row );
				},
				'photo' => function( $val, $row )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::load( $row['member_id'] ), 'mini' );
				},
				'running_time' => function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->relative();
				},
				'member_name' => function( $val, $row )
				{
					if( $row['member_id'] )
					{
						return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userLink( \IPS\Member::load( $row['member_id'] ) );
					}
					else
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'guest' );
					}
				},
		);
		
		$table->filters = array(
			 'filter_loggedin'	=> 'core_sessions.member_id <> 0',
		);
		
		foreach ( \IPS\Member\Group::groups( TRUE, TRUE, TRUE ) as $group )
		{
			/* Hiding from online list? */
			if( $group->g_hide_online_list )
			{
				continue;
			}

			/* Alias the lang keys */
			$realLangKey = "core_group_{$group->g_id}";
			$fakeLangKey = "online_users_group_{$group->g_id}";
			\IPS\Member::loggedIn()->language()->words[ $fakeLangKey ] = \IPS\Member::loggedIn()->language()->addToStack( $realLangKey, FALSE );
			
			if( $group->g_id == \IPS\Settings::i()->guest_group )
			{
				$table->filters[ 'group_' . $group->g_id ] = 'core_sessions.member_id IS NULL';
			}
			else
			{
				$table->filters[ 'group_' . $group->g_id ] = 'core_sessions.member_group=' . $group->g_id;
			}
		}

		$table->sortBy = $table->sortBy ?: 'running_time';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Get the count */
		$counter = \IPS\Db::i()->select( 'COUNT(*)', 'core_sessions', $where );

		foreach( $table->joins as $join )
		{
			$counter = $counter->join( $join['from'], $join['where'] );
		}

		$totalCount = $counter->first();
		
		/* Display */
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('online_users');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'online', 'core', 'front' )->onlineUsersList( (string) $table, $totalCount );
	}
}