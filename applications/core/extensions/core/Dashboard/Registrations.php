<?php
/**
 * @brief		Dashboard extension: Registrations
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
 * @brief	Dashboard extension: Registrations
 */
class _Registrations
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
		$chart->addHeader( \IPS\Member::loggedIn()->language()->addToStack('date'), 'string' ); // Since we're displaying a column chart, it makes more sense to use a string so they plotted as discreet columns rather than in the middle of dates
		$chart->addHeader( \IPS\Member::loggedIn()->language()->addToStack('members'), 'number' );
		
		$data = array();
		
		/* We use midnight as a basis so we can include todays registrations */
		$date = new \IPS\DateTime( 'tomorrow', new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );

		/* We need to clone $date here for use in the query later */
		$today = clone $date;
		
		/* And we do this over a period of seven days - the query will also do this on the cloned object later. */
		$date->sub( new \DateInterval( 'P7D' ) );

		while ( $date->getTimestamp() < time() )
		{
			$data[ $date->format( 'Y-n-j' ) ] = 0;
			$date->add( new \DateInterval( 'P1D' ) );
		}

		/* fetch only successful registered members ; if this needs to be changed, please review the other areas where we have the name<>? AND email<>? condition */
		$where = array( 'joined>? AND name<>? AND email<>?', $today->sub( new \DateInterval( 'P7D' ) )->getTimestamp(), '', '' );

		/* Add Rows */
		foreach( \IPS\Db::i()->select( 'joined', 'core_members', $where, 'joined DESC' ) AS $joined )
		{
			$time = \IPS\DateTime::ts( $joined )->format( 'Y-n-j' );

			/* Occasionally, timezone differences mean that we fall just over the beginning or end, which results in an undefined index. We can
				ignore any registrations for dates that haven't been initialized. */
			if( isset( $data[ $time ] ) )
			{
				$data[ $time ] += 1;
			}
		}
		
		uksort( $data, function( $a, $b )
		{
			return strnatcmp( $a, $b );
		} );
		
		/* Add to graph */
		foreach ( $data as $time => $d )
		{
			$datetime = new \IPS\DateTime;
			$exploded = explode( '-', $time );
			$datetime->setDate( $exploded[0], $exploded[1], $exploded[2] );
			
			$chart->addRow( array( $datetime->format( 'j M Y' ), $d ) );
		}
		
		/* Work out the ticks */
		$ticks = array();
		$increment = ceil( max($data) / 5 );
		
		for ($i = 1; $i <= 5; $i++)
		{
			$v = $increment * $i;
			$ticks[] = array( 'v' => $v, 'f' => (string) $v );
		}
		
		/* Output */
		return \IPS\Theme::i()->getTemplate( 'dashboard' )->registrations( $chart->render( 'ColumnChart', array(
 			'legend' 			=> array( 'position' => 'none' ),
 			'areaOpacity'		=> 0.4,
 			'lineWidth'			=> 1,
 			'backgroundColor' 	=> '#ffffff',
 			'colors'			=> array( '#10967e' ),
 			'vAxis'				=> array( 'ticks' => $ticks ),
 			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
 		) ) );
	}
}