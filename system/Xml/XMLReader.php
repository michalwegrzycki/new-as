<?php
/**
 * @brief		Wrapper class for managing XMLReader objects
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 July 2016
 */

namespace IPS\Xml;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Wrapper class for managing XMLReader objects
 */
class _XMLReader extends \XMLReader
{
	/**
	 * Open a file or URL with XMLReader to read it
	 *
	 * @param	string	$uri		The URI/path to open
	 * @param	string	$encoding	The encoding to use, or NULL
	 * @param	int		$options	Bitmask of LIBXML_* constants
	 * @return	bool
	 * @note	We are disabling the entity loader after opening the content to prevent XXE
	 */
	public function open( $uri, $encoding=NULL, $options=0 )
	{
		$entityLoaderValue = libxml_disable_entity_loader( false );
		$opened = parent::open( $uri, $encoding, $options );
		libxml_disable_entity_loader( $entityLoaderValue );

		return $opened;
	}
}