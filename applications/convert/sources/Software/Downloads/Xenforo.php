<?php

/**
 * @brief		Converter XenForo Resource Manager Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Xenforo extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "XenForo Resource Manager (1.5.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "xenforo";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertDownloadsCfields' 		=> array(
				'table'							=> 'xf_resource_field',
				'where'							=> NULL
			),
			'convertDownloadsCategories'	=> array(
				'table'							=> 'xf_resource_category',
				'where'							=> NULL
			),
			'convertDownloadsFiles'			=> array(
				'table'							=> 'xf_resource',
				'where'							=> array( "is_fileless=?", 0 )
			),
			'convertDownloadsReviews'		=> array(
				'table'							=> 'xf_resource_rating',
				'where'							=> NULL
			)
		);
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
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertDownloadsFiles',
			'convertDownloadsReviews'
		);
	}

	/**
	 * Finish
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'class' => 'IPS\downloads\File', 'link' => 'downloads_files' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'class' => 'IPS\downloads\File\Review', 'link' => 'downloads_reviews'  ), 2, array( 'app', 'link', 'class' ) );

		return array( );
	}
	
	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		switch( $method )
		{
			case 'convertDownloadsFiles':
				$return['convertDownloadsFiles']['file_location'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_xf_attach_path'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				break;
			
			case 'convertDownloadsReviews':
				$return['convertDownloadsReviews']['convert_author_replies_to_comments'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\YesNo',
					'field_default'		=> FALSE,
					'field_required'	=> FALSE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL,
				);
		}
		
		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
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
	 * Convert custom fields
	 *
	 * @return	void
	 */
	public function convertDownloadsCfields()
	{
		$libraryClass = $this->getLibrary();
		
		foreach( $this->fetch( 'xf_resource_field', 'field_id' ) AS $row )
		{
			$info = array(
				'cf_id'				=> $row['field_id'],
				'cf_type'			=> $this->_mapFieldType( $row['field_type'], $row['match_type'] ),
				'cf_content'		=> \unserialize( $row['field_choices'] ),
				'cf_not_null'		=> $row['required'],
				'cf_max_input'		=> $row['max_length'],
				'cf_input_format'	=> ( $row['match_type'] == 'regex' ) ? '/' . $row['match_regex'] .'/i' : '',
				'cf_position'		=> $row['display_order'],
				'cf_multiple'		=> ( in_array( $row['field_type'], array( 'checkbox', 'multiselect' ) ) ) ? 1 : 0
			);
			
			$libraryClass->convertDownloadsCfield( $info );
		}
	}

	/**
	 * Map the field types
	 *
	 * @return	void
	 */
	protected function _mapFieldType( $type, $match )
	{
		switch( $type )
		{
			case 'textbox':
				/* Yay, nested switches */
				switch( $match )
				{
					case 'email':
						return 'Email';
						break;
					
					case 'url':
						return 'Url';
						break;
					
					case 'number':
						return 'Number';
						break;
					
					default:
						return 'Text';
						break;
				}
				break;
			
			case 'textarea':
				return 'TextArea';
				break;
			
			case 'bbcode':
				return 'Editor';
				break;
			
			case 'multiselect':
			case 'select':
				return 'Select';
				break;
			
			case 'checkbox':
				return 'CheckboxSet';
				break;
		}
	}
	
	/**
	 * Convert categories
	 *
	 * @return	void
	 */
	public function convertDownloadsCategories()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'resource_category_id' );
		
		foreach( $this->fetch( 'xf_resource_category', 'resource_category_id' ) AS $row )
		{
			/* Custom Fields for this category */
			$ccfields = array();
			foreach( $this->db->select( 'field_id', 'xf_resource_field_category', array( "resource_category_id=?", $row['resource_category_id'] ) ) AS $field )
			{
				$ccfields[] = $field;
			}
			
			/* Topic Prefix */
			$prefix = '';
			if ( $row['thread_prefix_id'] AND $row['thread_node_id'] )
			{
				$prefix = $this->getPhrase( "thread_prefix_{$row['thread_prefix_id']}" );
				
				if ( is_null( $prefix ) )
				{
					$prefix = '';
				}
			}
			
			$info = array(
				'cid'			=> $row['resource_category_id'],
				'cparent'		=> $row['parent_category_id'],
				'cname'			=> $row['category_title'],
				'cdesc'			=> $row['category_description'],
				'copen'			=> ( $row['allow_local'] OR $row['allow_external'] ) ? 1 : 0,
				'cposition'		=> $row['display_order'],
				'ccfields'		=> $ccfields,
				'cforum_id'		=> $row['thread_node_id'],
				'ctopic_prefix'	=> $prefix
			);
			
			$libraryClass->convertDownloadsCategory( $info );
			
			/* Follows */
			foreach( $this->db->select( '*', 'xf_resource_category_watch', array( "notify_on=? AND resource_category_id=?", 'resource', $row['resource_category_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'downloads',
					'follow_area'			=> 'category',
					'follow_rel_id'			=> $row['resource_category_id'],
					'follow_rel_id_type'	=> 'downloads_categories',
					'follow_member_id'		=> $follow['user_id'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> ( $follow['send_alert'] OR $follow['send_email'] ) ? 'immediate' : 'none',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
					'follow_index_id'		=> NULL
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['resource_category_id'] );
		}
	}
	
	/**
	 * Convert files
	 *
	 * @return	void
	 */
	public function convertDownloadsFiles()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'resource_id' );
		
		foreach( $this->fetch( 'xf_resource', 'resource_id', array( "is_fileless=?", 0 ) ) AS $row )
		{
			/* Latest Version Stuff */
			$latest				= $this->db->select( '*', 'xf_resource_update', array( "xf_resource_update.resource_id=?", $row['resource_id'] ), "xf_resource_update.post_date DESC" )
									->join( 'xf_resource_version', "xf_resource_update.resource_update_id = xf_resource_version.resource_update_id" )
									->first();
			
			$latest_version		= $latest['version_string'];
			$latest_changelog	= $latest['message'];
			
			/* Featured */
			$featured = 0;
			try
			{
				$this->db->select( '*', 'xf_resource_feature', array( "resource_id=?", $row['resource_id'] ) )->first();
				
				$featured = 1;
			}
			catch( \UnderflowException $e ) {}
			
			$info = array(
				'file_id'			=> $row['resource_id'],
				'file_desc'			=> $row['tag_line'],
				'file_name'			=> $row['title'],
				'file_cat'			=> $row['resource_category_id'],
				'file_downloads'	=> $this->db->select( 'COUNT(*)', 'xf_resource_download', array( 'resource_id=?', $row['resource_id'] ) )->first(),
				'file_reviews'		=> $row['review_count'],
				'file_submitted'	=> $row['resource_date'],
				'file_updated'		=> $row['last_update'],
				'file_submitter'	=> $row['user_id'],
				'file_topicid'		=> $row['discussion_thread_id'],
				'file_version'		=> $latest_version,
				'file_changelog'	=> $latest_changelog,
				'file_featured'		=> $featured,
			);
			
			$records	= array();
			$recordit = $this->db->select( '*', 'xf_resource_update', array( "xf_resource_update.resource_id=?", $row['resource_id'] ) )->join( 'xf_resource_version', "xf_resource_update.resource_update_id = xf_resource_version.resource_update_id" );
			foreach( $recordit AS $record )
			{
				if ( $record['download_url'] )
				{
					$records[] = array(
						'record_id'			=> $record['resource_update_id'],
						'record_type'		=> 'link',
						'file_path'			=> $record['download_url'],
						'record_time'		=> $record['release_date']
					);
				}
				else
				{
					try
					{
						$file_data = $this->db->select( '*', 'xf_attachment', array( "xf_attachment.content_type=? AND xf_attachment.content_id=?", 'resource_version', $record['resource_version_id'] ) )->join( 'xf_attachment_data', "xf_attachment.data_id = xf_attachment_data.data_id" )->first();
					}
					catch( \UnderflowException $e )
					{
						$this->app->log( 'xenforo_resource_missing_record', __METHOD__, \IPS\convert\App::LOG_WARNING, $record['resource_update_id'] );
						continue;
					}

					$group = floor( $file_data['data_id'] / 1000 );

					$records[] = array(
						'record_id'			=> $record['resource_update_id'],
						'record_type'		=> 'upload',
						'file_path'			=> rtrim( $this->app->_session['more_info']['convertDownloadsFiles']['file_location'], '/' ) . '/' . $group . '/' . $file_data['data_id'] . '-' . $file_data['file_hash'] . '.data',
						'record_time'		=> $record['release_date'],
						'record_realname' 	=> $file_data['filename']
					);
				}

				/* And now screenshots */
				try
				{
					foreach( $this->db->select( '*', 'xf_attachment', array( "xf_attachment.content_type=? AND xf_attachment.content_id=?", 'resource_update', $record['resource_update_id'] ) )->join( 'xf_attachment_data', "xf_attachment.data_id = xf_attachment_data.data_id" ) AS $screen )
					{
						$group = floor( $file_data['data_id'] / 1000 );

						$records[] = array(
							'record_type'		=> 'ssupload',
							'file_path'			=> rtrim( $this->app->_session['more_info']['convertDownloadsFiles']['file_location'], '/' ) . '/' . $group . '/' . $screen['data_id'] . '-' . $screen['file_hash'] . '.data',
							'record_time'		=> $record['release_date'],
							'record_realname' 	=> $screen['filename']
						);
					}
				}
				catch( \UnderflowException $e )
				{
					$this->app->log( 'xenforo_resource_missing_screenshot', __METHOD__, \IPS\convert\App::LOG_WARNING, $record['resource_update_id'] );
				}
			}

			$cfields	= array();
			foreach( $this->db->select( 'field_id, field_value', 'xf_resource_field_value', array( "resource_id=?", $row['resource_id'] ) )->setKeyField( 'field_id' )->setValueField( 'field_value' ) AS $key => $value )
			{
				$field_type = $this->db->select( 'field_type', 'xf_resource_field', array( "field_id=?", $key ) )->first();
				
				if ( in_array( $field_type, array( 'checkbox', 'multiselect' ) ) )
				{
					$cfields[$key] = json_encode( \unserialize( $value ) );
				}
				else
				{
					$cfields[$key] = $value;
				}
			}
			
			$libraryClass->convertDownloadsFile( $info, $records, $cfields );
			
			/* Follows */
			foreach( $this->db->select( '*', 'xf_resource_watch', array( "resource_id=?", $row['resource_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'downloads',
					'follow_area'			=> 'file',
					'follow_rel_id'			=> $row['resource_id'],
					'follow_rel_id_type'	=> 'downloads_files',
					'follow_member_id'		=> $follow['user_id'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> ( $follow['email_subscribe'] ) ? 'immediate' : 'none',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
					'follow_index_id'		=> NULL
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['resource_id'] );
		}
	}
	
	/**
	 * Convert reviews
	 *
	 * @return	void
	 */
	public function convertDownloadsReviews()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'resource_rating_id' );
		
		foreach( $this->fetch( 'xf_resource_rating', 'resource_rating_id' ) AS $row )
		{
			switch( $row['rating_state'] )
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
				'review_id'			=> $row['resource_rating_id'],
				'review_text'		=> $row['message'],
				'review_rating'		=> $row['rating'],
				'review_mid'		=> $row['user_id'],
				'review_fid'		=> $row['resource_id'],
				'review_date'		=> $row['rating_date'],
				'review_approved'	=> $approved,
				'review_version'	=> $row['version_string']
			);
			
			$libraryClass->convertDownloadsReview( $info );
			
			if ( $this->app->_session['more_info']['convertDownloadsReviews']['convert_author_replies_to_comments'] AND $row['author_response'] )
			{
				/* Get Resource */
				$resource	= $this->db->select( '*', 'xf_resource', array( "resource_id=?", $row['resource_id'] ) )->first();
				$member		= $this->db->select( '*', 'xf_user', array( "user_id=?", $row['user_id'] ) )->first();
				$post		= "<blockquote class=\"ipsQuote\" data-ipsQuote-username=\"{$member['username']}\">{$row['message']}</blockquote><p>{$row['author_response']}</p>";
				
				$libraryClass->convertDownloadsComment( array(
					'comment_id'		=> $row['resource_rating_id'],
					'comment_fid'		=> $row['resource_id'],
					'comment_text'		=> $post,
					'comment_mid'		=> $resource['user_id'],
					'comment_date'		=> $row['rating_date'],
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['resource_rating_id'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		if( preg_match( '#/(resources/categories|resources)/(.+?)\.([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			$oldId	= (int) $matches[3];

			switch( $matches[1] )
			{
				case 'resources/categories':
					$class	= '\IPS\downloads\Category';
					$types	= array( 'downloads_categories' );
				break;

				case 'resources':
					$class	= '\IPS\downloads\File';
					$types	= array( 'downloads_files' );
				break;
			}
		}

		if( isset( $class ) )
		{
			try
			{
				try
				{
					$data = (string) $this->app->getLink( $oldId, $types );
				}
				catch( \OutOfRangeException $e )
				{
					$data = (string) $this->app->getLink( $oldId, $types, FALSE, TRUE );
				}
				$item = $class::load( $data );

				if( $item instanceof \IPS\Content )
				{
					if( $item->canView() )
					{
						return $item->url();
					}
				}
				elseif( $item instanceof \IPS\Node\Model )
				{
					if( $item->can( 'view' ) )
					{
						return $item->url();
					}
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}