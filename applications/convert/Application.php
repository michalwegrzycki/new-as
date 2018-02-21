<?php
/**
 * @brief		Converter Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 * @version		
 */
 
namespace IPS\convert;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Converter Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'random';
	}

	/**
	 * Install Other
	 *
	 * @return	void
	 */
	public function installOther()
	{
		static::checkConvParent();
		
		try
		{
			\IPS\Db::i()->select( '*', 'core_login_handlers', array( "login_key=?", 'Convert' ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$position = \IPS\Db::i()->select( 'MAX(login_order)', 'core_login_handlers' )->first();
			\IPS\Db::i()->insert( 'core_login_handlers', array(
				'login_key'			=> "Convert",
				'login_enabled'		=> 1,
				'login_settings'	=> json_encode( array( 'auth_types' => \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL ) ),
				'login_order'		=> $position + 1,
				'login_acp'			=> 1
			) );
			
			if ( isset( \IPS\Data\Store::i()->loginHandlers ) )
			{
				unset( \IPS\Data\Store::i()->loginHandlers );
			}
		}
	}
	
	/**
	 * Ensure the appropriate tables have a conv_parent column for internal references
	 *
	 * @param	string|NULL		The application to check, or NULL to check all.
	 * @return	void
	 */
	public static function checkConvParent( $application=NULL )
	{
		$parents = array(
			'downloads'	=> array(
				'tables'	=> array(
					'downloads_categories'	=> array(
						'prefix'	=> 'c',
						'column'	=> 'conv_parent'
					)
				)
			),
			'forums'	=> array(
				'tables'	=> array(
					'forums_forums'			=> array(
						'prefix'	=> '',
						'column'	=> 'conv_parent'
					)
				)
			),
			'gallery'	=> array(
				'tables'	=> array(
					'gallery_categories'	=> array(
						'prefix'	=> 'category_',
						'column'	=> 'conv_parent'
					)
				)
			),
			'cms'		=> array(
				'tables'	=> array(
					'cms_containers'			=> array(
						'prefix'	=> 'container_',
						'column'	=> 'conv_parent'
					),
					'cms_database_categories'	=> array(
						'prefix'	=> 'category_',
						'column'	=> 'conv_parent'
					),
					'cms_folders'				=> array(
						'prefix'	=> 'folder_',
						'column'	=> 'conv_parent'
					)
				)
			),
			'nexus'		=> array(
				'tables'	=> array(
					'nexus_alternate_contacts'	=> array(
						'prefix'	=> '',
						'column'	=> 'conv_alt_id',
					),
					'nexus_package_groups'		=> array(
						'prefix'	=> 'pg_',
						'column'	=> 'conv_parent'
					),
					'nexus_packages'			=> array(
						'prefix'	=> 'p_',
						'column'	=> 'conv_associable'
					),
					'nexus_purchases'			=> array(
						'prefix'	=> 'ps_',
						'column'	=> 'conv_parent'
					)
				)
			)
		);
		
		foreach( $parents AS $app => $tables )
		{
			if ( !is_null( $application ) )
			{
				if ( $application != $app )
				{
					continue;
				}
			}
			
			if ( static::appisEnabled( $app ) )
			{
				foreach( $tables['tables'] AS $table => $data )
				{
					$column = $data['prefix'] . $data['column'];
					if ( \IPS\Db::i()->checkForColumn( $table, $column ) === FALSE )
					{
						\IPS\Db::i()->addColumn( $table, array(
							'name'		=> $column,
							'type'		=> 'BIGINT',
							'length'	=> 20,
							'default'	=> 0,
						) );
					}
				}
			}
		}
	}

	/**
	 * Check if we need to redirect requests coming in to index.php
	 *
	 * @note	In an ideal world, we'd check the individual converter libraries, however that requires looping over lots of files or
	 *	querying the database on every single page load, so instead we will sniff and see if we think anything needs to be done based
	 *	on hardcoded potential patterns.
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		$_qs = '';
		if ( isset( $_SERVER['QUERY_STRING'] ) )
		{
			$_qs = $_SERVER['QUERY_STRING'];
		}
		elseif ( isset( $_SERVER['PATH_INFO'] ) )
		{
			$_qs = $_SERVER['PATH_INFO'];
		}
		elseif ( isset( $_SERVER['REQUEST_URI'] ) )
		{
			$_qs = $_SERVER['REQUEST_URI'];
		}

		/* Expression Engine */
		preg_match ( '#(viewforum|viewthread|viewreply|member)\/([0-9]+)#i', $_qs, $matches );
		if( isset( $matches[1] ) AND $matches[1] )
		{
			static::checkRedirects();
		}

		/* Vanilla */
		preg_match ( '#(discussion|profile)\/([0-9]+)\/#i', $_qs, $matches );

		if( isset( $matches[1] ) AND $matches[1] )
		{
			static::checkRedirects();
		}

		/* Xenforo */
		preg_match ( '#(forums|threads|members)\/(.*)\.([0-9]+)#i', $_qs, $matches );

		if( isset( $matches[1] ) AND $matches[1] )
		{
			static::checkRedirects();
		}

		/* SMF */
		if( \IPS\Request::i()->board OR \IPS\Request::i()->topic OR \IPS\Request::i()->action )
		{
			static::checkRedirects();
		}
	}

	/**
	 * Check if we need to redirect
	 *
	 * @return void
	 */
	public static function checkRedirects()
	{
		/* Try each of our converted applications. We will assume the most important conversions were done first */
		foreach( \IPS\convert\App::apps() as $app )
		{
			try
			{
				$redirect	= $app->getSource( TRUE, FALSE )->checkRedirects();
			}
			catch( \InvalidArgumentException $e )
			{
				/* This converter app doesn't exist on disk, this is expected for sites upgraded from 3.x where there isn't a 4.x version of the converter app */
				continue;
			}

			/* Pass the request off to the application to see if it can redirect */
			if( $redirect !== NULL )
			{
				/* We got a valid redirect, so send the user there */
				\IPS\Output::i()->redirect( $redirect, NULL, 301 );
			}
		}
	}
}