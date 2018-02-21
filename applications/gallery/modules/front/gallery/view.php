<?php
/**
 * @brief		View image
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\modules\front\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View image or movie
 */
class _view extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\gallery\Image';
	
	/**
	 * @brief	Should views and item markers be updated by AJAX requests?
	 * @note	For Gallery images, AJAX requests handle the next/previous links and using those needs to mark as read, etc.
	 */
	protected $updateViewsAndMarkersOnAjax = TRUE;

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( \IPS\Request::i()->do != 'embed' )
		{
			try
			{
				$this->image = \IPS\gallery\Image::load( \IPS\Request::i()->id );
				
				if ( !$this->image->canView( \IPS\Member::loggedIn() ) )
				{
					\IPS\Output::i()->error( $this->image->container()->errorMessage(), '2G188/1', 403, '' );
				}				
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G188/2', 404, '' );
			}
		}

		parent::execute();
	}
	
	/**
	 * View Image
	 *
	 * @return	void
	 * @link	http://www.videojs.com/projects/mimes.html
	 * @note	Only HTML5 and some flash-based video formats will work. MP4, webm and ogg are relatively safe bets but anything else isn't likely to play correctly.
	 *	The above link will allow you to check what is supported in the browser you are using.
	 * @note	As of RC1 we fall back to a generic 'embed' for non-standard formats for better upgrade compatibility...need to look into transcoding in the future
	 */
	protected function manage()
	{
		/* Init */
		parent::manage();

		/* Check restrictions */
		if( \IPS\Settings::i()->gallery_detailed_bandwidth AND ( \IPS\Member::loggedIn()->group['g_max_transfer'] OR \IPS\Member::loggedIn()->group['g_max_views'] ) )
		{
			$lastDay		= \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp();

			if( \IPS\Member::loggedIn()->group['g_max_views'] )
			{
				if( \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_bandwidth', array( 'member_id=? AND bdate > ?', (int) \IPS\Member::loggedIn()->member_id, $lastDay ) )->first() >= \IPS\Member::loggedIn()->group['g_max_views'] )
				{
					\IPS\Output::i()->error( 'maximum_daily_views', '1G188/7', 403, 'maximum_daily_views_admin' );
				}
			}

			if( \IPS\Member::loggedIn()->group['g_max_transfer'] )
			{
				if( \IPS\Db::i()->select( 'SUM(bsize) as total', 'gallery_bandwidth', array( 'member_id=? AND bdate > ?', (int) \IPS\Member::loggedIn()->member_id, $lastDay ) )->first() >= ( \IPS\Member::loggedIn()->group['g_max_transfer'] * 1024 ) )
				{
					\IPS\Output::i()->error( 'maximum_daily_transfer', '1G188/8', 403, 'maximum_daily_transfer_admin' );
				}
			}
		}

		/* Set some meta tags */
		if( $this->image->media )
		{
			\IPS\Output::i()->metaTags['og:video']		= \IPS\File::get( 'gallery_Images', $this->image->original_file_name )->url;
			\IPS\Output::i()->metaTags['og:video:type']	= $this->image->file_type;
			\IPS\Output::i()->metaTags['og:type']		= 'video';

			if( count( $this->image->tags() ) )
			{
				\IPS\Output::i()->metaTags['og:video:tag']	= $this->image->tags();
			}

			if( $this->image->medium_file_name )
			{
				\IPS\Output::i()->metaTags['og:image']		= \IPS\File::get( 'gallery_Images', $this->image->medium_file_name )->url;
			}
		}
		else
		{
			\IPS\Output::i()->metaTags['og:image']		= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url;
			\IPS\Output::i()->metaTags['og:image:type']	= $this->image->file_type;

			if( count( $this->image->tags() ) )
			{
				\IPS\Output::i()->metaTags['og:object:tag']	= $this->image->tags();
			}
		}

		/* Sort out comments and reviews */
		$tabs = $this->image->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $this->image->commentReviews( $tab );

		if ( count( $tabs ) > 1 )
		{
			$commentsAndReviews = count( $tabs ) ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, $this->image->url(), 'tab', FALSE, TRUE ) : NULL;
		}
		else
		{
			$commentsAndReviews = $activeTabContents;
		}

		/* Set the session location */
		\IPS\Session::i()->setLocation( $this->image->url(), $this->image->onlineListPermissions(), 'loc_gallery_viewing_image', array( $this->image->caption => FALSE ) );

		/* Get next 2 and previous 2 images in the container for slider */
		$slider		= array(
			'previous'	=> $this->image->previousImages( 4 ),
			'next'		=> $this->image->nextImages( 4 ),
		);

		$hasNext = TRUE;
		$hasPrev = TRUE;

		if( count( $slider['previous'] ) < 4 )
		{
			$hasPrev = FALSE;
		}
		else
		{
			$slider['previous'] = array_slice( $slider['previous'], 0, -1 );
		}

		if( count( $slider['next'] ) < 4 )
		{
			$hasNext = FALSE;
		}
		else
		{
			$slider['next'] = array_slice( $slider['next'], 0, -1 );
		}

		/* If this is a video, grab the necessary javascript */
		if( $this->image->media )
		{
			if( \IPS\IN_DEV )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'videojs/video.js', 'gallery', 'interface' ) );
			}
			else
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'videojs/video.min.js', 'gallery', 'interface' ) );
			}
		}

		/* Store bandwidth log */
		if( \IPS\Settings::i()->gallery_detailed_bandwidth )
		{
			/* Media items should get the file size of the original file instead of a thumbnail */
			if( $this->image->media )
			{
				$displayedImage = \IPS\File::get( 'gallery_Images', $this->image->original_file_name );
			}
			/* Otherwise, fetch the thumbnails */
			elseif( ( \IPS\Request::i()->imageSize == 'medium' OR !\IPS\Request::i()->imageSize ) AND $this->image->medium_file_name )
			{
				$displayedImage	= \IPS\File::get( 'gallery_Images', $this->image->medium_file_name );
			}
			elseif( \IPS\Request::i()->imageSize == 'large' )
			{
				$displayedImage	= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name );

				/* We hide the sidebar when displaying the large image */
				\IPS\Output::i()->sidebar['enabled'] = FALSE;
			}
			elseif( \IPS\Request::i()->imageSize == 'small' )
			{
				$displayedImage	= \IPS\File::get( 'gallery_Images', $this->image->small_file_name );
			}
			elseif( \IPS\Request::i()->imageSize == 'thumb' )
			{
				$displayedImage	= \IPS\File::get( 'gallery_Images', $this->image->thumb_file_name );
			}

			/* Get filesize, but don't error out if there is a problem fetching it at this point */
			try
			{
				$filesize = ( ( isset( $displayedImage ) AND $displayedImage->filesize() ) ? $displayedImage->filesize() : $this->image->file_size );
			}
			catch( \Exception $e )
			{
				$filesize = $this->image->file_size;
			}

			\IPS\Db::i()->insert( 'gallery_bandwidth', array(
				'member_id'		=> (int) \IPS\Member::loggedIn()->member_id,
				'bdate'			=> time(),
				'bsize'			=> (int) $filesize,
				'image_id'		=> $this->image->id
			)	);
		}

		/* Add JSON-ld */
		\IPS\Output::i()->jsonLd['gallery']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> $this->image->media ? "VideoObject" : "VisualArtwork",
			'@id'			=> (string) $this->image->url(),
			'url'			=> (string) $this->image->url(),
			'name'			=> $this->image->mapped('title'),
			'description'	=> strip_tags( $this->image->truncated() ),
			'dateCreated'	=> \IPS\DateTime::ts( $this->image->date )->format( \IPS\DateTime::ISO8601 ),
			'fileFormat'	=> $this->image->file_type,
			'keywords'		=> $this->image->tags(),
			'author'		=> array(
				'@type'		=> 'Person',
				'name'		=> \IPS\Member::load( $this->image->member_id )->name,
				'image'		=> \IPS\Member::load( $this->image->member_id )->get_photo()
			),
			'interactionStatistic'	=> array(
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $this->image->views
				)
			)
		);

		/* Do we have a real author? */
		if( $this->image->member_id )
		{
			\IPS\Output::i()->jsonLd['gallery']['author']['url']	= (string) \IPS\Member::load( $this->image->member_id )->url();
		}

		if ( $this->image->container()->allow_comments AND $this->image->directContainer()->allow_comments )
		{
			\IPS\Output::i()->jsonLd['gallery']['interactionStatistic'][] = array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/CommentAction",
				'userInteractionCount'	=> $this->image->mapped('num_comments')
			);

			\IPS\Output::i()->jsonLd['gallery']['commentCount'] = $this->image->mapped('num_comments');
		}

		if ( $this->image->container()->allow_reviews AND $this->image->directContainer()->allow_reviews )
		{
			\IPS\Output::i()->jsonLd['gallery']['interactionStatistic'][] = array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/ReviewAction",
				'userInteractionCount'	=> $this->image->mapped('num_reviews')
			);

			\IPS\Output::i()->jsonLd['gallery']['aggregateRating'] = array(
				'@type'			=> 'AggregateRating',
				'ratingValue'	=> $this->image->averageReviewRating(),
				'reviewCount'	=> $this->image->reviews,
				'bestRating'	=> \IPS\Settings::i()->reviews_rating_out_of
			);
		}

		if( $this->image->media )
		{
			if( $this->image->medium_file_name )
			{
				\IPS\Output::i()->jsonLd['gallery']['thumbnail']	= (string) \IPS\File::get( 'gallery_Images', $this->image->medium_file_name )->url;
				\IPS\Output::i()->jsonLd['gallery']['thumbnailUrl']	= (string) \IPS\File::get( 'gallery_Images', $this->image->medium_file_name )->url;
			}

			\IPS\Output::i()->jsonLd['gallery']['contentSize'] = (string) \IPS\File::get( 'gallery_Images', $this->image->original_file_name )->filesize();
		}
		else
		{
			try
			{
				$mediumFile = \IPS\File::get( 'gallery_Images', $this->image->medium_file_name );
				$dimensions = $mediumFile->getImageDimensions();

				\IPS\Output::i()->jsonLd['gallery']['artMedium']	= 'Digital';
				\IPS\Output::i()->jsonLd['gallery']['width'] 		= $dimensions[0];
				\IPS\Output::i()->jsonLd['gallery']['height'] 		= $dimensions[1];
				\IPS\Output::i()->jsonLd['gallery']['image']		= array(
					'@type'		=> 'ImageObject',
					'url'		=> (string) $mediumFile->url,
					'caption'	=> $this->image->mapped('title'),
					'thumbnail'	=> (string) \IPS\File::get( 'gallery_Images', $this->image->thumb_file_name )->url,
					'width'		=> $dimensions[0],
					'height'	=> $dimensions[1],
				);

				if( count( $this->image->metadata ) )
				{
					\IPS\Output::i()->jsonLd['gallery']['image']['exifData'] = array();

					foreach( $this->image->metadata as $k => $v )
					{
						\IPS\Output::i()->jsonLd['gallery']['image']['exifData'][] = array(
							'@type'		=> 'PropertyValue',
							'name'		=> $k, 
							'value'		=> $v
						);
					}
				}
				\IPS\Output::i()->jsonLd['gallery']['thumbnailUrl']	= (string) \IPS\File::get( 'gallery_Images', $this->image->thumb_file_name )->url;
			}
			/* File doesn't exist */
			catch ( \RuntimeException $e ){}
		}

		/* Display */
		if( isset( \IPS\Request::i()->imageSize ) )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'gallery' ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->imageSizes( $this->image, $this->image->sizes() );
		}
		elseif( \IPS\Request::i()->isAjax() && isset( \IPS\Request::i()->browse ) )
		{
			$return = array(
				'title' => htmlspecialchars( $this->image->mapped('title'), ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE ),
				'image' => \IPS\Theme::i()->getTemplate( 'view' )->imageFrame( $this->image, $slider, $this->image->nextItem(), $this->image->prevItem() ),
				'info' => \IPS\Theme::i()->getTemplate( 'view' )->imageInfo( $this->image, $slider, $this->image->nextItem(), $this->image->prevItem() ),
				'slider' => \IPS\Theme::i()->getTemplate( 'view' )->imageSlider( $this->image, $slider, $this->image->nextItem(), $this->image->prevItem(), $hasNext, $hasPrev )
			);

			if( $this->image->directContainer()->allow_comments )
			{
				$return['comments'] = $commentsAndReviews;
			}

			\IPS\Output::i()->json( $return );
		}
		/* Switching comments only */
		elseif( \IPS\Request::i()->isAjax() AND !isset( \IPS\Request::i()->rating_submitted ) )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		else
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, array( \IPS\Http\Url::internal( 'applications/gallery/interface/videojs/video-js.min.css', 'none' ) ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'gallery' ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->image( $this->image, $slider, $this->image->nextItem(), $this->image->prevItem(), $hasNext, $hasPrev, $commentsAndReviews );
		}
	}

	/**
	 * Download the full size image
	 *
	 * @return	void
	 */
	protected function download()
	{
		try
		{
			/* Get file and data */
			$size		= ( isset( \IPS\Request::i()->imageSize ) AND \IPS\Request::i()->imageSize AND \IPS\Request::i()->imageSize != 'large' ) ? \IPS\Request::i()->imageSize . "_file_name" : "masked_file_name";
			$file		= \IPS\File::get( 'gallery_Images', $this->image->$size );

			$headers	= array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'download', $file->originalFilename ), "X-Content-Type-Options" => "nosniff" ) );

			/* Send headers and print file */
			\IPS\Output::i()->sendStatusCodeHeader( 200 );
			\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( $file->originalFilename ) . ";charset=UTF-8" );

			foreach( $headers as $key => $header )
			{
				\IPS\Output::i()->sendHeader( $key . ': ' . $header );
			}
			\IPS\Output::i()->sendHeader( "Content-Length: " . $file->filesize() );

			$file->printFile();
			exit;
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->sendOutput( '', 404 );
		}
	}

	/**
	 * View all of the metadata for this image
	 *
	 * @return	void
	 */
	protected function metadata()
	{
		/* Set navigation and title */
		$this->_setBreadcrumbAndTitle( $this->image );

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'gallery_metadata', FALSE, array( 'sprintf' => $this->image->caption ) );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'view' )->metadata( $this->image );
	}

	/**
	 * Set this image as a cover photo
	 *
	 * @return	void
	 */
	protected function cover()
	{
		if( ( !$this->image->album_id OR !$this->image->canEdit() ) AND !\IPS\gallery\Image::modPermission( 'edit', NULL, $this->image->container() ) )
		{
			\IPS\Output::i()->error( 'node_error', '2G188/5', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		$lang = '';

		if( ( $this->image->album_id AND $this->image->canEdit() ) && ( \IPS\Request::i()->set == 'album' or \IPS\Request::i()->set == 'both' ) )
		{
			$this->image->directContainer()->cover_img_id	= $this->image->id;
			$this->image->directContainer()->save();

			$lang = \IPS\Member::loggedIn()->language()->addToStack('set_as_album_done');
		}

		if( ( \IPS\gallery\Image::modPermission( 'edit', NULL, $this->image->container() ) ) && ( \IPS\Request::i()->set == 'category' or \IPS\Request::i()->set == 'both' ) )
		{
			$this->image->container()->cover_img_id	= $this->image->id;
			$this->image->container()->save();

			if( $lang )
			{
				$lang = \IPS\Member::loggedIn()->language()->addToStack('set_as_both_done');
			}
			else
			{
				$lang = \IPS\Member::loggedIn()->language()->addToStack('set_as_category_done');
			} 
		}

		/* Redirect back to image */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'message' => $lang ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->image->url() );	
		}		
	}

	/**
	 * Rotate image
	 *
	 * @return	void
	 */
	protected function rotate()
	{
		/* Check permission */
		if( !$this->image->canEdit() )
		{
			\IPS\Output::i()->error( 'node_error', '2G188/3', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		/* Determine angle to rotate */
		if( \IPS\Request::i()->direction == 'right' )
		{
			$angle = 90;
		}
		else
		{
			$angle = 270;
		}

		/* Rotate the image and rebuild thumbnails */
		$file	= \IPS\File::get( 'gallery_Images', $this->image->original_file_name );
		$image	= \IPS\Image::create( $file->contents() );
		$image->rotate( $angle );
		$file->replace( (string) $image );
		$this->image->buildThumbnails( $file );
		$this->image->original_file_name = (string) $file;
		$this->image->save();

		/* Respond or redirect */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->image->url() );
		}
	}

	/**
	 * Change Author
	 *
	 * @return	void
	 */
	public function changeAuthor()
	{
		/* Permission check */
		if ( !$this->image->canChangeAuthor() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2G188/6', 403, '' );
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'author', NULL, TRUE ) );
		$form->class .= 'ipsForm_vertical';

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$this->image->member_id = $values['author']->member_id;
			$this->image->save();
			
			\IPS\Output::i()->redirect( $this->image->url() );
		}
		
		/* Display form */
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}

	/**
	 * Set this image as a profile image
	 *
	 * @return	void
	 */
	public function setAsPhoto()
	{
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2G188/9', 403, '' );
		}

		/* Only images... */
		if ( $this->image->media )
		{
			\IPS\Output::i()->error( 'no_photo_for_media', '2G188/A', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		/* Update profile photo */
		$file		= \IPS\File::get( 'gallery_Images', $this->image->medium_file_name );
		$image = \IPS\Image::create( $file->contents() );
		$photo = \IPS\File::create( 'core_Profile', $file->filename, (string) $image );

		\IPS\Member::loggedIn()->pp_main_photo = (string) $photo;
		\IPS\Member::loggedIn()->pp_thumb_photo = (string) $photo->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE );
		\IPS\Member::loggedIn()->pp_photo_type = "custom";
		\IPS\Member::loggedIn()->photo_last_update = time();
		\IPS\Member::loggedIn()->save();

		/* Redirect back to image */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'message' => \IPS\Member::loggedIn()->language()->addToStack('set_as_profile_photo') ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->image->url() );	
		}
	}

	/**
	 * Move
	 *
	 * @return	void
	 * @note	Overridden so we can show an album selector as well
	 */
	protected function move()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMove() )
			{
				throw new \DomainException;
			}
			
			$form = new \IPS\Helpers\Form( 'form', 'move' );
			$form->class = 'ipsForm_vertical';
			if ( \IPS\gallery\Category::canOnAny('add') and \IPS\gallery\Album::canOnAny('add') )
			{
				$options = array( 'category' => 'image_category', 'album' => 'image_album' );
				$toggles = array( 'category' => array( 'move_to_category' ), 'album' => array( 'move_to_album' ) );
				$extraFields = array();
				
				if ( \IPS\gallery\Image::modPermission( 'edit', NULL, NULL ) and \IPS\Db::i()->select( 'COUNT(*)', 'gallery_categories', array( array( 'category_allow_albums=1' ), array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . \IPS\gallery\Category::$permissionMap['add'], \IPS\Member::loggedIn()->groups ) . ' OR ' . 'core_permission_index.perm_' . \IPS\gallery\Category::$permissionMap['add'] . '=? )', '*' ) ) )->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . \IPS\gallery\Category::$databaseTable . "." . \IPS\gallery\Category::$databasePrefix . \IPS\gallery\Category::$databaseColumnId, \IPS\gallery\Category::$permApp, \IPS\gallery\Category::$permType ) )->first() )
				{
					$options['new_album'] = 'move_to_new_album';

					foreach ( \IPS\gallery\Album::formFields( NULL, TRUE, \IPS\Request::i()->move_to === 'new_album' ) as $field )
					{
						if ( !$field->htmlId )
						{
							$field->htmlId = $field->name . '_id';
						}
						$toggles['new_album'][] = $field->htmlId;
						
						$extraFields[] = $field;
					}
				}
				
				$form->add( new \IPS\Helpers\Form\Radio( 'move_to', NULL, TRUE, array( 'options' => $options, 'toggles' => $toggles ) ) );
				foreach ( $extraFields as $field )
				{
					$form->add( $field );
				}
			}

			$currentContainer = $item->container();
			$form->add( new \IPS\Helpers\Form\Node( 'move_to_category', NULL, NULL, array( 
				'clubs'				=> true, 
				'class'				=> 'IPS\\gallery\\Category', 
				'permissionCheck'	=> function( $node ) use ( $currentContainer )
				{
					/* If the image is in the same category already, we can't move it there */
					if( $currentContainer instanceof \IPS\gallery\Category and $currentContainer->id == $node->id )
					{
						return false;
					}

					/* If the category is a club, check mod permissions appropriately */
					try
					{
						/* If the item is in a club, only allow moving to other clubs that you moderate */
						if ( $currentContainer and \IPS\IPS::classUsesTrait( $currentContainer, 'IPS\Content\ClubContainer' ) and $currentContainer->club()  )
						{
							return \IPS\gallery\Image::modPermission( 'move', \IPS\Member::loggedIn(), $node ) and $node->can( 'add' ) ;
						}
					}
					catch( \OutOfBoundsException $e ) { }

					/* Can we add in this category? */
					if ( $node->can( 'add' ) )
					{
						return true;
					}
					
					return false;
				}
			), function( $val ) {
				if ( !$val and isset( \IPS\Request::i()->move_to ) and \IPS\Request::i()->move_to == 'category' )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, NULL, 'move_to_category' ) );

			$form->add( new \IPS\Helpers\Form\Node( 'move_to_album', NULL, NULL, array( 
				'class' 				=> 'IPS\\gallery\\Album', 
				'permissionCheck' 		=> function( $node ) use ( $currentContainer )
				{
					/* If the image is in the same album already, we can't move it there */
					if( $currentContainer instanceof \IPS\gallery\Album and $currentContainer->id == $node->id )
					{
						return false;
					}

					/* Do we have permission to add? */
					if( !$node->can( 'add' ) )
					{
						return false;
					}

					/* Have we hit an images per album limit? */
					if( $node->owner()->group['g_img_album_limit'] AND ( $node->count_imgs + $node->count_imgs_hidden ) >= $node->owner()->group['g_img_album_limit'] )
					{
						return false;
					}
					
					return true;
				}, 
				'forceOwner'			=> $item->author()
			), function( $val ) {
				if ( !$val and isset( \IPS\Request::i()->move_to ) and \IPS\Request::i()->move_to == 'album' )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, NULL, 'move_to_album' ) );
			
			if ( $values = $form->values() )
			{
				if ( isset( $values['move_to'] ) )
				{
					if ( $values['move_to'] == 'new_album' )
					{
						$albumValues = $values;
						unset( $albumValues['move_to'] );
						unset( $albumValues['move_to_category'] );
						unset( $albumValues['move_to_album'] );
						
						$target = new \IPS\gallery\Album;
						$target->saveForm( $target->formatFormValues( $albumValues ) );
						$target->save();
					}
					else
					{						
						$target = ( \IPS\Request::i()->move_to == 'category' ) ? $values['move_to_category'] : $values['move_to_album'];
					}
				}
				else
				{
					$target = isset( $values['move_to_category'] ) ? $values['move_to_category'] : $values['move_to_album'];
				}

				$item->move( $target, FALSE );
				\IPS\Output::i()->redirect( $item->url() );
			}
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2G188/B', 403, '' );
		}
	}

	/**
	 * Set the breadcrumb and title
	 *
	 * @param	\IPS\Content\Item	$item	Content item
	 * @param	bool				$link	Link the content item element in the breadcrumb
	 * @return	void
	 */
	protected function _setBreadcrumbAndTitle( $item, $link=TRUE )
	{
		$container	= NULL;
		try
		{
			$container = $this->image->container();
			
			if ( $club = $container->club() )
			{
				\IPS\core\FrontNavigation::$clubTabActive = TRUE;
				\IPS\Output::i()->breadcrumb = array();
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
				\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
				
				if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
				{
					\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $container, 'sidebar' );
				}
			}
			else
			{
				foreach ( $container->parents() as $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }

		/* Add album */
		if( $this->image->album_id )
		{
			\IPS\Output::i()->breadcrumb[] = array( $this->image->directContainer()->url(), $this->image->directContainer()->_title );
		}

		\IPS\Output::i()->breadcrumb[] = array( $link ? $this->image->url() : NULL, $this->image->mapped('title') );
		
		$title = ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $this->image->mapped('title'), intval( \IPS\Request::i()->page ) ) ) ) : $this->image->mapped('title');
		\IPS\Output::i()->title = $container ? ( $title . ' - ' . $container->_title ) : $title;
	}
}