<?php
/**
 * @brief		Pinterest share link
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Dec 2016
 */

namespace IPS\Content\ShareServices;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Pinterest share link
 */
class _Pinterest
{
	/**
	 * @brief	URL to the content item
	 */
	protected $url		= NULL;

	/**
	 * @brief	Title of the content item
	 */
	protected $title	= NULL;

	/**
	 * @brief	Ccontent item
	 */
	protected $item	= NULL;
		
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	URL to the content [optional - if omitted, some services will figure out on their own]
	 * @param	string			$title	Default text for the content, usually the title [optional - if omitted, some services will figure out on their own]
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL, $title=NULL, $item=NULL )
	{
		$this->url		= $url;
		$this->title	= $title;
		$this->item		= $item;
	}
		
	/**
	 * Determine whether the logged in user has the ability to autoshare
	 *
	 * @return	boolean
	 */
	public static function canAutoshare()
	{
		return FALSE;
	}

	/**
	 * Add any additional form elements to the configuration form. These must be setting keys that the service configuration form can save as a setting.
	 *
	 * @param	\IPS\Helpers\Form				$form		Configuration form for this service
	 * @param	\IPS\core\ShareLinks\Service	$service	The service
	 * @return	void
	 */
	public static function modifyForm( \IPS\Helpers\Form &$form, $service )
	{
	}

	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	public function __toString()
	{
		if ( $this->item )
		{
			return \IPS\Theme::i()->getTemplate( 'sharelinks', 'core' )->pinterest( \IPS\Http\Url::external( 'http://pinterest.com/pin/create/button/' )->setQueryString( 'url', (string) $this->url )->setQueryString( 'media', (string) $this->item->shareImage() ) );
		}
		else
		{
			return '';
		}
	}
}