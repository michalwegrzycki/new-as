<?php
/**
 * @brief		todaysBirthdays Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		18 Dec 2013
 */

namespace IPS\calendar\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * todaysBirthdays Widget
 */
class _todaysBirthdays extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'todaysBirthdays';
	
	/**
	 * @brief	App
	 */
	public $app = 'calendar';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
 		if ( $form === null )
 		{
	 		$form = new \IPS\Helpers\Form;
 		} 
 		
		$form->add( new \IPS\Helpers\Form\YesNo( 'auto_hide', isset( $this->configuration['auto_hide'] ) ? $this->configuration['auto_hide'] : FALSE, FALSE ) );
		return $form;
 	} 

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'calendar', 'calendar' ) ) )
		{
			return '';
		}

		$date		= \IPS\calendar\Date::getDate();
		$birthdays	= $date->getBirthdays( TRUE );
		$totalCount	= $date->getBirthdays( TRUE, TRUE );

		if( !isset( $birthdays[ $date->mon . $date->mday ] ) )
		{
			$birthdays[ $date->mon . $date->mday ]	= array();
		}

		/* Auto hiding? */
		if( !count( $birthdays[ $date->mon . $date->mday ] ) AND isset( $this->configuration['auto_hide'] ) AND $this->configuration['auto_hide'] )
		{
			return '';
		}

		return $this->output( $birthdays[ $date->mon . $date->mday ], isset( $totalCount[ $date->mon . $date->mday ] ) ? $totalCount[ $date->mon . $date->mday ] : 0, $date );
	}
}