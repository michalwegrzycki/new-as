<?php
/**
 * @brief		topics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		18 Aug 2014
 */

namespace IPS\forums\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * topics
 */
class _topics extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'topics_manage' );
		
		$chart = new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=forums&module=stats&controller=topics" ), 'forums_topics', 'start_date', '', array( 
			'isStacked' => FALSE,
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		) );
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack( 'stats_new_topics' ), 'number', 'COUNT(*)', FALSE );
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_topics_title' );
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__forums_stats_topics' );
		\IPS\Output::i()->output = (string) $chart;
	}
}