<?php
/**
 * @brief		Member Stats
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		26 Mar 2014
 */

namespace IPS\gallery\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Stats
 */
class _member extends \IPS\Dispatcher\Controller
{
	/**
	 * Images
	 *
	 * @return	void
	 */
	protected function images()
	{
		/* Get member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2G192/1', 404, '' );
		}
		
		/* Build chart */
		$tabs = array( 'information' => 'information', 'bandwidth_use' => 'bandwidth_use' );
		$activeTab = \IPS\Request::i()->tab ?: 'information';
		switch ( $activeTab )
		{
			case 'information':
				$imageCount = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images', array( 'image_member_id=?', $member->member_id ) )->first();
				$diskspaceUsed = \IPS\Db::i()->select( 'SUM(image_file_size)', 'gallery_images', array( 'image_member_id=?', $member->member_id ) )->first();
				$numberOfViews = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_bandwidth', array( 'member_id=?', $member->member_id ) )->first();
				$bandwidthUsed = \IPS\Db::i()->select( 'SUM(bsize)', 'gallery_bandwidth', array( 'member_id=?', $member->member_id ) )->first();
				
				$allImages = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first();
				$totalFilesize = \IPS\Db::i()->select( 'SUM(image_file_size)', 'gallery_images' )->first();
				$allBandwidth = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_bandwidth' )->first();
				$totalBandwidth = \IPS\Db::i()->select( 'SUM(bsize)', 'gallery_bandwidth' )->first();
				
				$activeTabContents = \IPS\Theme::i()->getTemplate( 'stats' )->information( \IPS\Theme::i()->getTemplate( 'global', 'core' )->definitionTable( array(
					'images_submitted'		=> 
						\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
						\IPS\Member::loggedIn()->language()->formatNumber( $imageCount ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $allImages ? ( 100 / $allImages ) : 0 ) * $imageCount ), 2 ) ) )
					),
					'gdiskspace_used'		=> 
						\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( $diskspaceUsed ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalFilesize ? ( 100 / $totalFilesize ) : 0 ) * $diskspaceUsed ), 2 ) ) )
					),
					'gaverage_filesize'		=> 
						\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_average' , FALSE, array( 'sprintf' => array(
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( \IPS\Db::i()->select( 'AVG(image_file_size)', 'gallery_images', array( 'image_member_id=?', $member->member_id ) )->first() ),
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( \IPS\Db::i()->select( 'AVG(image_file_size)', 'gallery_images' )->first() ) ) )
					),
					'number_of_views'		=> 
						\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
						\IPS\Member::loggedIn()->language()->formatNumber( $numberOfViews ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $allBandwidth ? ( 100 / $allBandwidth ) : 0 ) * $imageCount ), 2 ) ))
					),
					'gallery_bandwidth_used'		=> 
						\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( $bandwidthUsed ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalBandwidth ? ( 100 / $totalBandwidth ) : 0 ) * $bandwidthUsed ), 2 ) ) )
					)
				) ) );
			break;

			case 'bandwidth_use':
				$bandwidthChart = new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=gallery&module=stats&controller=member&do=images&id={$member->member_id}&tab=bandwidth_use&_graph=1" ), 'gallery_bandwidth', 'bdate', '', array( 'vAxis' => array( 'title' => \IPS\Member::loggedIn()->language()->addToStack( 'filesize_raw_k' ) ) ), 'LineChart', 'daily' );
				$bandwidthChart->groupBy = 'bdate';
				$bandwidthChart->where[] = array( 'member_id=?', $member->member_id );
				$bandwidthChart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('bandwidth_use'), 'number', 'ROUND((SUM(bsize)/1024),2)', FALSE );
				$activeTabContents = ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->_graph ) ) ? (string) $bandwidthChart : \IPS\Theme::i()->getTemplate( 'stats' )->graphs( (string) $bandwidthChart );
			break;
		}
		
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('member_images_chart', FALSE, array( 'sprintf' => array( $member->name ) ) );
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=gallery&module=stats&controller=member&do=images&id={$member->member_id}" ) );
		}
	}
}