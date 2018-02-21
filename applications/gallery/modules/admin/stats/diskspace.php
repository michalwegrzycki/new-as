<?php
/**
 * @brief		Top uploaders
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Top uploaders
 */
class _diskspace extends \IPS\Dispatcher\Controller
{
	const PER_PAGE = 25;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'diskspace_manage' );
		parent::execute();
	}

	/**
	 * Show top uploaders
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$values = NULL;
		$where = array();
		
		if ( isset( \IPS\Request::i()->form ) )
		{
			$form = new \IPS\Helpers\Form( 'form', 'go' );
			$form->add( new \IPS\Helpers\Form\DateRange( 'stats_date_range', NULL, FALSE, array( 'start' => array( 'max' => new \IPS\DateTime() ), 'end' => array( 'max' => new \IPS\DateTime() ) ) ) );
			
			if ( $values = $form->values() )
			{
				if ( $values['stats_date_range']['start'] )
				{
					$where[] = array( 'image_date>?', $values['stats_date_range']['start']->getTimestamp() );
				}
				if ( $values['stats_date_range']['end'] )
				{
					$where[] = array( 'image_date<?', $values['stats_date_range']['end']->getTimestamp() );
				}
			}
			else
			{
				\IPS\Output::i()->output = $form;
				return;
			}
		}
		
		$page = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		$select = \IPS\Db::i()->select( 'image_member_id, COUNT(*) as images', 'gallery_images', $where, 'images DESC', array( ( $page - 1 ) * static::PER_PAGE, static::PER_PAGE ), 'image_member_id', NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS )->join( 'core_members', 'core_members.member_id=gallery_images.image_member_id' );
		$mids = array();
		
		foreach( $select as $row )
		{
			$mids[] = $row['image_member_id'];
		}
		
		if ( count( $mids ) )
		{
			$members = iterator_to_array( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', $mids ) ) )->setKeyField('member_id') );
		}
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination(
			\IPS\Http\Url::internal( 'app=gallery&module=stats&controller=diskspace' )->setQueryString( $values ),
			ceil( $select->count( TRUE ) / static::PER_PAGE ),
			$page,
			static::PER_PAGE,
			FALSE
		);
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'settings'	=> array(
				'title'		=> 'stats_date_range',
				'icon'		=> 'calendar',
				'link'		=> \IPS\Http\Url::internal( 'app=gallery&module=stats&controller=diskspace&form=1' )->setQueryString( $values ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('stats_date_range') )
			)
		);
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack( 'stats_include_hidden_content' ), 'info' );
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('stats')->uploadersTable( $select, $pagination, $members );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__gallery_stats_diskspace');
	}
}