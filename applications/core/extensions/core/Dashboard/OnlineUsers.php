<?php
/**
 * @brief		Dashboard extension: Online Users
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jul 2013
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Online Users
 */
class _OnlineUsers
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		/* Init Chart */
		$chart = new \IPS\Helpers\Chart;
		
		/* Specify headers */
		$chart->addHeader( \IPS\Member::loggedIn()->language()->get('chart_app'), "string" );
		$chart->addHeader( \IPS\Member::loggedIn()->language()->get('chart_members'), "number" );
		
		/* Add Rows */
		$online = array();
		$seen   = array();
		foreach( \IPS\Db::i()->select( "*", 'core_sessions', array( 'running_time>?', \IPS\DateTime::create()->sub( new \DateInterval('PT30M') )->getTimestamp() ), 'running_time DESC' ) as $row )
		{
			$key = ( $row['member_id'] ? $row['member_id'] : $row['id'] );
			
			if ( ! isset( $seen[ $key ] ) )
			{
				$online[ $row['current_appcomponent'] ][ $key ] = $row['id'];
				$seen[ $key ] = true;
			}
		}

		foreach ( $online as $app => $data )
		{
			/* Only show if the application is still installed and enabled */
			if( !\IPS\Application::appIsEnabled( $app ) )
			{
				continue;
			}

			$chart->addRow( array( \IPS\Member::loggedIn()->language()->addToStack( "__app_" . $app), count( $data ) ) );
		}
		
		/* Output */
		return \IPS\Theme::i()->getTemplate( 'dashboard' )->onlineUsers( $online, $chart->render( 'PieChart', array( 
			'backgroundColor' 	=> '#ffffff',
			'colors' => array( '#cc535f', '#d8624b', '#598acd', '#9a84d2', '#e4b555', '#8cb65e', '#44af94', '#4da5c8', '#acb2bb', '#676d76' ),
			'chartArea' => array( 
				'width' =>"90%", 
				'height' => "90%" 
			) 
		) ) );
	}
}