<?php
/**
 * @brief		Create Menu Extension : gallery
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension: gallery
 */
class _gallery
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{		
		if ( \IPS\gallery\Category::canOnAny( 'add' ) )
		{
			return array(
				'gallery_image' => array(
					'link' 		=> \IPS\Http\Url::internal( "app=gallery&module=gallery&controller=submit&_new=1", 'front', 'gallery_submit' ),
					'extraData'	=> array( 'data-ipsDialog-size' => "fullscreen", 'data-ipsDialog' => true )
				)
			);
		}

		return array();
	}
}