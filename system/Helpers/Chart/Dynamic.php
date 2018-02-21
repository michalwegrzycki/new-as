<?php
/**
 * @brief		Dynamic Chart Builder Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Mar 2017
 */

namespace IPS\Helpers\Chart;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dynamic Chart Helper
 */
abstract class _Dynamic extends \IPS\Helpers\Chart
{
	/**
	 * @brief	URL
	 */
	public $url;

	/**
	 * @brief	$timescale (daily, weekly, monthly)
	 */
	public $timescale = 'monthly';

	/**
	 * @brief	Unique identifier for URLs
	 */
	public $identifier	= '';
	
	/**
	 * @brief	Start Date
	 */
	public $start;
	
	/**
	 * @brief	End Date
	 */
	public $end;
	
	/**
	 * @brief	Series
	 */
	protected $series = array();
	
	/**
	 * @brief	Title
	 */
	public $title;
	
	/**
	 * @brief	Google Chart Options
	 */
	public $options = array();
	
	/**
	 * @brief	Type
	 */
	public $type;
	
	/**
	 * @brief	Available Types
	 */
	public $availableTypes = array( 'AreaChart', 'LineChart', 'ColumnChart', 'BarChart', 'PieChart', 'Table' );
	
	/**
	 * @brief	Available Filters
	 */
	public $availableFilters = array();
	
	/**
	 * @brief	Current Filters
	 */
	public $currentFilters = array();

	/**
	 * @brief	Plot zeros
	 */
	public $plotZeros = TRUE;
	
	/**
	 * @brief	Value for number formatter
	 */
	public $format = NULL;

	/**
	 * @brief	Allow user to adjust interval (group by daily, monthly, etc.)
	 */
	public $showIntervals = TRUE;
	
	/**
	 * @brief	If a warning about timezones needs to be shown
	 */
	public $timezoneError = FALSE;

	/**
	 * @brief	If set to an \IPS\DateTime instance, minimum time will be checked against this value
	 */
	public $minimumDate = NULL;

