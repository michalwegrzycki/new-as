<?php
/**
 * @brief		Calendars API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		3 Apr 2017
 */

namespace IPS\calendar\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Calendars API
 */
class _calendars extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\calendar\Calendar';

	/**
	 * GET /calendar/calendar
	 * Get list of calendars
	 *
	 * @return		\IPS\Api\PaginatedResponse<IPS\calendar\Calendar>
	 */
	public function GETindex()
	{
		/* Return */
		return $this->_list();
	}

	/**
	 * GET /calendar/calendar/{id}
	 * Get specific calendar
	 *
	 * @param		int		$id			ID Number
	 *
	 * @return		\IPS\Api\PaginatedResponse<IPS\calendar\Calendar>
	 */
	public function GETitem( $id )
	{
		/* Return */
		return $this->_view( $id );
	}

	/**
	 * POST /calendar/calendar
	 * Create a calendar
	 *
	 * @reqapiparam	string		title				The calendar title
	 * @apiparam	string		color				The calendar color (Hexadecimal)
	 * @apiparam	int			approve_events		0|1 Events must be approved?
	 * @apiparam	int			allow_comments		0|1 Allow comments
	 * @apiparam	int			approve_comments	0|1 Comments must be approved
	 * @apiparam	int			allow_reviews		0|1 Allow reviews
	 * @apiparam	int			approve_reviews		0|1 Reviews must be approved
	 *
	 * @return		\IPS\calendar\Calendar
	 */
	public function POSTindex()
	{
		return $this->_create();
	}

	/**
	 * POST /calendar/calendar/{id}
	 * Edit a calendar
	 *
	 * @reqapiparam	string		title				The calendar title
	 * @apiparam	string		color				The calendar color (Hexadecimal)
	 * @apiparam	int			approve_events		0|1 Events must be approved?
	 * @apiparam	int			allow_comments		0|1 Allow comments
	 * @apiparam	int			approve_comments	0|1 Comments must be approved
	 * @apiparam	int			allow_reviews		0|1 Allow reviews
	 * @apiparam	int			approve_reviews		0|1 Reviews must be approved
	 *
	 * @return		\IPS\calendar\Calendar
	 */
	public function POSTitem( $id )
	{
		$class = $this->class;
		$calendar = $class::load( $id );
		$calendar = $this->_createOrUpdate( $calendar );

		return $calendar;
	}

	/**
	 * DELETE /calendar/calendar/{id}
	 * Delete a calendar
	 *
	 * @param		int		$id			ID Number
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		return $this->_delete( $id );
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\node\Model	$node				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\node\Model $calendar )
	{
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '', 400 );
		}

		if ( isset( \IPS\Request::i()->title ) )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_calendar_' . $calendar->id, \IPS\Request::i()->title );
			$calendar->title_seo	= \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->title );
		}

		if ( isset( \IPS\Request::i()->color ) )
		{
			$calendar->color = \IPS\Request::i()->color;
		}

		$calendar->moderate 		= (int) isset( \IPS\Request::i()->approve_events ) ? \IPS\Request::i()->approve_events : 0;
		$calendar->allow_comments	= (int) isset( \IPS\Request::i()->allow_comments ) ? \IPS\Request::i()->allow_comments : 0;
		$calendar->comment_moderate = (int) isset( \IPS\Request::i()->approve_comments ) ? \IPS\Request::i()->approve_comments : 0;
		$calendar->allow_reviews 	= (int) isset( \IPS\Request::i()->allow_reviews ) ? \IPS\Request::i()->allow_reviews : 0;
		$calendar->review_moderate	= (int) isset( \IPS\Request::i()->approve_reviews ) ? \IPS\Request::i()->approve_reviews : 0;

		return parent::_createOrUpdate( $calendar );
	}
}