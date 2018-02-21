<?php
/**
 * @brief		Gallery Submission
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
 * Gallery Submission
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage addition of gallery images
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Permission check */
		\IPS\gallery\Image::canCreate( \IPS\Member::loggedIn(), NULL, TRUE );
		
		/* Init */
		$url = \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit', 'front', 'gallery_submit' );
		$steps = array(
			'choose_album'		=> array( $this, '_stepAlbum' ),
			'upload_images'		=> array( $this, '_stepUploadImages' ),
			'image_information'	=> array( $this, '_stepAddInfo' ),
			'process'			=> array( $this, '_stepProcess' )
		);
		$club = NULL;
		
		/* Initial data? */
		$initialData = array();
		if ( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category = \IPS\gallery\Category::loadAndCheckPerms( \IPS\Request::i()->category );
				$url = $url->setQueryString( 'category', $category->_id );
				$initialData['category'] = $category->_id;;
				if ( $club = $category->club() )
				{
					$initialData['album'] = NULL;
					
					if ( !\IPS\Member::loggedIn()->group['g_create_albums'] or !$category->allow_albums )
					{
						unset( $steps['choose_album'] );
					}
					
					\IPS\core\FrontNavigation::$clubTabActive = TRUE;
					\IPS\Output::i()->breadcrumb = array();
					\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
					\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
					\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
					
					if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
					{
						\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $category, 'sidebar' );
					}
				}
			}
			catch ( \OutOfRangeException $e ) {}
		}
		if ( isset( \IPS\Request::i()->album ) )
		{
			$initialData['album'] = \IPS\Request::i()->album;
		}
		
		/* Build wizard */
		$wizard = new \IPS\Helpers\Wizard( $steps, $url, TRUE, $initialData, FALSE, array( 'category', 'chosenCategory' ) );
		$wizard->template = array( \IPS\Theme::i()->getTemplate( 'submit' ), 'wizardWrapper' );
		
		/* Images have to be moderated? */
		if ( \IPS\gallery\Image::moderateNewItems( \IPS\Member::loggedIn() ) )
		{
			$wizard = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts ) . $wizard;
		}
		
		/* Club header */
		if ( $club and !\IPS\Request::i()->isAjax() )
		{
			$wizard =  \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $category ) . $wizard;
		}

		/* Set online user location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit', 'front', 'gallery_submit' ), array(), 'loc_gallery_adding_image' );

		/* Output */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'submit.css' ), \IPS\Theme::i()->css( 'gallery.css' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'submit_responsive.css', 'gallery', 'front' ), \IPS\Theme::i()->css( 'gallery_responsive.css', 'gallery', 'front' ) );
		}
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'gallery' ) );	
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('add_gallery_image');
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( ( \IPS\Member::loggedIn()->group['g_movies'] ) ? 'add_gallery_image_movies' : 'add_gallery_image' ) );
		\IPS\Output::i()->output = $wizard;
	}
	
	/**
	 * Wizard step: Choose a category and an album
	 *
	 * @param	array	$data	The current wizard data
	 * @return	string|array
	 */
	public function _stepAlbum( $data )
	{
		/* Have we chosen a category? */
		$category = NULL;

		if ( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category = \IPS\gallery\Category::loadAndCheckPerms( \IPS\Request::i()->category, 'add' );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		if ( isset( \IPS\Request::i()->chosenCategory ) )
		{
			try
			{
				$category = \IPS\gallery\Category::loadAndCheckPerms( \IPS\Request::i()->chosenCategory, 'add' );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* If we haven't, show a form */
		if ( !$category )
		{
			$chooseCategoryForm = new \IPS\Helpers\Form( 'choose_category', 'continue' );
			$chooseCategoryForm->add( new \IPS\Helpers\Form\Node( 'image_category', isset( $data['category'] ) ? $data['category'] : NULL, TRUE, array(
				'url'					=> \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit', 'front', 'gallery_submit' ),
				'class'					=> 'IPS\gallery\Category',
				'permissionCheck'		=> 'add',
			) ) );
			if ( $chooseCategoryFormValues = $chooseCategoryForm->values() )
			{
				$category = $chooseCategoryFormValues['image_category'];
			}
			else
			{
				return $chooseCategoryForm->customTemplate( array( \IPS\Theme::i()->getTemplate('submit'), 'chooseCategory' ) );
			}
		}
		
		/* If we have chosen no album, we can just continue */
		if ( isset( \IPS\Request::i()->noAlbum ) )
		{
			return array( 'category' => $category->_id, 'album' => NULL );
		}
					
		/* Can we create an album in this category? */
		$canCreateAlbum = ( $category->allow_albums and \IPS\Member::loggedIn()->group['g_create_albums'] );
		$maximumAlbums = \IPS\Member::loggedIn()->group['g_album_limit'];
		$currentAlbumCount = count( \IPS\gallery\Album::loadByOwner() );

		/* If we can, build a form */
		$createAlbumForm = NULL;
		if ( $canCreateAlbum and ( !$maximumAlbums or $maximumAlbums > $currentAlbumCount ) )
		{
			/* Build the create form... */
			$createAlbumForm = new \IPS\Helpers\Form( 'new_album', 'create_new_album' );
			$createAlbumForm->class .= 'ipsForm_vertical';
			$createAlbumForm->hiddenValues['chosenCategory'] = $category->_id;

			$album	= new \IPS\gallery\Album;
			$album->form( $createAlbumForm );
			unset( $createAlbumForm->elements['']['album_category'] );
			
			/* And when we submit it, create an album... */
			if ( $createAlbumFormValues = $createAlbumForm->values() )
			{
				unset( $createAlbumFormValues['chosenCategory'] );
				$createAlbumFormValues['album_category'] = $category;
				$album->saveForm( $album->formatFormValues( $createAlbumFormValues ) );
				return array( 'category' => $category->_id, 'album' => $album->_id );
			}
			
			/* Otherwise, display it*/
			$createAlbumForm = $createAlbumForm->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'gallery' ) ), 'createAlbum' ) );
		}
		
		/* Can we choose an existing album? */
		$existingAlbumForm = NULL;
		$albumsInCategory = \IPS\gallery\Album::loadByOwner( NULL, array( array( 'album_category_id=?', $category->id ) ) );
		if ( count( $albumsInCategory ) )
		{
			/* Build the existing album form... */
			$existingAlbumForm = new \IPS\Helpers\Form( 'choose_album', 'choose_selected_album' );
			$existingAlbumForm->class .= 'ipsForm_vertical';
			$existingAlbumForm->hiddenValues['chosenCategory'] = $category->_id;
			$albums = array();
			foreach( $albumsInCategory as $id => $album )
			{
				$albums[ $album->_id ] = $album->_title;
			}
			$existingAlbumForm->add( new \IPS\Helpers\Form\Radio( 'existing_album', isset( $data['album'] ) ? $data['album'] : NULL, FALSE, array( 'options' => $albums, 'noDefault' => TRUE ), NULL, NULL, NULL, 'set_album_owner' ) );
			
			/* When we submit it, we can continue... */
			if ( $existingAlbumFormValues = $existingAlbumForm->values() )
			{
				return array( 'category' => $category->_id, 'album' => $existingAlbumFormValues['existing_album'] );
			}
			
			/* Otherwise, display it */
			$existingAlbumForm = \IPS\Theme::i()->getTemplate( 'submit' )->existingAlbums( $category, $existingAlbumForm->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'gallery' ) ), 'existingAlbumForm' ), $category ) );
		}
		
		/* If there's nothing we can do, we can just continue */
		if ( !$canCreateAlbum )
		{
			return array( 'category' => $category->_id, 'album' => 0 );
		}
		
		/* Otherwise, ask the user what they want to do */
		else
		{
			$output = \IPS\Theme::i()->getTemplate('submit')->chooseAlbum( $category, $createAlbumForm, $canCreateAlbum, $maximumAlbums, $existingAlbumForm );
			if ( \IPS\Request::i()->isAjax() AND !isset( \IPS\Request::i()->_new ) )
			{
				\IPS\Output::i()->json( $output );
			}
			else
			{
				return $output;
			}
		}
	}
	
	/**
	 * Wizard step: Upload Images
	 *
	 * @param	array	$data	The current wizard data
	 * @return	string|array
	 */
	public function _stepUploadImages( $data )
	{
		/* Load category and album */
		$category = \IPS\gallery\Category::loadAndCheckPerms( $data['category'], 'add' );
		$album = NULL;
		if ( $data['album'] )
		{
			try
			{
				$album = \IPS\gallery\Album::loadAndCheckPerms( $data['album'], 'add' );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* How many images are allowed? */
		$maxNumberOfImages = NULL;
		if ( $album and \IPS\Member::loggedIn()->group['g_img_album_limit'] )
		{
			$maxNumberOfImages = \IPS\Member::loggedIn()->group['g_img_album_limit'] - ( $album->count_imgs + $album->count_imgs_hidden );
		}
		
		/* Init form */
		$url = \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit&_step=upload_images', 'front', 'gallery_submit' );
		if ( isset( \IPS\Request::i()->category ) )
		{
			$url = $url->setQueryString( 'category', \IPS\Request::i()->category );
		}
		$form = new \IPS\Helpers\Form( 'upload_images', 'continue', $url );
		$form->class = 'ipsForm_vertical';

		/* Get any existing */
		$currentValue = array();
		if ( isset( $data['images'] ) )
		{
			foreach ( $data['images'] as $url )
			{
				$currentValue[] = \IPS\File::get( 'gallery_Images', $url );
			}
		}
		
		/* Add upload field */
		$maxFileSizes = array();
		$options = array(
			'storageExtension'	=> 'gallery_Images',
			'image'				=> TRUE,
			'multiple'			=> TRUE,
			'minimize'			=> FALSE,
			'template'			=> "core.attachments.imageItem",
			'maxFiles'			=> 100 // @todo: Look for a better way of storing temporary gallery data so we can get rid of this arbitrary limit
		);
		if ( \IPS\Member::loggedIn()->group['g_max_upload'] )
		{
			$maxFileSizes['image'] = \IPS\Member::loggedIn()->group['g_max_upload'] / 1024;
		}
		if ( \IPS\Member::loggedIn()->group['g_movies'] )
		{
			$options['image'] = NULL;
			$options['allowedFileTypes'] = array_merge( \IPS\Image::$imageExtensions, array( 'flv', 'f4v', 'wmv', 'mpg', 'mpeg', 'mp4', 'mkv', 'm4a', 'm4v', '3gp', 'mov', 'avi', 'webm', 'ogg', 'ogv' ) );
			if ( \IPS\Member::loggedIn()->group['g_movie_size'] )
			{
				$maxFileSizes['movie'] = \IPS\Member::loggedIn()->group['g_movie_size'] / 1024;
			}
		}
		if ( count( $maxFileSizes ) )
		{
			$options['maxFileSize'] = max( $maxFileSizes );
		}
		$form->add( new \IPS\Helpers\Form\Upload( 'images', $currentValue, TRUE, $options, function( $val ) use ( $maxNumberOfImages, $maxFileSizes ) {
			if ( $maxNumberOfImages !== NULL and count( $val ) > $maxNumberOfImages )
			{
				if ( $maxNumberOfImages < 1 )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'gallery_images_no_more' ) );
				}
				else
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'gallery_images_too_many', FALSE, array( 'pluralize' => array( $maxNumberOfImages ) ) ) );
				}
			}
			if ( count( $val ) > 100 ) // @todo remove this limit: Just to prevent the session data exceeding it's storage size
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'gallery_images_too_many_at_once' ) );
			}
			foreach ( $val as $file )
			{
				$ext = mb_substr( $file->filename, ( mb_strrpos( $file->filename, '.' ) + 1 ) );
				if ( in_array( $ext, \IPS\Image::$imageExtensions ) )
				{
					/* The size was saved as kb, then divided by 1024 above to figure out how many MB to allow. So now we have '2' for 2MB for instance, so we need
						to multiply that by 1024*1024 in order to get the byte size again */
					if ( count( $maxFileSizes ) == 2 and $file->filesize() > ( $maxFileSizes['image'] * 1048576 ) )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'upload_image_too_big', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $maxFileSizes['image'] * 1048576 ) ) ) ) );
					}
				}
				elseif ( count( $maxFileSizes ) == 2 and $file->filesize() > ( $maxFileSizes['movie'] * 1048576 ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'upload_movie_too_big', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $maxFileSizes['movie'] * 1048576 ) ) ) ) );
				}
			}
		} ) );
		
		/* Process submission */
		if ( $values = $form->values() )
		{
			/* Get any records we had before in case we need to delete them */
			$existing = iterator_to_array( \IPS\Db::i()->select( '*', 'gallery_images_uploads', array( 'upload_session=?', session_id() ) )->setKeyField( 'upload_location' ) );
			
			/* Loop through the values we have */
			$images = array();
			$inserts = array();
			foreach ( $values['images'] as $image )
			{
				$images[] = (string) $image;

				if ( !isset( $existing[ (string) $image ] ) )
				{
					$inserts[] = array(
						'upload_session'	=> session_id(),
						'upload_member_id'	=> (int) \IPS\Member::loggedIn()->member_id,
						'upload_location'	=> (string) $image,
						'upload_file_name'	=> $image->originalFilename,
						'upload_date'		=> time(),
					);
				}

				unset( $existing[ (string) $image ] );
			}
			
			/* Insert them into the database */
			if( count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'gallery_images_uploads', $inserts );
			}

			/* Delete any that we don't have any more */
			foreach ( $existing as $location => $file )
			{
				try
				{
					\IPS\File::get( 'gallery_Images', $location )->delete();
				}
				catch ( \Exception $e ) { }
				
				\IPS\Db::i()->delete( 'gallery_images_uploads', array( 'upload_session=? and upload_location=?', $file['upload_session'], $file['upload_location'] ) );
			}

			/* Return */
			return array_merge( $data, array( 'images' => $images ) );
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'submit' )->uploadImages( $form, $category );
	}
	
	/**
	 * Wizard step: Add Information
	 *
	 * @param	array	$data	The current wizard data
	 * @return	string|array
	 */
	public function _stepAddInfo( $data )
	{
		/* Load category and album */
		$category = \IPS\gallery\Category::loadAndCheckPerms( $data['category'], 'add' );
		$album = NULL;
		if ( $data['album'] )
		{
			try
			{
				$album = \IPS\gallery\Album::loadAndCheckPerms( $data['album'], 'add' );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* Get any records we had before so we can mark them done */
		$existing = iterator_to_array( \IPS\Db::i()->select( '*', 'gallery_images_uploads', array( 'upload_session=?', session_id() ) )->setKeyField( 'upload_location' ) );
		
		/* Get images */
		$images = array();
		if ( isset( $data['images'] ) )
		{
			foreach ( $data['images'] as $url )
			{
				$image = \IPS\File::get( 'gallery_Images', $url );
				$image->done = ( isset( $existing[ $url ] ) and $existing[ $url ]['upload_data'] );
				$image->movie = !in_array( mb_strtolower( mb_substr( $image->filename, ( mb_strrpos( $image->filename, '.' ) + 1 ) ) ), \IPS\Image::$imageExtensions );
				$image->tempId = isset( $existing[ $url ] ) ? $existing[ $url ]['upload_unique_id'] : 0;
				$image->originalFilename = isset( $existing[ $url ] ) ? $existing[ $url ]['upload_file_name'] : $image->originalFilename;
				$images[] = $image;
			}
		}
				
		/* What image are we on? */
		$currentlyEditing = NULL;
		if ( isset( \IPS\Request::i()->edit ) and array_key_exists( \IPS\Request::i()->edit, $images ) )
		{
			$currentlyEditing = \IPS\Request::i()->edit;
		}
		else
		{
			foreach ( $images as $i => $image )
			{
				if ( !$image->done )
				{
					$currentlyEditing = $i;
					break;
				}
			}
		}
		
		/* If we have nothing to edit, just go back to the first one */
		if ( $currentlyEditing === NULL )
		{
			$currentlyEditing = 0;
		}
		
		/* Get the form for the image being edited */
		$form = $this->_editImageForm( $images, $currentlyEditing, $category );
		$values = $form->values( TRUE );
		if ( $values )
		{
			/* Save that data */
			\IPS\Db::i()->update( 'gallery_images_uploads', array( 'upload_data' => json_encode( $values ) ), array( 'upload_location=? AND upload_session=?', (string) $images[ $currentlyEditing ], session_id() ) );
			$images[ $currentlyEditing ]->done = TRUE;

			/* Try and get the previous or the next image, depending on what button was pressed */
			$wasEditing = $currentlyEditing;
			$currentlyEditing = NULL;
			if ( isset( \IPS\Request::i()->submitButton ) and \IPS\Request::i()->submitButton == 'prev' )
			{
				if( isset( $images[ $wasEditing - 1 ] ) )
				{
					$currentlyEditing = $wasEditing - 1;
				}
			}
			elseif ( isset( \IPS\Request::i()->submitButton ) and \IPS\Request::i()->submitButton == 'next' )
			{
				if( isset( $images[ $wasEditing + 1 ] ) )
				{
					$currentlyEditing = $wasEditing + 1;
				}
			}
			else
			{
				$currentlyEditing = $wasEditing;
			}
						
			/* If we couldn't find one, just grab any image which hasn't been done */
			if ( $currentlyEditing === NULL )
			{
				foreach ( $images as $i => $image )
				{
					if ( !$image->done )
					{
						$currentlyEditing = $i;
						break;
					}
				}
			}
			
			/* Otherwise we'll display this form */
			$form = $this->_editImageForm( $images, $currentlyEditing, $category );
			
			/* Do we have existing information to add? */
			if ( isset( $images[ $currentlyEditing ] ) and isset( $existing[ (string) $images[ $currentlyEditing ] ] ) and $existing[ (string) $images[ $currentlyEditing ] ]['upload_data'] )
			{
				$data = json_decode( $existing[ (string) $images[ $currentlyEditing ] ]['upload_data'], TRUE );
				foreach( $form->elements[''] as $key => &$elem )
				{
					foreach( array( 'title', 'tags', 'description', 'credit_info', 'copyright' ) as $field )
					{
						$lookUp = 'filedata_' . $currentlyEditing . '_image_' . $field;
						if ( $key == $lookUp and $data[ $lookUp ] )
						{
							$elem->value = $data[ $lookUp ];
						}
					}
				}
			}
			
			if( \IPS\Request::i()->isAjax() )
			{
				/* If we've done them all, tell the user they're ready to submit */
				if ( $currentlyEditing === NULL )
				{
					$formHTML = \IPS\Theme::i()->getTemplate( 'submit' )->imageInformationDone( $images, $category, $album );
				}
				else
				{
					$formHTML = \IPS\Theme::i()->getTemplate( 'submit' )->imageInformationWrapper( $form, count( $images ), $currentlyEditing );
				}

				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $formHTML );

				$output = array( 
					'done' => ( $currentlyEditing === NULL ),
					'form' =>  $formHTML,
					'index' => ( $currentlyEditing === NULL ) ? -1 : $currentlyEditing,
					'success' => TRUE
				);

				\IPS\Output::i()->sendOutput( json_encode( $output ), 200, 'application/json' );
			}
		}
		elseif( \IPS\Request::i()->isAjax() )
		{
			/* If this form was submitted but didn't validate, we need to output JSON for the same image again */
			$formName = 'image_information_' . $currentlyEditing . '_submitted';
			
			if ( $currentlyEditing !== NULL )
			{
				/* Do we have existing information to add? */
				if ( isset( $images[ $currentlyEditing ] ) and isset( $existing[ (string) $images[ $currentlyEditing ] ] ) and $existing[ (string) $images[ $currentlyEditing ] ]['upload_data'] )
				{
					$data = json_decode( $existing[ (string) $images[ $currentlyEditing ] ]['upload_data'], TRUE );
					foreach( $form->elements[''] as $key => &$elem )
					{
						foreach( array( 'title', 'tags', 'description', 'credit_info', 'copyright' ) as $field )
						{
							$lookUp = 'filedata_' . $currentlyEditing . '_image_' . $field;
							if ( $key == $lookUp and $data[ $lookUp ] )
							{
								$elem->value = $data[ $lookUp ];
							}
						}
					}
				}
			}
			
			if( isset( \IPS\Request::i()->$formName ) and \IPS\Request::i()->csrfKey === \IPS\Session::i()->csrfKey )
			{
				$formHTML = \IPS\Theme::i()->getTemplate( 'submit' )->imageInformationWrapper( $form, count( $images ), $currentlyEditing );

				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $formHTML );

				$output = array( 
					'done' => ( $currentlyEditing === NULL ),
					'form' =>  $formHTML,
					'index' => ( $currentlyEditing === NULL ) ? -1 : $currentlyEditing,
					'success' => FALSE
				);

				\IPS\Output::i()->sendOutput( json_encode( $output ), 200, 'application/json' );
			}
		}
		
		/* Get the form which applies to all images - submitting this is 'finishing' */
		$url = \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit&_step=image_information', 'front', 'gallery_submit' );
		if ( isset( \IPS\Request::i()->category ) )
		{
			$url = $url->setQueryString( 'category', \IPS\Request::i()->category );
		}
		$allImagesForm = new \IPS\Helpers\Form( 'all_images_form', 'submit', $url );
		$allImagesForm->add( new \IPS\Helpers\Form\TextArea( 'image_credit_info', NULL, FALSE ) );
		$allImagesForm->add( new \IPS\Helpers\Form\Text( 'image_copyright', NULL, FALSE, array( 'maxLength' => 255 ) ) );
		$allImagesForm->add( new \IPS\Helpers\Form\YesNo( 'image_auto_follow', (bool) \IPS\Member::loggedIn()->auto_follow['content'], FALSE, array(), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack( 'image_auto_follow_suffix' ) ) );
		if( $values = $allImagesForm->values( TRUE ) )
		{
			/* Are we copying image details? */
			if( isset( \IPS\Request::i()->copyImageDetails ) AND \IPS\Request::i()->copyImageDetails == 1 )
			{
				/* Grab the first details we can find */
				try
				{
					$details = \IPS\Db::i()->select( 'upload_data', 'gallery_images_uploads', array( 'upload_data IS NOT NULL AND upload_session=?', session_id() ) )->first();
					\IPS\Db::i()->update( 'gallery_images_uploads', array( 'upload_data' => $details ), array( 'upload_data IS NULL AND upload_session=?', session_id() ) );
				}
				catch( \UnderflowException $e ){}
			}
			else
			{
				$data['copyImageDetails'] = ( isset( $data['copyImageDetails'] ) ) ? $data['copyImageDetails'] : false;
			}

			return array_merge( $data, $values );
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'submit' )->imageInformation( $images, $currentlyEditing, \IPS\Theme::i()->getTemplate( 'submit' )->imageInformationWrapper( $form, count( $images ), $currentlyEditing ), $allImagesForm->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'gallery' ) ), 'submitBar' ) ) );
	}
	
	/**
	 * Get form for an image
	 *
	 * @param	array					$images		The images array
	 * @param	int						$id			The ID number of the image
	 * @param	\IPS\gallery\Category	$category	The category we're submitting to
	 * @return	\IPS\Helpers\Form|NULL
	 */
	protected function _editImageForm( $images, $currentlyEditing, $category )
	{
		if( $currentlyEditing === NULL )
		{
			$currentlyEditing = 0;
		}

		/* Initate form */
		$url = \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit&_step=image_information', 'front', 'gallery_submit' );
		if ( isset( \IPS\Request::i()->category ) )
		{
			$url = $url->setQueryString( 'category', \IPS\Request::i()->category );
		}
		$form	= new \IPS\Helpers\Form( 'image_information_' . $currentlyEditing, 'continue_next_image', $url );
		$form->hiddenValues['edit'] = $currentlyEditing;
		$form->class = 'ipsForm_vertical';
				
		/* Get the standard elements */
		foreach ( \IPS\gallery\Image::formElements( NULL, $category, $currentlyEditing, $images[ $currentlyEditing ]->tempId ) as $input )
		{
			/* Skip auto-follow for this individual image (shown for all uploads instead) */
			if ( $input->name == 'image_auto_follow' )
			{
				continue;
			}
						
			/* Set a value */
			if ( !$input->value )
			{
				if ( $input->name === 'image_title' )
				{
					$input->value = $images[ $currentlyEditing ]->originalFilename;
				}
			}
			
			/* We have to make the name unique so it doesn't carry between steps */
			\IPS\Member::loggedIn()->language()->words[ "filedata_{$currentlyEditing}_{$input->name}" ] = \IPS\Member::loggedIn()->language()->addToStack( $input->name, FALSE );
			$input->name = "filedata_{$currentlyEditing}_{$input->name}";
			
			/* Add it to the form */
			$form->add( $input );
		}
		
		/* Add a checkbox allowing the user to specify if a map should show */
		if( \IPS\Image::exifSupported() and !$images[ $currentlyEditing ]->movie )
		{
			$exif	= \IPS\Image::create( $images[ $currentlyEditing ]->contents() )->parseExif();
			if( count( $exif ) )
			{
				if( isset( $exif['GPS.GPSLatitudeRef'] ) && isset( $exif['GPS.GPSLatitude'] ) && isset( $exif['GPS.GPSLongitudeRef'] ) && isset( $exif['GPS.GPSLongitude'] ) )
				{
					\IPS\Member::loggedIn()->language()->words[ "filedata_{$currentlyEditing}_image_gps_show" ] = \IPS\Member::loggedIn()->language()->addToStack( 'image_gps_show', FALSE );
					\IPS\Member::loggedIn()->language()->words[ "filedata_{$currentlyEditing}_image_gps_show_desc" ] = \IPS\Member::loggedIn()->language()->addToStack( 'image_gps_show_desc', FALSE );
					$form->add( new \IPS\Helpers\Form\YesNo( "filedata_{$currentlyEditing}_image_gps_show", TRUE, FALSE ) );
				}
			}
		}
		
		/* If it's a movie, show the upload field */
		if ( $images[ $currentlyEditing ]->movie )
		{
			\IPS\Member::loggedIn()->language()->words[ "filedata_{$currentlyEditing}_image_thumbnail" ] = \IPS\Member::loggedIn()->language()->addToStack( 'image_thumbnail', FALSE );
			$form->add( new \IPS\Helpers\Form\Upload( "filedata_{$currentlyEditing}_image_thumbnail", NULL, FALSE, array( 
				'storageExtension'	=> 'gallery_Images', 
				'image'				=> TRUE,
				'maxFileSize'		=> \IPS\Member::loggedIn()->group['g_max_upload'] ? ( \IPS\Member::loggedIn()->group['g_max_upload'] / 1024 ) : NULL,
			) ) );
		}
		
		/* Return */
		return $form;
	}
	
	/**
	 * Wizard step: Process the saved data to create an album and save images
	 *
	 * @param	array	$data	The current wizard data
	 * @return	string|array
	 */
	public function _stepProcess( $data )
	{
		/* Load category and album */
		$category = \IPS\gallery\Category::loadAndCheckPerms( $data['category'], 'add' );
		$album = NULL;
		if ( $data['album'] )
		{
			try
			{
				$album = \IPS\gallery\Album::loadAndCheckPerms( $data['album'], 'add' );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* Process */
		$url = \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit&_step=process', 'front', 'gallery_submit' );
		if ( isset( \IPS\Request::i()->category ) )
		{
			$url = $url->setQueryString( 'category', \IPS\Request::i()->category );
		}

		$multiRedirect = (string) new \IPS\Helpers\MultipleRedirect( $url,
			/* Function to process each image */
			function( $offset ) use ( $data, $category, $album )
			{
				$offset = intval( $offset );
				
				$existing = \IPS\Db::i()->select( '*', 'gallery_images_uploads', array( 'upload_session=?', session_id() ), 'upload_unique_id', array( 0, 1 ) )->setKeyField( 'upload_location' );
				foreach( $existing as $location => $file )
				{
					/* Start with the basic data */
					$values = array( 'category' => $category->_id, 'imageLocation' => $location );
					if( $album )
					{
						$values['album'] = $album->_id;
					}
					
					/* Get the data from the row */
					$fileData = json_decode( $file['upload_data'], TRUE );
					if( count( $fileData ) )
					{
						foreach( $fileData as $k => $v )
						{
							$values[ preg_replace("/^filedata_[0-9]+_/i", '', $k ) ]	= $v;
						}	
					}
					if( isset( $values['image_tags'] ) AND $values['image_tags'] AND !is_array( $values['image_tags'] ) )
					{
						$values['image_tags']	= explode( ',', $values['image_tags'] );
					}
					
					/* If no title was saved, use the original file name */
					if( !isset( $values['image_title'] ) )
					{
						$values['image_title'] = $file['upload_file_name'];
					}

					/* In case we used the quick tag and copied details */
					$values['image_title'] = str_replace( '%n', $offset + 1, $values['image_title'] );
		
					/* Create image */
					if ( ( !isset( $values['image_copyright'] ) or !$values['image_copyright'] ) and $data['image_copyright'] )
					{
						$values['image_copyright'] = $data['image_copyright'];
					}
					if ( ( !isset( $values['image_credit_info'] ) or !$values['image_credit_info'] ) and $data['image_credit_info'] )
					{
						$values['image_credit_info'] = $data['image_credit_info'];
					}
					if ( $data['image_auto_follow'] )
					{
						$values['image_auto_follow'] = TRUE;
					}

					$image	= \IPS\gallery\Image::createFromForm( $values, $category, FALSE );
					$image->markRead();
					
					/* Delete that file */
					\IPS\Db::i()->delete( 'gallery_images_uploads', array( 'upload_unique_id=?', $file['upload_unique_id'] ) );

					/* Go to next */
					return array( ++$offset, \IPS\Member::loggedIn()->language()->addToStack('processing'), 100 / count( $data['images'] ) * $offset );
				}
				
				return NULL;
			},
			
			/* Function to call when done */
			function() use ( $data, $category, $album )
			{
				if ( count( $data['images'] ) === 1 )
				{
					/* If we are only sending one image, send a normal notification */
					$image = \IPS\gallery\Image::constructFromData( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_id DESC', 1 )->first() );
					if ( !$image->hidden() )
					{
						$image->sendNotifications();
					}
					else if( $image->hidden() !== -1 )
					{
						$image->sendUnapprovedNotification();
					}
					
					\IPS\Output::i()->redirect( \IPS\gallery\Image::constructFromData( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_id DESC', 1 )->first() )->url() );
				}
				else
				{
					if ( \IPS\Member::loggedIn()->moderateNewContent() OR \IPS\gallery\Image::moderateNewItems( \IPS\Member::loggedIn(), $category ) )
					{
						\IPS\gallery\Image::_sendUnapprovedNotifications( $category, $album );
					}
					else
					{
						\IPS\gallery\Image::_sendNotifications( $category, $album );
					}
					
					\IPS\Output::i()->redirect( $album ? $album->url() : $category->url() );
				}
			}
		
		);
		
		/* Display redirect */
		return \IPS\Theme::i()->getTemplate( 'submit' )->processing( $multiRedirect );	
	}

}