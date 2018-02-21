<?php
/**
 * @brief		Profile Completiong Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Nov 2016
 */

namespace IPS\core\extensions\core\ProfileSteps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _Core
{
	/* !Extension Methods */
	
	/**
	 * Available Actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function actions()
	{
		$return = array( 'basic_profile' => 'complete_profile_basic_profile' );
		
		/* Social Integration */
		foreach( array( 'facebook', 'twitter', 'linkedin', 'google', 'live' ) AS $service )
		{
			try
			{
				$handler = \IPS\Login::getHandler( ucfirst( $service ) );
				if ( $handler->_enabled )
				{
					$return['social_login'] = 'complete_profile_social_login';
					break;
				}
			}
			catch ( \UnderflowException $e ){}

		}
		
		return $return;
	}
	
	/**
	 * Available sub actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function subActions()
	{
		/* Basic stuff */
		$return['basic_profile'] = array(
			'photo'			=> 'complete_profile_photo',
			'birthday'		=> 'complete_profile_birthday',
			'cover_photo'	=> 'complete_profile_cover_photo'
		);
		
		/* Signatures */
		if ( \IPS\Settings::i()->signatures_enabled )
		{
			$return['basic_profile']['signature'] = 'complete_profile_signature';
		}
		
		/* Social Integration */
		foreach( array( 'facebook', 'twitter', 'linkedin', 'google', 'live' ) AS $service )
		{
			try
			{
				$handler = \IPS\Login::getHandler( ucfirst( $service ) );
				if ( $handler->_enabled )
				{
					$return['social_login'][ $service ] = "complete_profile_{$service}";
				}
			}
			catch ( \UnderflowException $e ){}

		}
		
		return $return;
	}
	
	/**
	 * Can the actions have multiple choices?
	 *
	 * @param	string		$action		Action key (basic_profile, etc)
	 * @return	boolean
	 */
	public static function actionMultipleChoice( $action )
	{
		switch( $action )
		{
			case 'basic_profile':
				return TRUE;
			break;
			case 'social_login':
				return FALSE;
			break;
		}
		
		return FALSE;
	}
	
	/**
	 * Can be set as required?
	 *
	 * @return	array
	 * @note	This is intended for items which have their own independent settings and dedicated enable pages, such as Social Login integration
	 */
	public static function canBeRequired()
	{
		return array( 'basic_profile' );
	}
	
	/**
	 * Format Form Values
	 *
	 * @param	array
	 * @param	\IPS\Member
	 * @param	\IPS\Helpers\Form
	 * @return	void
	 */
	public static function formatFormValues( $values, &$member, &$form )
	{
		if( array_key_exists( 'signature', $values ) )
		{
			$sigLimits = explode( ":", $member->group['g_signature_limits'] );
			
			/* Check Limits */
			$signature = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$signature->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $values['signature'] ) );
			
			$errors = array();
				
			/* Links */
			if ( is_numeric( $sigLimits[4] ) and $signature->getElementsByTagName('a')->length > $sigLimits[4] )
			{
				$errors[] = $member->language()->addToStack('sig_num_links_exceeded');
			}

			/* Number of Images */
			if ( is_numeric( $sigLimits[1] ) and $signature->getElementsByTagName('img')->length > 0 )
			{
				$imageCount = 0;
				foreach ( $signature->getElementsByTagName('img') as $img )
				{
					if( !$img->hasAttribute("data-emoticon") )
					{
						$imageCount++;
					}
				}
				if( $imageCount > $sigLimits[1] )
				{
					$errors[] = $member->language()->addToStack('sig_num_images_exceeded');
				}
			}
			
			/* Size of images */
			if ( ( is_numeric( $sigLimits[2] ) and $sigLimits[2] ) or ( is_numeric( $sigLimits[3] ) and $sigLimits[3] ) )
			{
				foreach ( $signature->getElementsByTagName('img') as $image )
				{
					$attachId	= $image->getAttribute('data-fileid');
					$checkSrc	= TRUE;
					if( $attachId )
					{
						try
						{
							$attachment = \IPS\Db::i()->select( 'attach_location, attach_thumb_location', 'core_attachments', array( 'attach_id=?', $attachId ) )->first();
							$imageProperties = \IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] ?: $attachment['attach_location'] )->getImageDimensions();
							$checkSrc	= FALSE;
						}
						catch( \UnderflowException $e ){}
					}
					if( $checkSrc )
					{
						$src = $image->getAttribute('src');
						\IPS\Output::i()->parseFileObjectUrls( $src );
						$imageProperties = @getimagesize( $src );
					}
					
					if( is_array( $imageProperties ) AND count( $imageProperties ) )
					{
						if( $imageProperties[0] > $sigLimits[2] OR $imageProperties[1] > $sigLimits[3] )
						{
							$errors[] = $member->language()->addToStack( 'sig_imagetoobig', FALSE, array( 'sprintf' => array( $src, $sigLimits[2], $sigLimits[3] ) ) );
						}
					}
					else
					{
						$errors[] = $member->language()->addToStack( 'sig_imagenotretrievable', FALSE, array( 'sprintf' => array( $src ) ) );
					}
				}
			}
			
			/* Lines */
			$preBreaks = 0;
			
			/* Make sure we are not trying to bypass the limit by using <pre> tags, which will not have <p> or <br> tags in its content */
			foreach( $signature->getElementsByTagName('pre') AS $pre )
			{
				$content = nl2br( trim( $pre->nodeValue ) );
				$preBreaks += count( explode( "<br />", $content ) );
			}
			
			if ( is_numeric( $sigLimits[5] ) and ( $signature->getElementsByTagName('p')->length + $signature->getElementsByTagName('br')->length + $preBreaks ) > $sigLimits[5] )
			{
				$errors[] = $member->language()->addToStack('sig_num_lines_exceeded');
			}
			
			if( !empty( $errors ) )
			{
				$form->error = $member->language()->addToStack('sig_restrictions_exceeded');
				$form->elements['']['signature']->error = $member->language()->formatList( $errors );
			}
			else
			{
				$member->signature = $values['signature'];
			}
		}
		
		if ( array_key_exists( 'pp_photo_type', $values ) )
		{
			$photoVars = explode( ':', $member->group['g_photo_max_vars'] );
			
			$member->pp_photo_type = $values['pp_photo_type'];
			
			switch ( $values['pp_photo_type'] )
			{
				case 'custom':
					if ( $photoVars[0] and $values['member_photo_upload'] )
					{
						if ( (string) $values['member_photo_upload'] !== '' )
						{
							$member->pp_photo_type  = 'custom';
							$member->pp_main_photo  = NULL;
							$member->pp_main_photo  = (string) $values['member_photo_upload'];
							
							$thumbnail = $values['member_photo_upload']->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE, TRUE );
							$member->pp_thumb_photo = (string) $thumbnail;
							
							$member->photo_last_update = time();
						}
					}
					break;
			
				case 'url':
					if( $photoVars[0] and $values['member_photo_url'] )
					{
						$member->pp_photo_type = 'custom';
						$member->pp_main_photo = NULL;
						$member->pp_main_photo = (string) $values['member_photo_url'];
						
						$thumbnail = $values['member_photo_url']->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE, TRUE );
						$member->pp_thumb_photo = (string) $thumbnail;
						
						$member->photo_last_update = time();
					}
					break;
						
				case 'none':
					$member->pp_main_photo								= NULL;
					$member->members_bitoptions['bw_disable_gravatar']	= 1;
					$member->photo_last_update = NULL;
					break;
					
				case 'gravatar':
					$member->pp_gravatar = ( !isset( $values['photo_gravatar_email_public'] ) or $values['photo_gravatar_email_public'] === $member->email ) ? NULL : $values['photo_gravatar_email_public'];
					break;
			}
		}
		
		if ( array_key_exists( 'complete_profile_cover_photo', $values ) )
		{
			$photo = $member->coverPhoto();
			try
			{
				$photo->delete();
			}
			catch ( \Exception $e ) { }
			
			/* Make sure profile sync services are disabled */
			$services = \IPS\core\ProfileSync\ProfileSyncAbstract::services();
		
			if ( !empty( $services ) )
			{
				foreach( $services AS $class )
				{
					$obj = new $class( $member );
					if ( $obj->connected() )
					{
						$obj->save( array( 'profilesync_cover' => FALSE ) );
					}
				}
			}
			
			$newPhoto = new \IPS\Helpers\CoverPhoto( $values['complete_profile_cover_photo'], 0 );

			$member->pp_cover_photo = (string) $newPhoto->file;
			$member->pp_cover_offset = (int) $newPhoto->offset;
		}
		
		if ( array_key_exists( 'bday', $values ) )
		{
			$member->bday_month	= $values['bday']['month'];
			$member->bday_day	= $values['bday']['day'];
			$member->bday_year	= $values['bday']['year'];
		}
	}
	
	/**
	 * Has a specific step been completed?
	 *
	 * @param	\IPS\Member\ProfileStep	The step to check
	 * @param	\IPS\Member|NULL		The member to check, or NULL for currently logged in
	 * @return	bool
	 */
	public function completed( \IPS\Member\ProfileStep $step, \IPS\Member $member = NULL )
	{
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		static::$_member = $member ?: \IPS\Member::loggedIn();
		static::$_step = $step;

		/* If any sub-step is incomplete, then we have not completed this step */
		foreach( $step->subcompletion_act as $item )
		{
			$method = 'completed' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $item ) ) );

			if ( method_exists( $this, $method ) )
			{
				if( ! static::$method() )
				{
					return FALSE;
				}
			}
			else
			{
				\IPS\Log::debug( "missing_profile_step_method", 'profile_completion' );
				
				continue;
			}
		}

		return TRUE;
	}
	
	/**
	 * Wizard Steps
	 *
	 * @param	\IPS\Member	$member	Member or NULL for currently logged in member
	 * @return	array
	 */
	public static function wizard( \IPS\Member $member = NULL )
	{
		static::$_member = $member ?: \IPS\Member::loggedIn();
		
		$return = array();
		
		$return = array_merge( $return, static::wizardBasicProfile() );
		$return = array_merge( $return, static::wizardSocial() );
		
		return $return;
	}
	
	/* !Completed Utility Methods */
	
	/**
	 * Member
	 */
	protected static $_member = NULL;
	
	/**
	 * Step
	 */
	protected static $_step = NULL;
	
	/**
	 * Added a photo?
	 *
	 * @return	bool
	 */
	protected static function completedPhoto()
	{
		if ( !static::$_member->pp_photo_type )
		{
			return FALSE;
		}
		
		if ( static::$_member->pp_photo_type === 'none' )
		{
			return FALSE;
		}
		
		if ( static::$_member->pp_photo_type === 'letter' )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Added their birthday?
	 *
	 * @return	bool
	 */
	protected static function completedBirthday()
	{
		if ( ! static::$_member->group['g_edit_profile'] )
		{
			/* Member has no permission to edit profile */
			return TRUE;
		}
		
		return (bool) static::$_member->birthday;
	}
	
	/**
	 * Added a cover photo?
	 *
	 * @return	bool
	 */
	protected static function completedCoverPhoto()
	{
		if ( ! static::$_member->group['g_edit_profile'] )
		{
			/* Member has no permission to edit profile */
			return TRUE;
		}
		
		return (bool) static::$_member->pp_cover_photo;
	}
	
	/**
	 * Added a signature?
	 *
	 * @return	bool
	 */
	protected static function completedSignature()
	{
		if ( ! static::$_member->canEditSignature() )
		{
			/* Mark complete as signatures off or no permission to edit profile */
			return TRUE;
		}

		return (bool) ( static::$_member->signature );
	}
	
	/**
	 * Connected to Facebook?
	 *
	 * @return	bool
	 */
	protected static function completedFacebook()
	{
		if( !isset( \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Facebook'] ))
		{
			/* Not enabled */
			return TRUE;
		}

		$service = \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Facebook'];
		$class = new $service( static::$_member );
		return $class->connected();
	}
	
	/**
	 * Connected to Twitter?
	 *
	 * @return	bool
	 */
	protected static function completedTwitter()
	{
		if( !isset( \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Twitter'] ))
		{
			/* Not enabled */
			return TRUE;
		}

		$service = \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Twitter'];
		$class = new $service( static::$_member );
		return $class->connected();
	}
	
	/**
	 * Connected to LinkedIn?
	 *
	 * @return	bool
	 */
	protected static function completedLinkedin()
	{
		if( !isset( \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Linkedin'] ))
		{
			/* Not enabled */
			return TRUE;
		}

		$service = \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Linkedin'];
		$class = new $service( static::$_member );
		return $class->connected();
	}
	
	/**
	 * Connected to Google?
	 *
	 * @return	bool
	 */
	protected static function completedGoogle()
	{
		if( !isset( \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Google'] ))
		{
			/* Not enabled */
			return TRUE;
		}

		$service = \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Google'];
		$class = new $service( static::$_member );
		return $class->connected();
	}
	
	/**
	 * Connected to Live?
	 *
	 * @return	bool
	 */
	protected static function completedLive()
	{
		if( !isset( \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Microsoft'] ))
		{
			/* Not enabled */
			return TRUE;
		}

		$service = \IPS\core\ProfileSync\ProfileSyncAbstract::services()['Microsoft']; # Inconsistency ftl
		$class = new $service( static::$_member );
		return $class->connected();
	}
	
	/* !Wizard Utility Methods */
	
	/**
	 * Wizard: Basic Profile
	 *
	 * @return	array
	 */
	protected static function wizardBasicProfile()
	{
		$member = static::$_member;
		$wizards = array();
		
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			$include = array();
			if ( $step->completion_act === 'basic_profile' )
			{
				foreach( $step->subcompletion_act as $item )
				{
					switch( $item )
					{
						case 'photo':
							if ( !static::completedPhoto( static::$_member ) )
							{
								$include['photo'] = $step;
							}
						break;
						
						case 'birthday':
							if ( !static::completedBirthday( static::$_member ) )
							{
								$include['birthday'] = $step;
							}
						break;
						
						case 'signature':
							if ( !static::completedSignature( static::$_member ) )
							{
								$include['signature'] = $step;
							}
						break;
						
						case 'cover_photo':
							if ( !static::completedCoverPhoto( static::$_member ) )
							{
								$include['cover_photo'] = $step;
							}
						break;
					}
				}
				
				if ( count( $include ) )
				{
					$wizards[ $step->key ] = function( $data ) use ( $member, $include, $step ) {
						$form = new \IPS\Helpers\Form( 'profile_generic_' . $step->id, 'profile_complete_next' );
						
						if ( isset( $include['photo'] ) )
						{
							static::photoForm( $form, $include['photo'], $member );
						}
						
						if ( isset( $include['birthday'] ) )
						{
							static::birthdayForm( $form, $include['birthday'], $member );
						}
						
						if ( isset( $include['signature'] ) )
						{
							static::signatureForm( $form, $include['signature'], $member );
						}
						
						if ( isset( $include['cover_photo'] ) )
						{
							static::coverPhotoForm( $form, $include['cover_photo'], $member );
						}
						
						/* The forms are built immediately after posting which means it resubmits with empty values which confuses some form elements */
						if ( $values = $form->values() )
						{
							static::formatFormValues( $values, $member, $form );
							$member->save();
							return $values;
						}
	
						return $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'profileCompleteTemplate' ), $step );
					};
				}
			}
		}
		
		return $wizards;
	}
	
	/**
	 * Wizard: Social
	 *
	 * @return	array
	 */
	protected static function wizardSocial()
	{
		$return = array();
		$member = static::$_member;
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			if ( $step->completion_act === 'social_login' )
			{
				foreach( $step->subcompletion_act as $item )
				{
					switch( $item )
					{
						case 'facebook':
							if ( !$step->completed( $member ) )
							{
								$return[ $step->key ] = function( $data ) use ( $member, $step ) {
									$login = \IPS\Login\LoginAbstract::load( 'Facebook' );
									$url = \IPS\Http\Url::internal( "app=core&module=system&controller=settings&do=completion", 'front', "settings" );
									
									if ( \IPS\Request::i()->loginProcess )
									{
										try
										{
											$login->authenticate( $url, \IPS\Member::loggedIn() );
										}
										catch ( \IPS\Login\Exception $e ) { }
										
										return array();
									}
									
									return \IPS\Theme::i()->getTemplate( 'system' )->profileCompleteSocial( $step, \IPS\Theme::i()->getTemplate( 'system' )->settingsProfileSyncLogin( $login->loginForm( $url, TRUE ),  "profilesync__Facebook" ), \IPS\Request::i()->url() );
								};
							}
						break;
						
						case 'twitter':
							if ( !$step->completed( $member ) )
							{
								$return[ $step->key ] = function( $data ) use ( $member, $step ) {
									$login = \IPS\Login\LoginAbstract::load( 'Twitter' );
									$url = \IPS\Http\Url::internal( "app=core&module=system&controller=settings&do=completion", 'front', "settings" );
									
									if ( \IPS\Request::i()->loginProcess )
									{
										try
										{
											$login->authenticate( $url, \IPS\Member::loggedIn() );
										}
										catch ( \IPS\Login\Exception $e ) { }
										
										return array();
									}
									
									return \IPS\Theme::i()->getTemplate( 'system' )->profileCompleteSocial( $step, \IPS\Theme::i()->getTemplate( 'system' )->settingsProfileSyncLogin( $login->loginForm( $url, TRUE ),  "profilesync__Twitter" ), \IPS\Request::i()->url() );
								};
							}
						break;
						
						case 'google':
							if ( !$step->completed( $member ) )
							{
								$return[ $step->key ] = function( $data ) use ( $member, $step ) {
									$login = \IPS\Login\LoginAbstract::load( 'Google' );
									$url = \IPS\Http\Url::internal( "app=core&module=system&controller=settings&do=completion", 'front', "settings" );
									
									if ( \IPS\Request::i()->loginProcess )
									{
										try
										{
											$login->authenticate( $url, \IPS\Member::loggedIn() );
										}
										catch ( \IPS\Login\Exception $e ) { }
										
										return array();
									}
									
									return \IPS\Theme::i()->getTemplate( 'system' )->profileCompleteSocial( $step, \IPS\Theme::i()->getTemplate( 'system' )->settingsProfileSyncLogin( $login->loginForm( $url, TRUE ),  "profilesync__Google" ), \IPS\Request::i()->url() );
								};
							}
						break;
						
						case 'linkedin':
							if ( !$step->completed( $member ) )
							{
								$return[ $step->key ] = function( $data ) use ( $member, $step ) {
									$login = \IPS\Login\LoginAbstract::load( 'Linkedin' );
									$url = \IPS\Http\Url::internal( "app=core&module=system&controller=settings&do=completion", 'front', "settings" );
									
									if ( \IPS\Request::i()->loginProcess )
									{
										try
										{
											$login->authenticate( $url, \IPS\Member::loggedIn() );
										}
										catch ( \IPS\Login\Exception $e ) { }
										
										return array();
									}
									
									return \IPS\Theme::i()->getTemplate( 'system' )->profileCompleteSocial( $step, \IPS\Theme::i()->getTemplate( 'system' )->settingsProfileSyncLogin( $login->loginForm( $url, TRUE ),  "profilesync__Linkedin" ), \IPS\Request::i()->url() );
								};
							}
						break;
						
						case 'live':
							if ( !$step->completed( $member ) )
							{
								$return[ $step->key ] = function( $data ) use ( $member, $step ) {
									$login = \IPS\Login\LoginAbstract::load( 'Live' );
									$url = \IPS\Http\Url::internal( "app=core&module=system&controller=settings&do=completion", 'front', "settings" );
									
									if ( \IPS\Request::i()->loginProcess )
									{
										try
										{
											$login->authenticate( $url, \IPS\Member::loggedIn() );
										}
										catch ( \IPS\Login\Exception $e ) { }
										
										return array();
									}
									
									return \IPS\Theme::i()->getTemplate( 'system' )->profileCompleteSocial( $step, \IPS\Theme::i()->getTemplate( 'system' )->settingsProfileSyncLogin( $login->loginForm( $url, TRUE ),  "profilesync__Live" ), \IPS\Request::i()->url() );
								};
							}
						break;
					}
				}
			}
		}

		return $return;
	}
	
	/* !Misc Utility Methods */
	
	/**
	 * Photo Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\ProfileStep	$step	The step
	 * @param	\IPS\Member				$member	The member
	 * @return	void
	 */
	protected static function photoForm( &$form, $step, $member )
	{
		$photoVars = explode( ':', $member->group['g_photo_max_vars'] );
					
		$toggles = array( 'custom' => array( 'member_photo_upload' ), 'url' => array( 'member_photo_url' ) );
		$extra = array();
		$options = array();
		
		if ( $photoVars[0] )
		{
			$options['custom'] = 'member_photo_upload';
			$options['url'] = 'member_photo_url';
		}
		
		if ( \IPS\Settings::i()->allow_gravatars )
		{
			$options['gravatar'] = 'member_photo_gravatar';
			if ( $member->modPermission('can_see_emails') )
			{
				$extra[] = new \IPS\Helpers\Form\Email( 'photo_gravatar_email_public', $member->email, FALSE, array( 'maxLength' => 255 ), NULL, NULL, NULL, 'member_photo_gravatar' );
				$toggles['gravatar'] = array( 'member_photo_gravatar' );
			}
		}
			
		if ( !$step->required )
		{
			$options['none'] = 'member_photo_none';
		}
		
		$form->add( new \IPS\Helpers\Form\Radio( 'pp_photo_type', 'none', $step->required, array( 'options' => $options, 'toggles' => $toggles ) ) );
		
		if ( $photoVars[0] )
		{
			$form->add( new \IPS\Helpers\Form\Upload( 'member_photo_upload', NULL, FALSE, array( 'image' => array( 'maxWidth' => $photoVars[1], 'maxHeight' => $photoVars[2] ), 'storageExtension' => 'core_Profile', 'maxFileSize' => $photoVars[0] ? $photoVars[0] / 1024 : NULL ), function( $val ) use ( $member ) {
				if ( $val instanceof \IPS\File )
				{
					$image = \IPS\Image::create( $val->contents() );
					if( $image->isAnimatedGif and !$member->group['g_upload_animated_photos'] )
					{
						throw new \DomainException('member_photo_upload_no_animated');
					}
				}
			}, NULL, NULL, 'member_photo_upload' ) );
			
			$form->add( new \IPS\Helpers\Form\Url( 'member_photo_url', NULL, FALSE, array( 'file' => 'core_Profile', 'allowedMimes' => 'image/*', 'maxFileSize' => $photoVars[0] ? $photoVars[0] / 1024 : NULL, 'maxDimensions' => array( 'width' => $photoVars[1], 'height' => $photoVars[2] ) ), NULL, NULL, NULL, 'member_photo_url' ) );
		}
		
		foreach ( $extra as $element )
		{
			$form->add( $element );
		}
	}
	
	/**
	 * Birthday Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\ProfileStep	$step	The step
	 * @param	\IPS\Member				$member	The member
	 * @return	void
	 */
	protected static function birthdayForm( &$form, $step, $member )
	{
		$form->add( new \IPS\Helpers\Form\Custom( 'bday', NULL, $step->required, array( 'getHtml' => function( $element ) use ( $member, $step )
		{
			return strtr( $member->language()->preferredDateFormat(), array(
				'DD'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_day( $element->name, $element->value, $element->error ),
				'MM'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_month( $element->name, $element->value, $element->error ),
				'YY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error, $step->required ),
				'YYYY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error, $step->required ),
			) );
		} ),
		/* Validation */
		function( $val ) use ( $step )
		{
			if ( $step->required and ( ! $val['day'] and ! $val['month'] and ! $val['year'] ) )
			{
				throw new \InvalidArgumentException('form_required');
			}
		} ) );
		
		if ( \IPS\Settings::i()->profile_birthday_type == 'private' )
		{
			$form->addMessage( 'profile_birthday_display_private', 'ipsMessage ipsMessage_info' );
		}
	}
	
	/**
	 * Birthday Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\ProfileStep	$step	The step
	 * @param	\IPS\Member				$member	The member
	 * @return	void
	 */
	protected static function signatureForm( &$form, $step, $member )
	{
		$form->add( new \IPS\Helpers\Form\Editor( 'signature', $member->signature, $step->required, array( 'app' => 'core', 'key' => 'Signatures', 'autoSaveKey' => "frontsig-" .$member->member_id, 'attachIds' => array( $member->member_id ) ) ) );
	}
	
	/**
	 * Cover Photo Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\ProfileStep	$step	The step
	 * @param	\IPS\Member				$member	The member
	 * @return	void
	 */

	protected static function coverPhotoForm( &$form, $step, $member )
	{
		$photo = $member->coverPhoto();

		$form->add( new \IPS\Helpers\Form\Upload( 'complete_profile_cover_photo', NULL, $step->required, array( 'image' => TRUE, 'minimize' => TRUE, 'maxFileSize' => ( $photo->maxSize and $photo->maxSize != -1 ) ? $photo->maxSize / 1024 : NULL, 'storageExtension' => 'core_Profile' ) ) );
				
	}
}