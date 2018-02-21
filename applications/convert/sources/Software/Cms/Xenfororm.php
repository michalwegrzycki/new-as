<?php

/**
 * @brief		Converter XenForoRm Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Xenfororm extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "XenForo Resource Manager Articles (1.5.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "xenfororm";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertCmsDatabases'			=> array(
				'table'							=> 'cms_databases',
				'where'							=> NULL,
			),
			'convertCmsDatabaseCategories'	=> array(
				'table'							=> 'xf_resource_category',
				'where'							=> array( "allow_fileless=?", 1 )
			),
			'convertCmsDatabaseRecords'		=> array(
				'table'							=> 'xf_resource',
				'where'							=> array( "is_fileless=?", 1 )
			)
		);
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool		$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch( $table )
		{
			case 'cms_databases':
				return 1;
				break;
				
			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}
	
	/**
	 * Uses Prefix
	 *
	 * @return	bool
	 */
	public static function usesPrefix()
	{
		return FALSE;
	}

	/**
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}
	
	/**
	 * Possible Parent Conversions
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'xenforo' ) );
	}

	/**
	 * Helper to fetch a xenforo phrase
	 *
	 * @param	string	$title	Phrase
	 * @return	string|null
	 */
	protected function getPhrase( $title )
	{
		try
		{
			return $this->db->select( 'phrase_text', 'xf_phrase', array( "title=?", $title ) )->first();
		}
		catch( \UnderflowException $e )
		{
			return NULL;
		}
	}

	/**
	 * Convert CMS databases
	 *
	 * @return	void
	 */
	public function convertCmsDatabases()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass->convertCmsDatabase( array(
			'database_id'			=> 1,
			'database_name'			=> "Resources",
			'database_sln'			=> 'resource',
			'database_pln'			=> 'resources',
			'database_scn'			=> 'Resource',
			'database_pcn'			=> 'Resources',
			'database_ia'			=> 'a resource',
		), array(
			array(
				'field_id'				=> 1,
				'field_name'			=> 'Title',
				'field_type'			=> 'Text',
				'field_key'				=> 'resource_title',
				'field_required'		=> 1,
				'field_position'		=> 1,
				'field_display_listing'	=> 1,
				'field_is_title'		=> 1,
			),
			array(
				'field_id'				=> 2,
				'field_name'			=> 'Tag Line',
				'field_type'			=> 'Text',
				'field_key'				=> 'resource_tagline',
				'field_required'		=> 1,
				'field_position'		=> 2,
			),
			array(
				'field_id'				=> 3,
				'field_name'			=> 'Content',
				'field_type'			=> 'Editor',
				'field_key'				=> 'resource_content',
				'field_required'		=> 1,
				'field_position'		=> 3,
				'field_is_content'		=> 1,
			)
		) );
		
		/* Throw an exception here to tell the library that we're done with this step */
		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Convert CMS database categories
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseCategories()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'resource_category_id' );
		
		foreach( $this->fetch( 'xf_resource_category', 'resource_category_id', array( 'allow_fileless=?', 1 ) ) AS $row )
		{
			$forumoverride	= 0;
			$forumid		= 0;
			if ( $row['thread_node_id'] )
			{
				$forumoverride	= 1;
				$forumid		= $row['thread_node_id'];
			}
			
			$info = array(
				'category_id'			=> $row['resource_category_id'],
				'category_database_id'	=> 1,
				'category_name'			=> $row['category_title'],
				'category_position'		=> $row['display_order'],
				'category_fields'		=> array( 'resource_title', 'resource_tagline', 'resource_content' ),
			);
			
			$libraryClass->convertCmsDatabaseCategory( $info );
			
			$libraryClass->setLastKeyValue( $row['resource_category_id'] );
		}
	}

	/**
	 * Convert CMS database records
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseRecords()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'resource_id' );
		
		foreach( $this->fetch( 'xf_resource', 'resource_id', array( 'is_fileless=?', 1 ) ) AS $row )
		{
			$post = $this->db->select( 'message, post_date', 'xf_resource_update', array( "resource_id=?", $row['resource_id'] ), "post_date DESC" )->first();
			
			switch( $row['resource_state'] )
			{
				case 'visible':
					$approved = 1;
					break;
				
				case 'moderated':
					$approved = 0;
					break;
				
				case 'deleted':
					$approved = -1;
					break;
			}
			
			$info = array(
				'record_id'				=> $row['resource_id'],
				'record_database_id'	=> 1,
				'member_id'				=> $row['user_id'],
				'record_saved'			=> $row['resource_date'],
				'record_updated'		=> $post['post_date'],
				'category_id'			=> $row['resource_category_id'],
				'record_approved'		=> $approved,
				'record_topicid'		=> $row['discussion_thread_id'],
				'record_publish_date'	=> $row['resource_date'],
			);
			
			$fields = array( 1 => $row['title'], 2 => $row['tag_line'], 3 => $post['message'] );
			
			$libraryClass->convertCmsDatabaseRecord( $info, $fields );
			
			$libraryClass->setLastKeyValue( $row['resource_id'] );
		}
	}

	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		$database = $this->app->getLink( 1, 'cms_databases' );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\cms\Categories' . $database, 'count' => 0 ), 5, array( 'class' ) );
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'cms_custom_database_' . $database, 'class' => 'IPS\cms\Records' . $database ), 2, array( 'app', 'link', 'class' ) );

		return array( "f_recount_cms_categories", "f_rebuild_cms_tags" );
	}
}