<?php

/**
 * @brief		Converter XenForo 1.x Master Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Core;

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
		return "XenForo (1.5.x)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		return 'xenforo';
	}

	/**
	 * Returns a block of text, or a language string, that explains what the admin must do to start this conversion
	 *
	 * @return	string
	 */
	public static function getPreConversionInformation()
	{
		return 'convert_xf_preconvert';
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
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertEmoticons'				=> array(
				'table'		=> 'xf_smilie',
				'where'		=> NULL
			),
			'convertProfileFields'		=> array(
				'table'		=> 'xf_user_field',
				'where'		=> NULL
			),
			'convertGroups'				=> array(
				'table'		=> 'xf_user_group',
				'where'		=> NULL
			),
			'convertMembers'				=> array(
				'table'		=> 'xf_user',
				'where'		=> NULL
			),
			'convertMemberHistory'			=> array(
				'table'		=> 'xf_user_change_log',
				'where'		=> NULL
			),
			'convertWarnReasons'			=> array(
				'table'		=> 'xf_warning_definition',
				'where'		=> NULL
			),
			'convertStatuses'				=> array(
				'table'		=> 'xf_profile_post',
				'where'		=> NULL
			),
			'convertStatusReplies'		=> array(
				'table'		=> 'xf_profile_post_comment',
				'where'		=> NULL
			),
			'convertIgnoredUsers'			=> array(
				'table'		=> 'xf_user_ignored',
				'where'		=> NULL
			),
			'convertPrivateMessages'		=> array(
				'table'		=> 'xf_conversation_master',
				'where'		=> NULL
			),
			'convertPrivateMessageReplies'	=> array(
				'table'		=> 'xf_conversation_message',
				'where'		=> NULL
			),
			'convertAttachments'			=> array(
				'table'		=> 'xf_attachment',
				'where'		=> array( "content_type=?", 'conversation_message' )
			),
			'convertRanks'					=> array(
				'table'		=> 'xf_user_title_ladder',
				'where'		=> NULL
			)
		);
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool			$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch( $table )
		{
			case 'xf_user_change_log':
				return $this->db->select( 'count(log_id)', 'xf_user_change_log', array( $this->db->in( 'field', array_keys( static::$changeLogTypes ) ) ) )->first();
				break;

			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}

	/**
	 * Can we convert passwords from this software.
	 *
	 * @return 	boolean
	 */
	public static function loginEnabled()
	{
		return TRUE;
	}

	/**
	 * Can we convert settings?
	 *
	 * @return	boolean
	 */
	public static function canConvertSettings()
	{
		return TRUE;
	}

	/**
	 * List of Conversion Methods that require more information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments',
			'convertEmoticons',
			'convertProfileFields',
			'convertGroups',
			'convertMembers',
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

		switch ( $method )
		{
			case 'convertAttachments':
				$return['convertAttachments'] = array(
					'attach_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Text',
						'field_default'			=> NULL,
						'field_required'		=> TRUE,
						'field_extra'			=> array(),
						'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_xf_attach_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
				);
				break;

			case 'convertEmoticons':
				/* XenForo stores emoticons either as a remotely linked image, or relative to the installation path, so we need to change the verbiage here a bit. */
				\IPS\Member::loggedIn()->language()->words['emoticon_path'] = \IPS\Member::loggedIn()->language()->addToStack( 'source_path', FALSE, array( 'sprintf' => array( 'XenForo' ) ) );
				$return['convertEmoticons'] = array(
					'emoticon_path'				=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> NULL,
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					),
					'keep_existing_emoticons'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
						'field_default'		=> TRUE,
						'field_required'	=> FALSE,
						'field_extra'		=> array(),
						'field_hint'		=> NULL,
					)
				);
				break;
			
			case 'convertProfileFields':
				$return['convertProfileFields'] = array();
				
				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_data' ), 'IPS\core\ProfileFields\Field' ) AS $field )
				{
					$options[$field->_id] = $field->_title;
				}
				
				foreach( $this->db->select( '*', 'xf_user_field' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['field_id']}"]			= $this->getPhrase( "user_field_{$field['field_id']}" );
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['field_id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );
					
					$return['convertProfileFields']["map_pfield_{$field['field_id']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;
			
			case 'convertGroups':
				$return['convertGroups'] = array();

				$options = array();
				$options['none'] = 'None';
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[$group->g_id] = $group->name;
				}

				foreach( $this->db->select( '*', 'xf_user_group' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['user_group_id']}"]			= $group['title'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['user_group_id']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );

					$return['convertGroups']["map_group_{$group['user_group_id']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;
			case 'convertMembers':
				$return['convertMembers'] = array();

				/* Find out where the photos live */
				\IPS\Member::loggedIn()->language()->words['photo_location_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'photo_location_nodb_desc' );
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_xf_avatar_path'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				foreach( array( 'homepage', 'location', 'occupation', 'about', 'gender' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, array( 'sprintf' => $field ) );
					\IPS\Member::loggedIn()->language()->words["field_{$field}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field_desc' );
					$return['convertMembers']["field_{$field}"] = array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'no_convert',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'no_convert'			=> \IPS\Member::loggedIn()->language()->addToStack( 'no_convert' ),
								'create_field'			=> \IPS\Member::loggedIn()->language()->addToStack( 'create_field' ),
							),
							'userSuppliedInput'		=> 'create_field'
						),
						'field_hint'			=> NULL
					);
				}
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}

	/**
	 * Settings Map
	 *
	 * @return	array
	 */
	public function settingsMap()
	{
		return array(
			'boardTitle'	=> 'board_name',
		);
	}

	/**
	 * Settings Map Listing
	 *
	 * @return	array
	 */
	public function settingsMapList()
	{
		$settings = array();
		foreach( $this->settingsMap() AS $theirs => $ours )
		{
			try
			{
				$setting = $this->db->select( 'option_value', 'xf_option', array( "option_id=?", $theirs ) )->first();
			}
			catch( \UnderflowException $e )
			{
				continue;
			}

			$title = $this->getPhrase( 'option_' . $setting );

			if ( !$title )
			{
				$title = $setting;
			}

			$settings[ $theirs ] = array( 'title' => $title, 'value' => $setting, 'our_key' => $ours, 'our_title' => \IPS\Member::loggedIn()->language()->addToStack( $ours ) );
		}

		return $settings;
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
	 * Finish
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Search Index Rebuild */
		\IPS\Content\Search\Index::i()->rebuild();
		
		/* Clear Cache and Store */
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();
		
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );
		
		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\core\Messenger\Message' ), 3, array( 'class' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 1, array( 'app' ) );

		/* Rebuild Leaderboard */
		\IPS\Task::queue( 'core', 'RebuildReputationLeaderboard', array(), 4 );
		\IPS\Db::i()->delete('core_reputation_leaderboard_history');
		
		return array( "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild" );
	}
	
	/**
	 * Fix post data
	 *
	 * @param 	string		raw post data
	 * @return 	string		parsed post data
	 */
	public static function fixPostData( $post )
	{
		// run everything through htmlspecialchars to prevent XSS
		$post = htmlspecialchars( $post, \IPS\HTMLENTITIES | ENT_QUOTES, 'UTF-8', FALSE );
		
		// find YouTube ID's and replace.
		$post = preg_replace( '#\[media=youtube\](.+?)\[/media\]#i', '[media]http://www.youtube.com/watch?v=$1[/media]', $post );
		
		/* Mentions */
		preg_match_all( '#\[user=(\d+)\](.+?)\[\/user\]#i', $post, $matches );
		
		if ( count( $matches ) )
		{
			if ( isset( $matches[1] ) )
			{
				$mentions = array();
				foreach( $matches[1] AS $k => $v )
				{
					if ( isset( $matches[2][ $k ] ) )
					{
						$name = trim( $matches[2][ $k ], '@' );
						$mentions[$name] = $v;
					}
				}
				
				$maps		= array();
				$urls		= array();
				$cardUrls	= array();
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'name', array_keys( $mentions ) ) ) ), 'IPS\Member' ) AS $member )
				{
					$maps[ $member->name ]		= $member->member_id;
					$urls[ $member->name ]		= $member->url();
					$cardUrls[ $member->name ]	= $member->url()->setQueryString( 'do', 'hovercard' );
				}
				
				foreach( $mentions AS $member_name => $member_id )
				{
					$maps[ $member_name ]	= preg_quote( $maps[ $member_name ], '#' );
					$memberNameEscaped		= preg_quote( $member_name, '#' );
					
					$post = preg_replace( "#\[user={$member_id}\]@?{$memberNameEscaped}\[\/user\]#i", "<a contenteditable=\"false\" rel=\"\" href=\"{$urls[ $member_name ]}\" data-mentionid=\"{$maps[ $member_name ]}\" data-ipshover-target=\"{$cardUrls[ $member_name ]}\" data-ipshover=\"\">@{$member_name}</a>", $post );
				}
			}
		}

		/* Quotes */
		$post = preg_replace( '#\[quote=&quot;([a-zA-Z0-9]+)(,(.?)+)?&quot;]#i', "[quote name='$1']", $post );
		
		// finally, give us the post back.
		return $post;
	}

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'xf_attachment.attachment_id' );

		$it = $this->fetch( 'xf_attachment', 'xf_attachment.attachment_id', array( "content_type=?", 'conversation_message' ) )->join( 'xf_attachment_data', 'xf_attachment.data_id = xf_attachment_data.data_id' );

		foreach( $it AS $row )
		{
			$conversation = $this->db->select( 'conversation_id', 'xf_conversation_message', array( "message_id=?", $row['content_id'] ) )->first();

			$map = array(
				'id1'				=> $conversation,
				'id2'				=> $row['content_id'],
				'id1_type'			=> 'core_message_topics',
				'id1_from_parent'	=> FALSE,
				'id2_type'			=> 'core_message_posts',
				'id2_from_parent'	=> FALSE,
				'location_key'		=> 'core_Messaging'
			);

			$ext = explode( '.', $row['filename'] );
			$ext = array_pop( $ext );

			$info = array(
				'attach_id'			=> $row['attachment_id'],
				'attach_file'		=> $row['filename'],
				'attach_date'		=> $row['upload_date'],
				'attach_member_id'	=> $row['user_id'],
				'attach_hits'		=> $row['view_count'],
				'attach_ext'		=> $ext,
				'attach_filesize'	=> $row['file_size'],
			);

			$physicalName	= $row['data_id'] . '-' . $row['file_hash'] . '.data';
			$group			= floor( $row['data_id'] / 1000 );
			$path			= rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $group . '/' . $physicalName;

			$attachId = $libraryClass->convertAttachment( $info, $map, $path );

			/* Update Message Post if we can */
			try
			{
				if ( $attachId !== FALSE )
				{
					$messagePostId = $this->app->getLink( $row['content_id'], 'core_message_posts' );

					$post = \IPS\Db::i()->select( 'msg_post', 'core_message_posts', array( "msg_id=?", $messagePostId ) )->first();

					if ( preg_match( "/\[ATTACH(.+?)?\]" . $row['attachment_id'] . "\[\/ATTACH\]/i", $post ) )
					{
						$post = preg_replace( "/\[ATTACH(.+?)?\]" . $row['attachment_id'] . "\[\/ATTACH\]/i", '[attachment=' . $attachId . ':name]', $post );
					}

					\IPS\Db::i()->update( 'core_message_posts', array( 'msg_post' => $post ), array( "msg_id=?", $messagePostId ) );
				}
			}
			catch( \UnderflowException $e ) {}
			catch( \OutOfRangeException $e ) {}

			$libraryClass->setLastKeyValue( $row['attachment_id'] );
		}
	}
	
	/**
	 * Convert emoticons
	 *
	 * @return	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'smilie_id' );
		
		foreach( $this->fetch( 'xf_smilie', 'smilie_id' ) AS $row )
		{
			/* We need to figure out where our file lives - if it's remote, then we need to use file_get_contents() and pass the raw data. */
			$filepath = NULL;
			$filedata = NULL;
			
			if ( mb_substr( $row['image_url'], 0, 4 ) == 'http' )
			{
				$filedata	= file_get_contents( $row['image_url'] );
				$fileurl	= explode( '/', $row['image_url'] );
				$filename	= array_pop( $fileurl );
			}
			else
			{
				$fileurl	= explode( '/', $row['image_url'] );
				$filename	= array_pop( $fileurl );
				$filepath	= rtrim( $this->app->_session['more_info']['convertEmoticons']['emoticon_path'], '/' ) . '/' . implode( '/', $fileurl );
			}
			
			/* XenForo allows multiple codes - we don't. */
			$code = explode( "\n", $row['smilie_text'] );
			$code = array_shift( $code );
			
			/* And our set */
			try
			{
				$category	= $this->db->select( '*', 'xf_smilie_category', array( "smilie_category_id=?", $row['smilie_category_id'] ) )->first();
				$title		= $this->getPhrase( "smilie_category_{$category['smilie_category_id']}_title" );
				
				if ( is_null( $title ) )
				{
					/* Bubble Up */
					throw new \UnderflowException;
				}
			}
			catch( \UnderflowException $e )
			{
				$category = array(
					'display_order'	=> 1,
				);
				
				$title = "Converted";
			}
			
			$set = array(
				'set'		=> md5( $title ),
				'title'		=> $title,
				'position'	=> $category['display_order']
			);
			
			$info = array(
				'id'			=> $row['smilie_id'],
				'typed'			=> $code,
				'filename'		=> $filename,
				'emo_position'	=> $row['display_order'],
			);
			
			$libraryClass->convertEmoticon( $info, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], $filepath, $filedata );
			
			$libraryClass->setLastKeyValue( $row['smilie_id'] );
		}
	}
	
	/**
	 * Convert profile fields
	 *
	 * @return	void
	 */
	public function convertProfileFields()
	{
		$libraryClass = $this->getLibrary();
		
		foreach( $this->fetch( 'xf_user_field', 'field_id' ) AS $row )
		{
			$info						= array();
			$info['pf_id']				= $row['field_id'];
			$merge						= $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['field_id']}"] != 'none' ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['field_id']}"] : NULL;
			$info['pf_type']			= $this->_fieldMap( $row['field_type'] );
			$info['pf_name']			= $this->getPhrase( "user_field_{$row['field_id']}" );
			$info['pf_desc']			= $this->getPhrase( "user_field_{$row['field_id']}_desc" );
			$info['pf_content'] 		= ( !in_array( $row['field_type'], array( 'textbox', 'textarea' ) ) ) ? \unserialize( $row['field_choices'] ) : NULL;
			$info['pf_not_null']		= $row['required'];
			$info['pf_member_hide']		= ( $row['viewable_profile'] > 0 ) ? 0 : 1;
			$info['pf_max_input']		= $row['max_length'];
			$info['pf_member_edit'] 	= ( in_array( $row['user_editable'], array( 'yes', 'once' ) ) ) ? 0 : 1;
			$info['pf_position']		= $row['display_order'];
			$info['pf_show_on_reg']		= ( $row['show_registration'] > 0 ) ? 1 : 0;
			$info['pf_input_format']	= ( $row['match_type'] == 'regex' AND $row['match_regex'] ) ? '/' . $row['match_regex'] . '/i' : NULL;
			$info['pf_multiple']		= ( $row['field_type'] == 'multiselect' ) ? 1 : 0;
			
			$libraryClass->convertProfileField( $info, $merge );
		}
	}
	
	/**
	 * Maps the XenForo field ttype to IPS field type
	 *
	 * @param	string	XF Field Type
	 * @return	string	IPS Field Type
	 */
	protected function _fieldMap( $type )
	{
		switch( $type )
		{
			case 'select':
			case 'radio':
			case 'checkbox':
				return ucwords( $type );
				break;

			case 'textarea':
				return 'TextArea';
				break;
			
			case 'multiselect':
				return 'Select';
				break;
			
			default:
				return 'Text';
				break;
		}
	}
	
	/**
	 * Convert groups
	 *
	 * @return	void
	 */
	public function convertGroups()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'user_group_id' );
		
		foreach( $this->fetch( 'xf_user_group', 'user_group_id' ) AS $row )
		{
			/* Basic info */
			$info = array(
				'g_id'		=> $row['user_group_id'],
				'g_name'	=> $row['title'],
			);
			
			/* XenForo stores raw CSS to style usernames - we can convert this into a <span> tag with an inline style attribute */
			$style = str_replace( array( '<br>', '<br />' ), '', nl2br( $row['username_css'] ) );
			$info['prefix'] = "<span style='{$style}'>";
			$info['suffix']	= "</span>";
			
			/* General Permissions */
			foreach( $this->db->select( '*', 'xf_permission_entry', array( "user_group_id=?", $row['user_group_id'] ) ) AS $perm )
			{
				switch( $perm['permission_id'] )
				{
					case 'view':
						$info['g_view_board'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;
		
					case 'deleteOwnPost' :
						$info['g_delete_own_posts'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;
		
					case 'editOwnPost':
						$info['g_edit_posts'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;
							
					case 'postThread':
						$info['g_post_polls'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;

					case 'votePoll':
						$info['g_vote_polls'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;

					case 'deleteOwnThread':
						$info['g_delete_own_posts'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;

					case 'maxRecipients':
						$info['g_max_mass_pm'] = $perm['permission_value_int'];
						break;

					case 'bypassFloodCheck':
						$info['g_avoid_flood'] = ($perm['permission_value'] == 'allow') ? 1 : 0;
						break;
				}
			}
			
			$merge = ( $this->app->_session['more_info']['convertGroups']["map_group_{$row['user_group_id']}"] != 'none' ) ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['user_group_id']}"] : NULL;
			
			$libraryClass->convertGroup( $info, $merge );
			
			$libraryClass->setLastKeyValue( $row['user_group_id'] );
		}

		/* Now check for group promotions */
		if( count( $libraryClass->groupPromotions ) )
		{
			foreach( $libraryClass->groupPromotions as $groupPromotion )
			{
				$libraryClass->convertGroupPromotion( $groupPromotion );
			}
		}
	}
	
	/**
	 * Convert warn reasons
	 *
	 * @return	void
	 */
	public function convertWarnReasons()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'warning_definition_id' );
		
		foreach( $this->fetch( 'xf_warning_definition', 'warning_definition_id' ) AS $row )
		{
			$title	= $this->getPhrase( "warning_definition_{$row['warning_definition_id']}_title" );
			$remove	= ( $row['expiry_type'] == 'never' ) ? 0 : $row['expiry_default'];

			switch( $row['expiry_type'] )
			{
				case 'weeks':
					$remove = $remove * 7;
					break;
				
				case 'months':
					$remove = $remove * 30;
					break;
				
				case 'years':
					$remove = $remove * 365;
					break;
			}
			
			
			$libraryClass->convertWarnReason( array(
				'wr_id'					=> $row['warning_definition_id'],
				'wr_name'				=> $title,
				'wr_points_override'	=> $row['is_editable'],
				'wr_remove'				=> $remove,
				'wr_remove_unit'		=> 'd',
				'wr_remove_override'	=> $row['is_editable'],
			) );
			
			$libraryClass->setLastKeyValue( $row['warning_definition_id'] );
		}
	}

	/**
	 * @brief	Member history type map
	 */
	protected static $changeLogTypes = array( 'username' => 'display_name', 'email' => 'email_change', 'user_group_id' => 'member_group_id', 'secondar_group_ids' => 'mgroup_others' );

	/**
	 * Convert member history
	 *
	 * @return	void
	 */
	public function convertMemberHistory()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'log_id' );

		foreach( $this->fetch( 'xf_user_change_log', 'log_id', array( $this->db->in( 'field', array_keys( static::$changeLogTypes ) ) ) ) AS $change )
		{
			$libraryClass->convertMemberHistory( array(
					'log_id'		=> $change['log_id'],
					'log_member'	=> $change['user_id'],
					'log_by'		=> ( $change['edit_user_id'] !== $change['user_id'] ) ? $change['edit_user_id'] : null,
					'log_type'		=> static::$changeLogTypes[ $change['field'] ],
					'log_data'		=> array( 'old' => $change['old_value'], 'new' => $change['new_value'] ),
					'log_date'		=> $change['edit_date']
				)
			);

			$libraryClass->setLastKeyValue( $change['log_id'] );
		}
	}
	
	/**
	 * Convert members
	 *
	 * @return	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'xf_user.user_id' );
		
		$it = $this->fetch( 'xf_user', 'xf_user.user_id' )
			->join( 'xf_user_profile', 'xf_user.user_id = xf_user_profile.user_id' )
			->join( 'xf_user_option', 'xf_user.user_id = xf_user_option.user_id' );
		
		foreach( $it AS $row )
		{
			/* Fetch our password. XenForo supports multiple authentication types (XF, vB, SMF, etc.) so let's try and retain these as much as possible. */
			try
			{
				$auth = $this->db->select( 'data', 'xf_user_authenticate', array( "user_id=?", $row['user_id'] ) )->first();
				$data = \unserialize( $auth );

				if( !isset( $data['hash'] ) )
				{
					throw new \UnderflowException;
				}

				$hash = $data['hash'];
				
				/* vB, IPB, etc. */
				$salt = NULL;
				if ( isset( $data['salt'] ) )
				{
					$salt = $data['salt'];
				}
				
				/* SMF */
				if ( isset( $data['username'] ) )
				{
					$salt = $data['username'];
				}
			}
			catch( \UnderflowException $e )
			{
				/* Ut oh... do something random. */
				$hash = md5( uniqid() );
				$salt = md5( uniqid() );
			}
			
			/* IP Address */
			try
			{
				$ip = $this->db->select( 'ip', 'xf_ip', array( "user_id=? AND action=?", $row['user_id'], 'register' ) )->first();
			}
			catch( \UnderflowException $e )
			{
				try
				{
					$ip = $this->db->select( 'ip', 'xf_ip', array( "user_id=? AND action=?", $row['user_id'], 'login' ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$ip = '127.0.0.1';
				}
			}
			
			/* Last warn */
			try
			{
				$last_warn = $this->db->select( 'warning_date', 'xf_warning', array( "user_id=?", $row['user_id'] ), "warning_id DESC" )->first();
			}
			catch( \UnderflowException $e )
			{
				$last_warn = 0;
			}
			
			/* PM Count */
			try
			{
				$pm_count = $this->db->select( 'COUNT(*)', 'xf_conversation_master', array( "user_id=?", $row['user_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$pm_count = 0;
			}
			
			/* Auto Track */
			$auto_track = 0;
			if ( $row['default_watch_state'] == 'watch_email' )
			{
				$auto_track = array(
					'content'	=> 1,
					'comments'	=> 1,
					'method'	=> 'immediate',
				);
			}
			
			/* Ban */
			try
			{
				$ban = $this->db->select( 'end_date', 'xf_user_ban', array( "user_id=?", $row['user_id'] ) )->first();
				
				if ( $ban == 0 )
				{
					$ban = -1;
				}
			}
			catch( \UnderflowException $e )
			{
				$ban = 0;
			}
			
			/* Timezone Verification */
			try
			{
				$timezone = new \DateTimeZone( $row['timezone'] ); # if invalid, this will throw an exception
			}
			catch( \Exception $e )
			{
				$timezone = 'UTC';
			}
			
			/* Main Member Information */
			$info = array(
				'member_id'					=> $row['user_id'],
				'name'						=> $row['username'],
				'email'						=> $row['email'],
				'conv_password'					=> $hash,
				'conv_password_extra'			=> $salt,
				'member_group_id'			=> $row['user_group_id'],
				'joined'					=> $row['register_date'],
				'ip_address'				=> $ip,
				'warn_level'				=> $row['warning_points'],
				'warn_lastwarn'				=> $last_warn,
				'bday_day'					=> $row['dob_day'],
				'bday_month'				=> $row['dob_month'],
				'bday_year'					=> $row['dob_year'],
				'msg_count_new'				=> $row['conversations_unread'],
				'msg_count_total'			=> $pm_count,
				'last_visit'				=> $row['last_activity'],
				'last_activity'				=> $row['last_activity'],
				'auto_track'				=> $auto_track,
				'temp_ban'					=> $ban,
				'mgroup_others'				=> $row['secondary_group_ids'],
				'members_bitoptions'		=> array(
					'view_sigs'					=> $row['content_show_signature']
				),
				'pp_setting_count_comments'	=> 1, # always on for XF
				'pp_reputation_points'		=> $row['like_count'],
				'timezone'					=> $timezone,
				'allow_admin_mails'			=> $row['receive_admin_email'],
				'member_title'				=> $row['custom_title'],
				'member_posts'				=> $row['message_count'],
			);
			
			/* Profile Photo */
			$group		= floor( $row['user_id'] / 1000 );
			$path		= rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' );
			$filename	= NULL;
			$filepath	= NULL;
			if ( file_exists( $path . '/l/' . $group . '/' . $row['user_id'] . '.jpg' ) )
			{
				$filename = $row['user_id'] . '.jpg';
				$filepath = $path . '/l/' . $group;
			}
			else
			{
				/* Got a gravatar? */
				if ( $row['gravatar'] )
				{
					$info['pp_gravatar'] = $row['gravatar'];
				}
			}
			
			/* Profile Fields */
			$pfields = array();
			foreach( $this->db->select( '*', 'xf_user_field_value', array( "user_id=?", $row['user_id'] ) ) AS $field )
			{
				$pfields[ $field['field_id'] ] = $field['field_value'];
			}
			
			/* Pseudo Fields */
			foreach( array( 'homepage', 'location', 'occupation', 'about', 'gender' ) AS $pseudo )
			{
				/* Are we retaining? */
				if ( $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"] == 'no_convert' )
				{
					/* No, skip */
					continue;
				}
				
				try
				{
					/* We don't actually need this, but we need to make sure the field was created */
					$fieldId = $this->app->getLink( $pseudo, 'core_pfields_data' );
				}
				catch( \OutOfRangeException $e )
				{
					$libraryClass->convertProfileField( array(
						'pf_id'				=> $pseudo,
						'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"],
						'pf_desc'			=> '',
						'pf_type'			=> ($pseudo == 'gender') ? 'Select' : 'Text',
						'pf_content'		=> ($pseudo == 'gender') ? json_encode( array( 'male', 'female' ) ) : '[]',
						'pf_member_hide'	=> 0,
						'pf_max_input'		=> ($pseudo == 'gender') ? 0 : 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
						'pf_admin_only'		=> 0,
					) );
				}
				
				$pfields[$pseudo] = $row[$pseudo];
			}
			
			$libraryClass->convertMember( $info, $pfields, $filename, $filepath );
			
			/* Followers */
			foreach( $this->db->select( '*', 'xf_user_follow', array( "user_id=?", $row['user_id'] ) ) AS $follower )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'core',
					'follow_area'			=> 'members',
					'follow_rel_id'			=> $follower['follow_user_id'],
					'follow_rel_id_type'	=> 'core_members',
					'follow_member_id'		=> $follower['user_id'],
				) );
			}
			
			/* Warnings */
			foreach( $this->db->select( '*', 'xf_warning', array( "content_type=? AND user_id=?", 'user', $row['user_id'] ) ) AS $warning )
			{
				$warnId = $libraryClass->convertWarnLog( array(
						'wl_id'				=> $warning['warning_id'],
						'wl_member'			=> $warning['user_id'],
						'wl_moderator'		=> $warning['warning_user_id'],
						'wl_date'			=> $warning['warning_date'],
						'wl_reason'			=> $warning['warning_definition_id'],
						'wl_points'			=> $warning['points'],
						'wl_note_mods'		=> $warning['notes'],
						'wl_expire_date'	=> $warning['expiry_date'],
					) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warning['warning_id'],
						'log_member'	=> $warning['user_id'],
						'log_by'		=> $warning['warning_user_id'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warning['warning_date']
					)
				);
			}
			
			$libraryClass->setLastKeyValue( $row['user_id'] );
		}
	}
	
	/**
	 * Convert statuses
	 *
	 * @return	void
	 */
	public function convertStatuses()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'profile_post_id' );
		
		foreach( $this->fetch( 'xf_profile_post', 'profile_post_id' ) AS $row )
		{
			/* We have to query for the IP Address */
			try
			{
				$ip = $this->db->select( 'ip', 'xf_ip', array( "ip_id=?", $row['ip_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$ip = '127.0.0.1';
			}
			
			/* Approval State */
			switch( $row['message_state'] )
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
				'status_id'			=> $row['profile_post_id'],
				'status_member_id'	=> $row['profile_user_id'],
				'status_date'		=> $row['post_date'],
				'status_content'	=> $row['message'],
				'status_replies'	=> $row['comment_count'],
				'status_author_id'	=> $row['user_id'],
				'status_author_ip'	=> $ip,
				'status_approved'	=> $approved,
			);
			
			$libraryClass->convertStatus( $info );
			
			$libraryClass->setLastKeyValue( $row['profile_post_id'] );
		}
	}

	/**
	 * Convert one or more settings
	 *
	 * @param	array	$settings	Settings to convert
	 * @return	void
	 */
	public function convertSettings( $settings=array() )
	{
		foreach( $this->settingsMap() AS $theirs => $ours )
		{
			if ( !isset( $values[ $ours ] ) OR $values[ $ours ] == FALSE )
			{
				continue;
			}

			try
			{
				$setting = $this->db->select( 'option_value', 'xf_option', array( "option_id=?", $theirs ) )->first();
			}
			catch( \UnderflowException $e )
			{
				continue;
			}

			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $setting ), array( "conf_key=?", $ours ) );
		}
	}

	/**
	 * Convert status replies
	 *
	 * @return	void
	 */
	public function convertStatusReplies()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'profile_post_comment_id' );
		
		foreach( $this->fetch( 'xf_profile_post_comment', 'profile_post_comment_id' ) AS $row )
		{
			/* We need to query for the IP Address */
			try
			{
				$ip = $this->db->select( 'ip', 'xf_ip', array( "ip_id=?", $row['ip_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$ip = '127.0.0.1';
			}
			
			/* Approval State */
			switch( $row['message_state'] )
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
				'reply_id'			=> $row['profile_post_comment_id'],
				'reply_status_id'	=> $row['profile_post_id'],
				'reply_member_id'	=> $row['user_id'],
				'reply_date'		=> $row['comment_date'],
				'reply_content'		=> $row['message'],
				'reply_approved'	=> $approved,
				'reply_ip_address'	=> $ip,
			);
			
			$libraryClass->convertStatusReply( $info );
			
			$libraryClass->setLastKeyValue( $row['profile_post_comment_id'] );
		}
	}
	
	/**
	 * Convert ignored users
	 *
	 * @return	void
	 */
	public function convertIgnoredUsers()
	{
		$libraryClass = $this->getLibrary();
		
		foreach( $this->fetch( 'xf_user_ignored', 'user_id' ) AS $row )
		{
			$libraryClass->convertIgnoredUser( array(
				'ignore_id'			=> $row['user_id'] . '-' . $row['ignored_user_id'],
				'ignore_owner_id'	=> $row['user_id'],
				'ignore_ignore_id'	=> $row['ignored_user_id']
			) );
		}
	}
	
	/**
	 * Convert ranks
	 *
	 * @return	void
	 */
	public function convertRanks()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'minimum_level' );
		
		foreach( $this->fetch( 'xf_user_title_ladder', 'minimum_level' ) AS $row )
		{
			$libraryClass->convertRank( array(
				'id'		=> $row['minimum_level'],
				'title'		=> $row['title'],
				'posts'		=> $row['minimum_level'],
			) );
			
			$libraryClass->setLastKeyValue( $row['minimum_level'] );
		}
	}
	
	/**
	 * Convert private messages
	 *
	 * @return	void
	 */
	public function convertPrivateMessages()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'conversation_id' );
		
		foreach( $this->fetch( 'xf_conversation_master', 'conversation_id' ) AS $row )
		{
			$topic = array(
				'mt_id'				=> $row['conversation_id'],
				'mt_date'			=> $row['start_date'],
				'mt_title'			=> $row['title'],
				'mt_starter_id'		=> $row['user_id'],
				'mt_start_time'		=> $row['start_date'],
				'mt_last_post_time'	=> $row['last_message_date'],
				'mt_to_count'		=> $row['recipient_count'],
				'mt_replies'		=> $row['reply_count'],
			);
			
			$maps = array();
			foreach( $this->db->select( '*', 'xf_conversation_user', array( 'conversation_id=?', $row['conversation_id'] ) ) AS $map )
			{
				try
				{
					$recip = $this->db->select( '*', 'xf_conversation_recipient', array( "conversation_id=? AND user_id=?", $row['conversation_id'], $map['owner_user_id'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$recip = array(
						'conversation_id'	=> $row['conversation_id'],
						'user_id'			=> $map['owner_user_id'],
						'recipient_state'	=> 'deleted',
						'last_read_date'	=> time(),
					);
				}
				$maps[$map['owner_user_id']] = array(
					'map_user_id'			=> $map['owner_user_id'],
					'map_read_time'			=> $recip['last_read_date'],
					'map_user_active'		=> ( $recip['recipient_state'] == 'active' ) ? 1 : 0,
					'map_user_banned'		=> ( $recip['recipient_state'] == 'deleted_ignored' ) ? 1 : 0,
					'map_has_unread'		=> $map['is_unread'],
					'map_is_starter'		=> ( $map['owner_user_id'] == $row['user_id'] ) ? 1 : 0,
					'map_last_topic_reply'	=> $map['last_message_date']
				);
			}
			
			$libraryClass->convertPrivateMessage( $topic, $maps );
			
			$libraryClass->setLastKeyValue( $row['conversation_id'] );
		}
	}
	
	/**
	 * Convert private message replies
	 *
	 * @return	void
	 */
	public function convertPrivateMessageReplies()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'message_id' );
		$ips = array();
		foreach( $this->fetch( 'xf_conversation_message', 'message_id' ) AS $row )
		{
			if ( !isset( $ips[$row['ip_id'] ] ) )
			{
				try
				{
					$ips[$row['ip_id']] = $this->db->select( 'ip', 'xf_ip', array( "ip_id=?", $row['ip_id'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$ips[$row['ip_id']] = '127.0.0.1';
				}
			}
			
			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $row['message_id'],
				'msg_topic_id'		=> $row['conversation_id'],
				'msg_date'			=> $row['message_date'],
				'msg_post'			=> $row['message'],
				'msg_author_id'		=> $row['user_id'],
				'msg_ip_address'	=> $ips[$row['ip_id']],
			) );
			
			$libraryClass->setLastKeyValue( $row['message_id'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 * @todo	The proxy.php redirection is not validating the hash that XenForo set previously, derived from the globalSalt and the option imageLinkProxyKey. We need
	 *	to either validate the hash (there is an open proxy concern), or we need to convert all calls to proxy.php in posted content to prevent broken images in posts.
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		if( preg_match( '#/members/(.+?)\.([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			/* If we can't access profiles, don't bother trying to redirect */
			if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
			{
				return NULL;
			}

			try
			{
				$data = (string) $this->app->getLink( (int) $matches[2], array( 'members', 'core_members' ) );
				return \IPS\Member::load( $data )->url();
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'proxy.php' ) !== FALSE AND isset( \IPS\Request::i()->image ) )
		{
			return \IPS\Http\Url::internal( "applications/core/interface/imageproxy/imageproxy.php", 'none' )->setQueryString( array(
						'img'	=> \IPS\Request::i()->image,
						'key'	=> hash_hmac( "sha256", (string) \IPS\Request::i()->image, \IPS\Settings::i()->site_secret_key )
					) );
		}

		return NULL;
	}
}