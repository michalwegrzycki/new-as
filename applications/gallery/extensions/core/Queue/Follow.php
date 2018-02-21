<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		23 Sep 2014
 */

namespace IPS\gallery\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _Follow
{
	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$category	= \IPS\gallery\Category::load( $data['category_id'] );
		$album		= ( !is_null( $data['album_id'] ) ) ? \IPS\gallery\Album::load( $data['album_id'] ) : NULL;
		$member		= \IPS\Member::load( $data['member_id'] );
		$newOffset	= \IPS\gallery\Image::_sendNotificationsBatch( $category, $album, $member, $offset );

		if( $newOffset === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $newOffset;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		if ( !is_null( $data['album_id'] ) )
		{
			$numberOfFollowers = \IPS\gallery\Image::_notificationRecipients( \IPS\gallery\Category::load( $data['category_id'] ), \IPS\gallery\Album::load( $data['album_id'] ) )->count( TRUE );
		}
		else
		{
			$numberOfFollowers = \IPS\gallery\Image::_notificationRecipients( \IPS\gallery\Category::load( $data['category_id'] ) )->count( TRUE );
		}
		
		if ( $numberOfFollowers )
		{
			$complete = round( 100 / $numberOfFollowers * $offset, 2 );
		}
		else
		{
			$complete = 100;
		}
		
		$directContainer = ( !is_null( $data['album_id'] ) ) ? \IPS\gallery\Album::load( $data['album_id'] ) : \IPS\gallery\Category::load( $data['category_id'] );
		
		$title = $directContainer->_title;
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('backgroundQueue_follow', FALSE, array( 'htmlsprintf' => array( "<a href='{$directContainer->url()}' target='_blank'>{$title}</a>" ) ) ), 'complete' => $complete );
	}	
}