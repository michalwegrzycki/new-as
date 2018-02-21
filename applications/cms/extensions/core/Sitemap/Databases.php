<?php
/**
 * @brief		Support Pages Databases in sitemaps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		1 April 2015
 */

namespace IPS\cms\extensions\core\Sitemap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Pages Databases in sitemaps
 */
class _Databases
{
	/**
	 * @brief	Recommended Settings
	 */
	public $recommendedSettings = array(
		'sitemap_databases_count'	 => -1,
		'sitemap_databases_priority' => 1
	);
	
	/**
	 * Settings for ACP configuration to the form
	 *
	 * @return	array
	 */
	public function settings()
	{
		return array(
			'sitemap_databases_count'	 => new \IPS\Helpers\Form\Number( 'sitemap_databases_count', \IPS\Settings::i()->sitemap_databases_count, FALSE, array( 'min' => '-1', 'unlimited' => '-1' ), NULL, NULL, NULL, 'sitemap_databases_count' ),
			'sitemap_databases_priority' => new \IPS\Helpers\Form\Select( 'sitemap_databases_priority', \IPS\Settings::i()->sitemap_databases_priority, FALSE, array( 'options' => \IPS\Sitemap::$priorities, 'unlimited' => '-1', 'unlimitedLang' => 'sitemap_dont_include' ), NULL, NULL, NULL, 'sitemap_databases_priority' )
		);
	}
	
	/**
	 * Get the sitemap filename(s)
	 *
	 * @return	array
	 */
	public function getFilenames()
	{
		$files = array();
		
		/* Check that guests can access the content at all */
		foreach( \IPS\cms\Databases::databases() as $database )
		{
			if ( $database->page_id > 0 )
			{
				try
				{
					if ( !$database->can( 'view', new \IPS\Member ) )
					{
						throw new \OutOfRangeException;
					}
				}
				catch ( \OutOfRangeException $e )
				{
					continue;
				}
				
				$class = '\IPS\cms\Records' . $database->id;
				
				if ( isset( $class::$containerNodeClass ) )
				{
					$nodeClass = $class::$containerNodeClass;
					
					/* We need one file for the nodes */
					$files[] = $database->id . '_sitemap_database_categories';
				}
				
				/* And however many for the content items */
				$count = ceil( max( (int) $class::getItemsWithPermission( $class::sitemapWhere(), NULL, 10, 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS, new \IPS\Member )->count( TRUE ), \IPS\Settings::i()->sitemap_databases_count ) / \IPS\Sitemap::MAX_PER_FILE );
				for( $i=1; $i <= $count; $i++ )
				{
					$files[] = $database->id . '_sitemap_database_records_' . $i;
				}
			}
		}
	
		return $files;
	}

	/**
	 * Generate the sitemap
	 *
	 * @param	string			$filename	The sitemap file to build (should be one returned from getFilenames())
	 * @param	\IPS\Sitemap	$sitemap	Sitemap object reference
	 * @return	void
	 */
	public function generateSitemap( $filename, $sitemap )
	{
		$tmp = explode( '_', $filename );
		$databaseId = intval( array_shift( $tmp ) );
		$database   = \IPS\cms\Databases::load( $databaseId );
		
		$class = '\IPS\cms\Records' . $databaseId;
		if ( isset( $class::$containerNodeClass ) )
		{
			$nodeClass = $class::$containerNodeClass;
		}
		$entries = array();
		
		if ( isset( $nodeClass ) and $filename == $databaseId . '_sitemap_database_categories' )
		{
			$select = array();
			if ( in_array( 'IPS\Content\Permissions', class_implements( $nodeClass ) ) or in_array( 'IPS\Node\Permissions', class_implements( $nodeClass ) ) )
			{
				$select = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $nodeClass::$databaseTable, array( '(' . \IPS\Db::i()->findInSet( 'perm_view', array( \IPS\Settings::i()->guest_group ) ) . ' OR ' . 'perm_view=? )', '*' ) )->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id={$nodeClass::$databaseTable}.{$nodeClass::$databasePrefix}{$nodeClass::$databaseColumnId}", $nodeClass::$permApp, $nodeClass::$permType ) ), $nodeClass );
			}
			else if ( $nodeClass::$ownerTypes !== NULL and is_subclass_of( $nodeClass, 'IPS\Node\Model' ) )
			{ 
				$select = $nodeClass::loadByOwner( new \IPS\Member );
			}

			foreach ( $select as $node )
			{
				if( $node->url() !== NULL )
				{
					$data = array( 'url' => $node->url() );
					
					$priority = intval( \IPS\Settings::i()->sitemap_databases_priority );
					if ( $priority !== -1 )
					{
						$data['priority'] = $priority;
						$entries[] = $data;
					}
				}
			}
		}
		else
		{
			$exploded = explode( '_', $filename );
			$block = (int) array_pop( $exploded );
			
			$offset = ( $block - 1 ) * \IPS\Sitemap::MAX_PER_FILE;
			$limit = \IPS\Sitemap::MAX_PER_FILE;
			
			$totalLimit = \IPS\Settings::i()->sitemap_databases_count;
			if ( $totalLimit > -1 and ( $offset + $limit ) > $totalLimit )
			{
				$limit = $totalLimit - $offset;
			}
			
			foreach ( $class::getItemsWithPermission( $class::sitemapWhere(), NULL, array( $offset, $limit ), 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY, 0, new \IPS\Member, TRUE ) as $item )
			{
				$data = array( 'url' => $item->url() );				
				$priority = ( $item->sitemapPriority() ?: ( intval( \IPS\Settings::i()->sitemap_databases_priority ) ) );
				if ( $priority !== -1 )
				{
					$data['priority'] = $priority;
					$entries[] = $data;
				}
			}
		}

		$sitemap->buildSitemapFile( $filename, $entries );
	}
}