	/**
	 * @brief	Error(s) to show on chart UI
	 */
	public $errors = array();
		
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url			The URL the chart will be displayed on
	 * @param	string			$title			Title
	 * @param	array			$options		Options
	 * @param	string			$defaultType	The default chart type
	 * @param	string			$defaultTimescale	The default timescale to use
	 * @param	array			$defaultTimes	The default start/end times to use
	 * @param	string			$identifier		If there will be more than one chart per page, provide a unique identifier
	 * @param	\IPS\DateTime|NULL	$minimumDate	The earliest available date for this chart
	 * @see		<a href='https://google-developers.appspot.com/chart/interactive/docs/gallery'>Charts Gallery - Google Charts - Google Developers</a>
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url, $title='', $options=array(), $defaultType='AreaChart', $defaultTimescale='monthly', $defaultTimes=array( 'start' => 0, 'end' => 0 ), $identifier='', $minimumDate=NULL )
	{
		if ( !isset( $options['chartArea'] ) )
		{
			$options['chartArea'] = array(
				'left'	=> '50',
				'width'	=> '75%'
			);
		}
		
		$this->baseURL		= $url;
		$this->title		= $title;
		$this->options		= $options;
		$this->timescale	= $defaultTimescale;
		$this->start		= $defaultTimes['start'];
		$this->end			= $defaultTimes['end'];
		$this->minimumDate	= $minimumDate;

		if ( isset( \IPS\Request::i()->type[ $this->identifier ] ) and in_array( \IPS\Request::i()->type[ $this->identifier ], $this->availableTypes ) )
		{
			$this->type = \IPS\Request::i()->type[ $this->identifier ];
			$url = $url->setQueryString( 'type', array( $this->identifier => $this->type ) );
		}
		else
		{
			$this->type = $defaultType;
		}

		if ( isset( \IPS\Request::i()->timescale[ $this->identifier ] ) and in_array( \IPS\Request::i()->timescale[ $this->identifier ], array( 'daily', 'weekly', 'monthly' ) ) )
		{
			$this->timescale = \IPS\Request::i()->timescale[ $this->identifier ];
			$url = $url->setQueryString( 'timescale', array( $this->identifier => \IPS\Request::i()->timescale[ $this->identifier ] ) );
		}

		if ( $this->type === 'PieChart' or $this->type === 'GeoChart' )
		{
			$this->addHeader( 'key', 'string' );
			$this->addHeader( 'value', 'number' );
		}
		else
		{
			$this->addHeader( \IPS\Member::loggedIn()->language()->addToStack('date'), ( $this->timescale == 'none' ) ? 'datetime' : 'date' );
		}

		if ( isset( \IPS\Request::i()->start[ $this->identifier ] ) and \IPS\Request::i()->start[ $this->identifier ] )
		{
			try
			{
				$originalStart = $this->start;

				if ( is_numeric( \IPS\Request::i()->start[ $this->identifier ] ) )
				{
					$this->start = \IPS\DateTime::ts( \IPS\Request::i()->start[ $this->identifier ] );
				}
				else
				{
					$this->start = new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->start[ $this->identifier ] ), new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
				}

				if( $this->minimumDate > $this->start )
				{
					$this->errors[] = array( 'string' => 'minimum_chart_date', 'sprintf' => $this->minimumDate->localeDate() );
					$this->start = $originalStart;
				}
				else
				{
					unset( $originalStart );
				}

				if( $this->start )
				{
					$url = $url->setQueryString( 'start', array( $this->identifier => $this->start->getTimestamp() ) );
				}
			}
			catch ( \Exception $e ) {}
		}

		if ( isset( \IPS\Request::i()->end[ $this->identifier ] ) and \IPS\Request::i()->end[ $this->identifier ] )
		{
			try
			{
				if ( is_numeric( \IPS\Request::i()->end[ $this->identifier ] ) )
				{
					$this->end = \IPS\DateTime::ts( \IPS\Request::i()->end[ $this->identifier ] );
				}
				else
				{
					$this->end = new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->end[ $this->identifier ] ), new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
				}

				/* The end date should include items to the end of the day */
				$this->end->setTime( 23, 59, 59 );

				$url = $url->setQueryString( 'end', array( $this->identifier => $this->end->getTimestamp() ) );
			}
			catch ( \Exception $e ) {}
		}

		if ( isset( \IPS\Request::i()->filters[ $this->identifier ] ) )
		{
			$url = $url->setQueryString( 'filters', '' );
		}
		
		$this->url = $url;
		
		if ( \IPS\Member::loggedIn()->timezone and in_array( \IPS\Member::loggedIn()->timezone, \DateTimeZone::listIdentifiers() ) )
		{
			try
			{
				$r = \IPS\Db::i()->query( "SELECT TIMEDIFF( NOW(), CONVERT_TZ( NOW(), @@session.time_zone, '" . \IPS\Db::i()->escape_string( \IPS\Member::loggedIn()->timezone ) . "' ) );" )->fetch_row();
				if ( $r[0] === NULL )
				{
					$this->timezoneError = TRUE;
				}
			}
			catch ( \IPS\Db\Exception $e )
			{
				$this->timezoneError = TRUE;
			}
		}
	}
	
	/**
	 * Get the chart output
	 *
	 * @return string
	 */
	abstract public function getOutput();

	/**
	 * HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			/* Get data */
			$output = '';
			if ( !empty( $this->series ) )
			{
				$output = $this->getOutput();
			}
			else
			{
				$output = \IPS\Member::loggedIn()->language()->addToStack('chart_no_results');
			}

			/* Display */
			if ( \IPS\Request::i()->noheader )
			{
				return $output;
			}
			else
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->dynamicChart( $this, $output );
			}
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
		
	/**
	 * Flip URL Filter
	 *
	 * @param	string	$filter	The Filter
	 * @return	\IPS\Http\Url
	 */
	public function flipUrlFilter( $filter )
	{
		$filters = $this->currentFilters;
		
		if ( in_array( $filter, $filters ) )
		{
			unset( $filters[ array_search( $filter, $filters ) ] );
		}
		else
		{
			$filters[] = $filter;
		}
		
		return $this->url->setQueryString( 'filters', array( $this->identifier => $filters ) );
	}

	/**
	 * Init the data array
	 *
	 * @return array
	 */
	protected function initData()
	{
		/* Init data */
		$data = array();
		if ( $this->start AND $this->timescale !== 'none' )
		{
			$date = clone $this->start;
			while ( $date->getTimestamp() < ( $this->end ? $this->end->getTimestamp() : time() ) )
			{
				switch ( $this->timescale )
				{
					case 'daily':
						$data[ $date->format( 'Y-n-j' ) ] = array();

						$date->add( new \DateInterval( 'P1D' ) );
						break;
						
					case 'weekly':
						/* o is the ISO year number, which we need when years roll over.
							@see http://php.net/manual/en/function.date.php#106974 */
						$data[ $date->format( 'o-W' ) ] = array();

						$date->add( new \DateInterval( 'P7D' ) );
						break;
						
					case 'monthly':
						$data[ $date->format( 'Y-n' ) ] = array();

						$date->add( new \DateInterval( 'P1M' ) );
						break;
				}
			}
		}

		return $data;
	}
}