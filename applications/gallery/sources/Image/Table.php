<?php
/**
 * @brief		Custom table helper for gallery images to override move menu
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Apr 2014
 */

namespace IPS\gallery\Image;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom table helper for gallery images to override move menu
 */
class _Table extends \IPS\Helpers\Table\Content
{
	/**
	 * Constructor
	 *
	 * @param	array					$class				Database table
	 * @param	\IPS\Http\Url			$baseUrl			Base URL
	 * @param	array|null				$where				WHERE clause (To restrict to a node, use $container instead)
	 * @param	\IPS\Node\Model|NULL	$container			The container
	 * @param	bool|null				$includeHidden		Flag to pass to getItemsWithPermission() method for $includeHiddenContent, defaults to NULL
	 * @param	string|NULL				$permCheck			Permission key to check
	 * @return	void
	 */
	public function __construct( $class, \IPS\Http\Url $baseUrl, $where=NULL, \IPS\Node\Model $container=NULL, $includeHidden=\IPS\Content\Hideable::FILTER_AUTOMATIC, $permCheck='view', $honorPinned=TRUE )
	{
		/* Are we changing the thumbnail viewing size? */
		if( isset( \IPS\Request::i()->thumbnailSize ) )
		{
			\IPS\Session::i()->csrfCheck();

			\IPS\Request::i()->setCookie( 'thumbnailSize', \IPS\Request::i()->thumbnailSize, \IPS\DateTime::ts( time() )->add( new \DateInterval( 'P1Y' ) ) );

			/* Do a 303 redirect to prevent indexing of the CSRF link */
			\IPS\Output::i()->redirect( \IPS\Request::i()->url(), '', 303 );
		}

		return parent::__construct( $class, $baseUrl, $where, $container, $includeHidden, $permCheck, $honorPinned );
	}

	/**
	 * Get the form to move items
	 *
	 * @return string|array
	 */
	protected function getMoveForm()
	{
		$class = $this->class;
		$params = array();

		$currentContainer = $this->container;
		$form = new \IPS\Helpers\Form( 'form', 'move' );
		$form->add( new \IPS\Helpers\Form\Node( 'move_to_category', NULL, FALSE, array( 
			'clubs'					=> true,
			'class'					=> 'IPS\\gallery\\Category', 
			'url'					=> \IPS\Request::i()->url()->setQueryString( 'modaction', 'move' ),
			'permissionCheck' 		=> function( $node ) use ( $currentContainer, $class )
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
						return $class::modPermission( 'move', \IPS\Member::loggedIn(), $node ) and $node->can( 'add' ) ;
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
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Node( 'move_to_album', NULL, FALSE, array( 
			'class' 				=> 'IPS\\gallery\\Album',
			'forceOwner'			=> FALSE,
			'url'					=> \IPS\Request::i()->url()->setQueryString( 'modaction', 'move' ),
			'permissionCheck' 		=> function( $node ) use ( $currentContainer, $class )
			{
				/* If the image is in the same album already, we can't move it there */
				if( $currentContainer instanceof \IPS\gallery\Album and $currentContainer->id == $node->id )
				{
					return false;
				}

				/* Have we hit an images per album limit? */
				if( $node->owner()->group['g_img_album_limit'] AND ( $node->count_imgs + $node->count_imgs_hidden ) >= $node->owner()->group['g_img_album_limit'] )
				{
					return false;
				}

				/* Do we have permission to add? */
				if( !$node->can( 'add' ) )
				{
					return false;
				}

				return true;
			}
		) ) );

		if ( $values = $form->values() )
		{
			if( ( !isset( $values['move_to_category'] ) OR !( $values['move_to_category'] instanceof \IPS\Node\Model ) ) AND
				( !isset( $values['move_to_album'] ) OR !( $values['move_to_album'] instanceof \IPS\Node\Model ) ) )
			{
				$form->error	= \IPS\Member::loggedIn()->language()->addToStack('gallery_cat_or_album');

				\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
				return;
			}

			$params[] = ( isset( $values['move_to_category'] ) AND $values['move_to_category'] ) ? $values['move_to_category'] : $values['move_to_album'];
			$params[] = FALSE;

			return $params;
		}
		else
		{
			\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->sendOutput( \IPS\Output::i()->output  );
			}
			else
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
			}
			return;
		}
	}

	/**
	 * Return the sort direction to use for links
	 *
	 * @note	Abstracted so other table helper instances can adjust as needed
	 * @param	string	$column		Sort by string
	 * @return	string [asc|desc]
	 */
	public function getSortDirection( $column )
	{
		if( $column == 'image_caption' )
		{
			return 'asc';
		}

		return 'desc';
	}
}