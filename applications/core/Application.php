<?php
/**
 * @brief		Core Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */
 
namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * @brief	Cached advertisement count
	 */
	protected $advertisements	= NULL;

	/**
	 * ACP Menu Numbers
	 *
	 * @param	array	$queryString	Query String
	 * @return	int
	 */
	public function acpMenuNumber( $queryString )
	{
		parse_str( $queryString, $queryString );
		switch ( $queryString['controller'] )
		{
			case 'advertisements':
				if( $this->advertisements === NULL )
				{
					$this->advertisements	= \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( 'ad_active=-1' ) )->first();
				}

				return $this->advertisements;
				break;
		}
	}
	
	/**
	 * Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	public function get__badge()
	{
		$return = parent::get__badge();
		
		if ( $return )
		{
			$availableUpgrade = $this->availableUpgrade( TRUE, FALSE );
			$return[2] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->updatebadge( $availableUpgrade['version'], \IPS\Http\Url::internal( 'app=core&module=system&controller=upgrade', 'admin' ), $availableUpgrade['released'], FALSE );
		}
		
		return $return;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'cogs';
	}
	
	/**
	 * Can view page even when user is a guest when guests cannot access the site
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowGuestAccess( \IPS\Application\Module $module, $controller, $do )
	{
		return (
			$module->key == 'system'
			and
			in_array( $controller, array( 'login', 'register', 'lostpass', 'terms', 'ajax', 'privacy', 'editor', 'language', 'theme' ) )
		)
		or
		( 
			$module->key == 'contact' and $controller == 'contact'
		);
	}
	
	/**
	 * Can view page even when site is offline
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowOfflineAccess( \IPS\Application\Module $module, $controller, $do )
	{
		return (
			$module->key == 'system'
			and
			(
				in_array( $controller, array(
					'login', // Because you can login when offline
					'embed', // Because the offline message can contain embedded media
					'lostpass',
					'register'
				) )
				or
				(
					$controller === 'ajax' and 
						( $do === 'states' OR  // Makes sure address input still works within the ACP otherwise the form to turn site back online is broken
						$do === 'passwordStrength' ) // Makes sure the password strength meter still works because it is used in the AdminCP and registration
				)
			)
		);
	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		$activityTabs = array(
			array( 'key' => 'AllActivity' ),
			array( 'key' => 'YourActivityStreams' ),
		);
		
		foreach ( array( 1, 2 ) as $k )
		{
			try
			{
				\IPS\core\Stream::load( $k );
				$activityTabs[] = array(
					'key'		=> 'YourActivityStreamsItem',
					'config'	=> array( 'menu_stream_id' => $k )
				);
			}
			catch ( \Exception $e ) { }
		}

		$activityTabs[] = array( 'key' => 'Search' );
		$activityTabs[] = array( 'key' => 'Promoted' );
		
		return array(
			'rootTabs'		=> array(),
			'browseTabs'	=> array(
				array( 'key' => 'Clubs' )
			),
			'browseTabsEnd'	=> array(
				array( 'key' => 'Guidelines' ),
				array( 'key' => 'StaffDirectory' ),
				array( 'key' => 'OnlineUsers' ),
				array( 'key' => 'Leaderboard' )
			),
			'activityTabs'	=> $activityTabs
		);
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		/* Convert &section= to &controller= */
		if ( isset( \IPS\Request::i()->section ) AND !isset( \IPS\Request::i()->controller ) )
		{
			\IPS\Request::i()->controller = \IPS\Request::i()->section;
		}

		/* Convert &showuser= */
		if ( isset( \IPS\Request::i()->showuser ) and is_numeric( \IPS\Request::i()->showuser ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=profile&id=' . \IPS\Request::i()->showuser ) );
		}
		
		/* Redirect ?app=core&module=attach&section=attach&attach_rel_module=post&attach_id= */
		if ( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'attach' AND isset( \IPS\Request::i()->attach_id ) AND is_numeric( \IPS\Request::i()->attach_id ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "applications/core/interface/file/attachment.php?id=" . \IPS\Request::i()->attach_id, 'none' ) );
		}

		/* redirect vnc to new streams */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND  isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'vnc' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discover&controller=streams' ) );
		}

		/* redirect 4.0 activity page to streams */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND isset( \IPS\Request::i()->module ) AND (\IPS\Request::i()->module == 'activity' ) AND isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'activity' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discover&controller=streams' ) );
		}

		/* redirect old messenger link */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'members' AND isset( \IPS\Request::i()->module ) AND (\IPS\Request::i()->module == 'messaging' ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=messaging&controller=messenger', 'front', 'messaging' ) );
		}

		/* redirect old messenger link */
		if( isset( \IPS\Request::i()->module ) AND \IPS\Request::i()->module == 'global' AND isset( \IPS\Request::i()->controller ) AND (\IPS\Request::i()->controller == 'register' ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register', NULL, \IPS\Settings::i()->logins_over_https ) );
		}

		/* redirect old reports */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND
			isset( \IPS\Request::i()->module ) AND (\IPS\Request::i()->module == 'reports' ) AND
			isset( \IPS\Request::i()->do ) AND ( \IPS\Request::i()->do == 'show_report' )  )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports&action=view&id=' . \IPS\Request::i()->rid , 'front', 'modcp_report' ) );
		}
	}
}
