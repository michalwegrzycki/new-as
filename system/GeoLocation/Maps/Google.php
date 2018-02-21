<?php
/**
 * @brief		Google Maps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Apr 2013
 */

namespace IPS\GeoLocation\Maps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Google Maps
 */
class _Google
{	
	/**
	 * @brief	GeoLocation
	 */
	public $geoLocation;

	/**
	 * Constructor
	 *
	 * @param	\IPS\GeoLocation	$geoLocation	Location
	 * @return	void
	 */
	public function __construct( \IPS\GeoLocation $geoLocation )
	{
		$this->geolocation	= $geoLocation;
	}
	
	/**
	 * Render
	 *
	 * @param	int			$width	Width
	 * @param	int			$height	Height
	 * @param	float|NULL	$zoom	The zoom amount (a value between 0 being totally zoomed out view of the world, and 1 being as fully zoomed in as possible) or NULL to zoom automatically based on how much data is available
	 * @return	string
	 */
	public function render( $width, $height, $zoom=NULL )
	{
		if ( $this->geolocation->lat and $this->geolocation->long )
		{
			$location = sprintf( "%F", $this->geolocation->lat ) . ',' . sprintf( "%F", $this->geolocation->long );
		}
		else
		{
			$location = array();
			foreach ( array( 'postalCode', 'country', 'region', 'city', 'addressLines' ) as $k )
			{
				if ( $this->geolocation->$k )
				{
					if ( is_array( $this->geolocation->$k ) )
					{
						foreach ( array_reverse( $this->geolocation->$k ) as $v )
						{
							if( $v )
							{
								$location[] = $v;
							}
						}
					}
					else
					{
						$location[] = $this->geolocation->$k;
					}
				}
			}
			$location = implode( ', ', array_reverse( $location ) );
		}

		$linkUrl = \IPS\Http\Url::external( 'https://maps.google.com/' )->setQueryString( 'q', $location );
				
		$imageUrl = \IPS\Http\Url::external( 'https://maps.googleapis.com/maps/api/staticmap' )->setQueryString( array(
			'center'	=> $location,
			'zoom'		=> $zoom === NULL ? NULL : ceil( $zoom * 8 ),
			'size'		=> "{$width}x{$height}",
			'sensor'	=> 'false',
			'markers'	=> $location,
			'key'		=> \IPS\Settings::i()->google_maps_api_key,
		) );
		
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->googleMap( $linkUrl, $imageUrl, $this->geolocation->lat, $this->geolocation->long );
	}
}