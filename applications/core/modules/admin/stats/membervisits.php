<?php
/**
 * @brief		Member visit statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Mar 2017
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member visit statistics
 */
class _membervisits extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'membervisits_manage' );
		parent::execute();
	}

	/**
	 * Member visit statistics
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$count		= NULL;
		$table		= NULL;
		$start		= NULL;
		$end		= NULL;

		$defaults = array( 'start' => \IPS\DateTime::create()->setDate( date('Y'), date('m'), 1 ), 'end' => new \IPS\DateTime );

		if( isset( \IPS\Request::i()->visitDateStart ) AND isset( \IPS\Request::i()->visitDateEnd ) )
		{
			$defaults = array( 'start' => \IPS\DateTime::ts( \IPS\Request::i()->visitDateStart ), 'end' => \IPS\DateTime::ts( \IPS\Request::i()->visitDateEnd ) );
		}

		$form = new \IPS\Helpers\Form( 'visits', 'continue' );
		$form->add( new \IPS\Helpers\Form\DateRange( 'visit_date', $defaults, TRUE ) );

		if( $values = $form->values() )
		{
			/* Determine start and end time */
			$startTime	= $values['visit_date']['start']->getTimestamp();
			$endTime	= $values['visit_date']['end']->getTimestamp();

			$start		= $values['visit_date']['start']->html();
			$end		= $values['visit_date']['end']->html();
		}
		else
		{
			/* Determine start and end time */
			$startTime	= $defaults['start']->getTimestamp();
			$endTime	= $defaults['end']->getTimestamp();

			$start		= $defaults['start']->html();
			$end		= $defaults['end']->html();
		}

		/* Do we have our date ranges? */
		if( $start AND $end )
		{
			/* Get the count */
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', array( 'last_visit BETWEEN ? AND ?', $startTime, $endTime ) )->first();
			
			/* And now build the table */
			$table = new \IPS\Helpers\Table\Db( 'core_members', \IPS\Request::i()->url()->setQueryString( array( 'visitDateStart' => $startTime, 'visitDateEnd' => $endTime ) ), array( array( 'last_visit BETWEEN ? AND ?', $startTime, $endTime ) ) );

			$table->include = array( 'name', 'last_visit' );
			$table->mainColumn = 'name';
			$table->langPrefix = 'visits_';

			/* Default sort options */
			$table->sortBy = $table->sortBy ?: 'last_visit';
			$table->sortDirection = $table->sortDirection ?: 'desc';
			
			/* Custom parsers */
			$table->parsers = array(
				'name'			=> function( $val, $row )
				{
					$member = \IPS\Member::constructFromData( $row );
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . ' ' . $member->link();
				},
				'last_visit'				=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->html();
				},
			);

			$table->extraHtml = \IPS\Theme::i()->getTemplate( 'stats' )->tableheader( $start, $end, $count );
		}

		$formHtml = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'stats' ) ), 'visitsFormTemplate' ) );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_membervisits');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'stats' )->membervisits( $formHtml, $count, $table );
	}
}