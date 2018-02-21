<?php
/**
 * @brief		Advertisements Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Sept 2013
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Advertisements Model
 */
class _Advertisement extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_advertisements';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'ad_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	Advertisements loaded during this request (used to update impression count)
	 * @see		static::updateImpressions()
	 */
	public static $advertisementIds	= array();

	/**
	 * @brief	Stored advertisements we can display on this page
	 */
	protected static $advertisements = NULL;

	/**
	 * Fetch advertisements and return the appropriate one to display
	 *
	 * @param	string	$location	Advertisement location
	 * @return	\IPS\core\Advertisement|NULL
	 */
	public static function loadByLocation( $location )
	{
		/* If we know there are no ads, we don't need to bother */
		if ( !\IPS\Settings::i()->ads_exist )
		{
			return NULL;
		}
		
		/* Fetch our advertisements, if we haven't already done so */
		if( static::$advertisements  === NULL )
		{
			static::$advertisements = array();

			foreach( \IPS\Db::i()->select( '*' ,'core_advertisements', array( "ad_active=1 AND ad_start < ? AND ( ad_end=0 OR ad_end > ? )", time(), time() ) ) as $row )
			{
				foreach ( explode( ',', $row['ad_location'] ) as $_location )
				{
					static::$advertisements[ $_location ][] = static::constructFromData( $row );
				}
			}
		}

		/* Weed out any we don't see due to our group. This is done after loading the advertisements so that the cache can be properly primed regardless of group. Note that $ad->exempt, is, confusingly who to SHOW to, not who is exempt */
		foreach( static::$advertisements as $adLocation => $ads )
		{
			foreach( $ads as $index => $ad )
			{
				if ( $ad->exempt != '*' )
				{
					$groupsToHideFrom = array_diff( array_keys(\IPS\Member\Group::groups()), json_decode( $ad->exempt, TRUE ) );

					if ( \IPS\Member::loggedIn()->inGroup( $groupsToHideFrom ) )
					{
						unset( static::$advertisements[ $adLocation ][ $index ] );
						continue;
					}
				}
			}
		}

		/* No advertisements? Just return then */
		if( !count( static::$advertisements ) OR !isset( static::$advertisements[ $location ] ) OR !count( static::$advertisements[ $location ] ) )
		{
			return NULL;
		}

		/* Reset so doesn't throw error */
		static::$advertisements[ $location ] = array_values( static::$advertisements[ $location ] );

		/* If we only have one, that is the one we will show */
		if( count( static::$advertisements[ $location ] ) === 1 )
		{
			$advertisement	= static::$advertisements[ $location ][0];
		}
		else
		{
			/* Figure out which one to show you */
			switch( \IPS\Settings::i()->ads_circulation )
			{
				case 'random':
					$advertisement	= static::$advertisements[ $location ][ array_rand( static::$advertisements[ $location ] ) ];
				break;

				case 'newest':
					usort( static::$advertisements[ $location ], function( $a, $b ){
						return strcmp( $a->start, $b->start );
					} );

					$advertisement	= static::$advertisements[ $location ][0];
				break;

				case 'oldest':
					usort( static::$advertisements[ $location ], function( $a, $b ){
						return strcmp( $b->start, $a->start );
					} );

					$advertisement	= static::$advertisements[ $location ][0];
				break;

				case 'least':
					usort( static::$advertisements[ $location ], function( $a, $b ){
						if ( $a->impressions == $b->impressions )
						{
							return 0;
						}
						
						return ( $a->impressions < $b->impressions ) ? -1 : 1;
					} );

					$advertisement	= static::$advertisements[ $location ][0];
				break;
			}
		}

		return $advertisement;
	}

	/**
	 * Convert the advertisement to an HTML string
	 *
	 * @return	string
	 */
	public function __toString()
	{
		/* Showing HTML or an image? */
		if( $this->html )
		{
			if( \IPS\Request::i()->isSecure() AND $this->html_https_set )
			{
				$result	= $this->html_https;
			}
			else
			{
				$result	= $this->html;
			}
		}
		else
		{
			$result	= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->advertisementImage( $this );
		}

		/* Did we just hit the maximum impression count? If so, disable and then clear the cache so it will rebuild next time. */
		if( $this->maximum_unit == 'i' AND $this->maximum_value > -1 AND $this->impressions + 1 >= $this->maximum_value )
		{
			$this->active	= 0;
			$this->save();
			
			if ( !\IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first() )
			{
				\IPS\Settings::i()->changeValues( array( 'ads_exist' => 0 ) );
			}			
		}

		/* Store the id so we can update impression count and return the ad */
		static::$advertisementIds[]	= $this->id;
		
		return $result;
	}

	/**
	 * Get images
	 *
	 * @return	array
	 */
	public function get__images()
	{
		if( !isset( $this->_data['_images'] ) )
		{
			$this->_data['_images']	= $this->_data['images'] ? json_decode( $this->_data['images'], TRUE ) : array();
		}

		return $this->_data['_images'];
	}
	
	/**
	 * Get additional settings
	 *
	 * @return	array
	 */
	public function get__additional_settings()
	{
		if( !isset( $this->_data['_additional_settings'] ) )
		{
			$this->_data['_additional_settings'] = $this->_data['additional_settings'] ? json_decode( $this->_data['additional_settings'], TRUE ) : array();
		}

		return $this->_data['_additional_settings'];
	}

	/**
	 * Get the file system storage extension
	 *
	 * @return string
	 */
	public function storageExtension()
	{
		if ( $this->member )
		{
			return 'nexus_Ads';
		}
		else
		{
			return 'core_Advertisements';
		}
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* If we have images, delete them */
		if( count( $this->_images ) )
		{
			\IPS\File::get( $this->storageExtension(), $this->_images['large'] )->delete();

			if( isset( $this->_images['small'] ) )
			{
				\IPS\File::get( $this->storageExtension(), $this->_images['small'] )->delete();
			}

			if( isset( $this->_images['medium'] ) )
			{
				\IPS\File::get( $this->storageExtension(), $this->_images['medium'] )->delete();
			}
		}
		
		/* Delete */
		parent::delete();
		
		/* Make sure we still have active ads */
		if ( !\IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first() )
		{
			\IPS\Settings::i()->changeValues( array( 'ads_exist' => 0 ) );
		}
	}

	/**
	 * Update ad impressions for advertisements loaded
	 *
	 * @return	void
	 */
	public static function updateImpressions()
	{
		if( count( static::$advertisementIds ) )
		{
			\IPS\Db::i()->update( 'core_advertisements', "ad_impressions=ad_impressions+1", "ad_id IN(" . implode( ',', static::$advertisementIds ) . ")" );
		}
	}
}