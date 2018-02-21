<?php

/**
 * @brief		Converter XenForo Media Gallery Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Gallery;

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
		return "XenForo Media Gallery (1.5.x)";
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
			'convertGalleryCategories'	=> array(
				'table'						=> 'xengallery_category',
				'where'						=> NULL
			),
			'convertGalleryAlbums'		=> array(
				'table'						=> 'xengallery_album',
				'where'						=> NULL,
			),
			'convertGalleryImages'		=> array(
				'table'						=> 'xengallery_media',
				'where'						=> NULL,
			),
			'convertGalleryComments'	=> array(
				'table'						=> 'xengallery_comment',
				'where'						=> NULL,
			)
		);
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
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertGalleryAlbums',
			'convertGalleryImages'
		);
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
			case 'convertGalleryAlbums':
				$options = array();
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'gallery_categories' ), 'IPS\gallery\Category' ) AS $category )
				{
					$options[$category->_id] = $category->_title;
				}
				
				$return['convertGalleryAlbums']['members_gallery_category'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Select',
					'field_default'		=> NULL,
					'field_required'	=> FALSE,
					'field_extra'		=> array(
						'options'			=> $options
					),
					'field_hint'		=> NULL,
				);
				break;
			case 'convertGalleryImages':
				$return['convertGalleryImages']['file_location'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_xf_attach_path'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				$return['convertGalleryImages']['media_thumb_location'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_xf_gal_path'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				/* Get our reactions to let the admin map them */
				$options		= array();
				$descriptions	= array();
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_reactions' ), 'IPS\Content\Reaction' ) AS $reaction )
				{
					$options[ $reaction->id ]		= $reaction->_icon->url;
					$descriptions[ $reaction->id ]	= \IPS\Member::loggedIn()->language()->addToStack('reaction_title_' . $reaction->id ) . '<br>' . $reaction->_description;
				}

				$return['convertGalleryImages']['rep_like'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array( 'parse' => 'image', 'options' => $options, 'descriptions' => $descriptions ),
					'field_hint'		=> NULL,
					'field_validation'	=> NULL,
				);
				break;
		}
		
		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
	
	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildGalleryImages', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\gallery\Image' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Album', 'count' => 0 ), 4, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Category', 'count' => 0 ), 5, array( 'class' ) );

		/* Caches */
		\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'gallery_images', 'class' => 'IPS\gallery\Image' ), 3, array( 'app', 'link', 'class' ) );

		return array( "f_gallery_images_rebuild", "f_gallery_cat_recount", "f_gallery_album_recount", "f_gallery_image_recount", "f_image_tags_recount" );
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
	 * Convert gallery categories
	 *
	 * @return	void
	 */
	public function convertGalleryCategories()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'category_id' );
		
		foreach( $this->fetch( 'xengallery_category', 'category_id' ) AS $row )
		{
			$libraryClass->convertGalleryCategory( array(
				'category_id'			=> $row['category_id'],
				'category_name'			=> $row['category_title'],
				'category_desc'			=> $row['category_description'],
				'category_parent_id'	=> $row['parent_category_id'],
				'category_count_imgs'	=> $row['category_media_count'],
				'category_position'		=> $row['display_order']
			) );
			
			/* Follows */
			foreach( $this->db->select( '*', 'xengallery_category_watch', array( "notify_on=? AND category_id=?", 'media', $row['category_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'gallery',
					'follow_area'			=> 'category',
					'follow_rel_id'			=> $row['category_id'],
					'follow_rel_id_type'	=> 'gallery_categories',
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
		}
	}

	/**
	 * Convert gallery albums
	 *
	 * @return	void
	 */
	public function convertGalleryAlbums()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'album_id' );
		
		foreach( $this->fetch( 'xengallery_album', 'album_id' ) AS $row )
		{
			try
			{
				$perm = $this->db->select( '*', 'xengallery_album_permission', array( "album_id=? AND permission=?", $row['album_id'], 'view' ) )->first();
			}
			catch( \UnderflowException $e )
			{
				/* If the permission row is missing for some reason, err on the side of caution and set this album private */
				$perm = array(
					'album_id'		=> $row['album_id'],
					'permission'	=> 'view',
					'access_type'	=> 'private',
					'share_users'	=> \serialize( array() ),
				);
			}
			
			$socialgroup = NULL;
			if ( $perm['access_type'] == 'shared' OR $perm['access_type'] == 'followed' )
			{
				switch( $perm['access_type'] )
				{
					/* Specific members */
					case 'shared':
						$users = \unserialize( $perm['share_users'] );
						$socialgroup['members'] = array();
						
						if ( count( $users ) )
						{
							foreach( $users AS $key => $user )
							{
								$socialgroup['members'][] = $user['shared_user_id'];
							}
						}
						break;
					
					/* Followers */
					case 'followed':
						$socialgroup['members'] = array();
						foreach( $this->db->select( 'follow_user_id', 'xf_user_follow', array( "user_id=?", $row['album_user_id'] ) ) AS $user )
						{
							$socialgroup['members'][] = $user;
						}
						break;
				}
			}
			
			switch( $perm['access_type'] )
			{
				case 'private':
				case 'members': # we don't have an equivalent of "members only" but we don't want to just open everything up either, so set these private.
					$type = 2;
					break;
				
				case 'public':
					$type = 1;
					break;
				
				case 'followed':
				case 'shared':
					$type = 3;
					break;
			}
			
			$info = array(
				'album_id'					=> $row['album_id'],
				'album_owner_id'			=> $row['album_user_id'],
				'album_description'			=> $row['album_description'],
				'album_name'				=> $row['album_title'],
				'album_type'				=> $type,
				'album_count_imgs'			=> $row['album_media_count'],
				'album_count_comments'		=> $row['album_comment_count'],
				'album_rating_aggregate'	=> $row['album_rating_avg'],
				'album_rating_count'		=> $row['album_rating_count'],
				'album_rating_total'		=> $row['album_rating_sum'],
			);
			
			$category = $this->app->_session['more_info']['convertGalleryAlbums']['members_gallery_category'];
			if ( $category == 0 )
			{
				$category = NULL;
			}
			
			$libraryClass->convertGalleryAlbum( $info, $socialgroup, $category );
			
			/* Follows */
			foreach( $this->db->select( '*', 'xengallery_album_watch', array( "notify_on=? AND album_id=?", 'media', $row['album_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'gallery',
					'follow_area'			=> 'album',
					'follow_rel_id'			=> $row['album_id'],
					'follow_rel_id_type'	=> 'gallery_albums',
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
			
			$libraryClass->setLastKeyValue( $row['album_id'] );
		}
	}

	/**
	 * Convert gallery images
	 *
	 * @return	void
	 */
	public function convertGalleryImages()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'media_id' );
		
		foreach( $this->fetch( 'xengallery_media', 'media_id' ) AS $row )
		{
			/* Exif */
			$exif = array();
			foreach( $this->db->select( 'exif_name, exif_value', 'xengallery_exif', array( "media_id=?", $row['media_id'] ) )->setKeyField( 'exif_name' )->setValueField( 'exif_value' ) AS $key => $value )
			{
				$exif[$key] = $value;
			}
			
			/* IP Address */
			try
			{
				$ip = $this->db->select( '*', 'xf_ip', array( "ip_id=?", $row['ip_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$ip = '127.0.0.1';
			}
			
			/* Now the fun part... we need to do different things depending on the type of item this is. */
			switch( $row['media_type'] )
			{
				/* Normal image upload */
				case 'image_upload':
					try
					{
						$file_data = $this->db->select( '*', 'xf_attachment', array( "xf_attachment.content_type=? AND xf_attachment.content_id=?", 'xengallery_media', $row['media_id'] ) )
							->join( 'xf_attachment_data', 'xf_attachment.data_id = xf_attachment_data.data_id' )
							->first();
					}
					catch( \UnderflowException $e )
					{
						/* If the file data is missing, we can't do anything. */
						$libraryClass->setLastKeyValue( $row['media_id'] );
						continue 2;
					}
					
					$group			= floor( $file_data['data_id'] / 1000 );
					$path			= rtrim( $this->app->_session['more_info']['convertGalleryImages']['file_location'], '/' ) . '/' . $group . '/' . $file_data['data_id'] . '-' . $file_data['file_hash'] . '.data';
					$file_name		= $file_data['filename'];
					$description	= $row['media_description'];
					
					break;
				
				/* Video Embed */
				case 'video_embed':
					$path = $this->app->_session['more_info']['convertGalleryImages']['media_thumb_location'];
					
					/* We need to figure out what service we came from, and the video ID */
					preg_match( '/\[media=(.*?)\](.*?)\[\/media\]/i', $row['media_tag'], $matches );
					$service		= $matches[1];
					$video_id		= $matches[2];
					
					$file_name		= $service . '_' . $video_id . '.jpg';
					$path			= rtrim( $path, '/' ) . '/' . $service . '/' . $file_name;
					
					try
					{
						$em = \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::createFromString( $row['media_embed_url'] ) );
					}
					catch( \Exception $e )
					{
						/* If anything went wrong, back out */
						$libraryClass->setlastKeyValue( $row['media_id'] );
						continue 2;
					}
					
					$description	= $em . "<br>" . $row['media_description'];
					
					break;
			}
			
			$info = array(
				'image_id'				=> $row['media_id'],
				'image_album_id'		=> $row['album_id'],
				'image_category_id'		=> $row['category_id'],
				'image_member_id'		=> $row['user_id'],
				'image_caption'			=> $row['media_title'],
				'image_views'			=> $row['media_view_count'],
				'image_comments'		=> $row['comment_count'],
				'image_ratings_total'	=> $row['rating_sum'],
				'image_ratings_count'	=> $row['rating_count'],
				'image_rating'			=> $row['rating_avg'],
				'image_date'			=> $row['media_date'],
				'image_updated'			=> $row['last_edit_date'],
				'image_last_comment'	=> $row['last_comment_date'],
				'image_metadata'		=> json_encode( $exif ),
				'image_ipaddress'		=> $ip,
				'image_file_name'		=> $file_name,
				'image_description'		=> $description
			);
			
			$libraryClass->convertGalleryImage( $info, $path );
			
			/* Warnings */
			foreach( $this->db->select( '*', 'xf_warning', array( "content_type=? AND content_id=?", 'xengallery_media', $row['media_id'] ) ) AS $warn )
			{
				$warnId = $libraryClass->convertWarnLog( array(
					'wl_id'					=> $warn['warning_id'],
					'wl_member'				=> $warn['user_id'],
					'wl_moderator'			=> $warn['warning_user_id'],
					'wl_date'				=> $warn['warning_date'],
					'wl_points'				=> $warn['points'],
					'wl_note_member'		=> $warn['title'],
					'wl_note_mods'			=> $warn['notes'],
				) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warn['warning_id'],
						'log_member'	=> $warn['user_id'],
						'log_by'		=> $warn['warning_user_id'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warn['warning_date']
					)
				);
			}
			
			/* Reputation */
			$likes = \unserialize( $row['like_users'] );
			if ( is_array( $likes ) AND count( $likes ) )
			{
				foreach( $likes AS $like )
				{
					$libraryClass->convertReputation( array(
						'app'				=> 'gallery',
						'type'				=> 'image_id',
						'type_id'			=> $row['media_id'],
						'member_id'			=> $like['user_id'],
						'member_received'	=> $row['user_id'],
						'reaction'			=> $this->app->_session['more_info']['convertGalleryImages']['rep_like'],
						'rep_date'			=> $row['media_date']
					) );
				}
			}
			
			/* Follows */
			foreach( $this->db->select( '*', 'xengallery_media_watch', array( "media_id=?", $row['media_id'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'gallery',
					'follow_area'			=> 'image',
					'follow_rel_id'			=> $row['media_id'],
					'follow_rel_id_type'	=> 'gallery_images',
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

			$tags = NULL;
			/* Tags 1.0.x */
			if( isset( $row['media_content_tag_cache'] ) )
			{
				$tags = \unserialize( $row['media_content_tag_cache'] );
			}
			/* Tags 1.1.x */
			elseif( isset( $row['tags'] ) )
			{
				$tags = \unserialize( $row['tags'] );
			}

			if ( is_array( $tags ) and count( $tags ) )
			{
				foreach( $tags AS $k => $tag )
				{
					$libraryClass->convertTag( array(
						'tag_meta_app'			=> 'gallery',
						'tag_meta_area'			=> 'gallery',
						'tag_meta_parent_id'	=> ( $row['album_id'] ) ? $row['album_id'] : $row['category_id'],
						'tag_meta_id'			=> $row['media_id'],
						'tag_text'				=> isset( $tag['tag_clean'] ) ? $tag['tag_clean'] : $tag['tag'], // Select 1.0 or 1.1 version
						'tag_prefix'			=> 0,
						'tag_member_id'			=> $row['user_id']
					) );
				}
			}
			
			$libraryClass->setLastKeyValue( $row['media_id'] );
		}
	}

	/**
	 * Convert gallery comments
	 *
	 * @return	void
	 */
	public function convertGalleryComments()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'comment_id' );
		
		foreach( $this->fetch( 'xengallery_comment', 'comment_id', array( "content_type=?", 'media' ) ) AS $row )
		{
			/* IP Address */
			try
			{
				$ip = $this->db->select( 'ip', 'xf_ip', array( "ip_id=?", $row['ip_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$ip = '127.0.0.1';
			}
			
			/* Approved */
			switch( $row['comment_state'] )
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
			
			$libraryClass->convertGalleryComment( array(
				'comment_id'			=> $row['comment_id'],
				'comment_text'			=> $row['message'],
				'comment_img_id'		=> $row['content_id'],
				'comment_author_id'		=> $row['user_id'],
				'comment_author_name'	=> $row['username'],
				'comment_ip_address'	=> $ip,
				'comment_post_date'		=> $row['comment_date'],
				'comment_approved'		=> $approved,
			) );
			
			/* Reputation */
			$likes = \unserialize( $row['like_users'] );
			if ( is_array( $likes ) AND count( $likes ) )
			{
				foreach( $likes AS $like )
				{
					$libraryClass->convertReputation( array(
						'app'				=> 'gallery',
						'type'				=> 'comment_id',
						'type_id'			=> $row['comment_id'],
						'member_id'			=> $like['user_id'],
						'member_received'	=> $row['user_id'],
						'reaction'			=> $this->app->_session['more_info']['convertGalleryImages']['rep_like'],
						'rep_date'			=> $row['comment_date']
					) );
				}
			}
			
			/* Warnings */
			foreach( $this->db->select( '*', 'xf_warning', array( "content_type=? AND content_id=?", 'xengallery_comment', $row['comment_id'] ) ) AS $warn )
			{
				$warnId = $libraryClass->convertWarnLog( array(
					'wl_id'					=> $warn['warning_id'],
					'wl_member'				=> $warn['user_id'],
					'wl_moderator'			=> $warn['warning_user_id'],
					'wl_date'				=> $warn['warning_date'],
					'wl_points'				=> $warn['points'],
					'wl_note_member'		=> $warn['title'],
					'wl_note_mods'			=> $warn['notes'],
				) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warn['warning_id'],
						'log_member'	=> $warn['user_id'],
						'log_by'		=> $warn['warning_user_id'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warn['warning_date']
					)
				);
			}
			
			$libraryClass->setLastKeyValue( $row['comment_id'] );
		}
	}
}