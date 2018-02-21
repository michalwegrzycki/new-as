<?php
/**
 * @brief		Internal Promotion
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 FEB 2017
 */

namespace IPS\Content\Promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Internal Promotion
 */
class _Internal extends PromoteAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'plus';
	
	/**
	 * @brief Default settings
	 */
	public $defaultSettings = array();
	
	/**
	 * Get image
	 *
	 * @return string
	 */
	public function getPhoto()
	{
		return \IPS\Theme::i()->settings['logo_sharer'] ? \IPS\Theme::i()->settings['logo_sharer'] : \IPS\Theme::i()->settings['logo_front'];
	}
	
	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName()
	{
		return \IPS\Settings::i()->board_name;
	}
	
	/**
	 * Get form elements for this share service
	 *
	 * @param	string		$text		Text for the text entry
	 * @param	string		$link		Short or full link (short when available)
	 * @param	string		$content	Additional text content (usually a comment, or the item content)
	 *
	 * @return array of form elements
	 */
	public function form( $text, $link=null, $content=null )
	{
		return array( new \IPS\Helpers\Form\TextArea( 'promote_social_content_internal', $content ?: $text, FALSE, array( 'maxLength' => 3000, 'rows' => 6 ) ) );
	}

	 
	/**
	 * Post to internal
	 *
	 * @param	\IPS\Content\Promote	$promote 	Promote Object
	 * @return void
	 */
	public function post( $promote )
	{
		return time();
	}
	
	/**
	 * Return the published URL
	 *
	 * @param	array	$data	Data returned from a successful POST
	 * @return	\IPS\Http\Url
	 * @throws InvalidArgumentException
	 */
	public function getUrl( $data )
	{
		return NULL;
	}
}