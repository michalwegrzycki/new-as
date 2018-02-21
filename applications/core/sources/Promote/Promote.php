<?php
/**
 * @brief		Promote model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Feb 2017
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Promote Model
 */
class _Promote extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_social_promote';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'promote_';
	
	/**
	 * @brief	Class object
	 */
	protected $object = NULL;
	
	/**
	 * @brief	Author object
	 */
	protected $author = NULL;
	
	/**
	 * @brief	Sent data
	 */
	protected $history = array();
	
	/**
	 * @brief	Promoter objects
	 */
	protected static $promoters = NULL;
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		/* Ensure TEXT fields are never NULL */
		$this->_data['text'] = array();
		$this->_data['short_link'] = '';
		$this->_data['images'] = array();
		$this->_data['media'] = array();
		$this->_data['share_to'] = array();
		$this->_data['returned'] = array();
	}

	/**
	 * Set the "text" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_text( $value )
	{
		$this->_data['text'] = ( is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "text" field
	 *
	 * @return array
	 */
	public function get_text()
	{
		return json_decode( $this->_data['text'], TRUE );
	}
	
	/**
	 * Set the "attach_ids" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_images( $value )
	{
		$this->_data['images'] = ( is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "attach_ids" field
	 *
	 * @return array
	 */
	public function get_images()
	{
		return $this->_data['images'] ? json_decode( $this->_data['images'], TRUE ) : array();
	}
	
	/**
	 * Set the "media" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_media( $value )
	{
		$this->_data['media'] = ( is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "media" field
	 *
	 * @return array
	 */
	public function get_media()
	{
		return $this->_data['media'] ? json_decode( $this->_data['media'], TRUE ) : array();
	}
	
	/**
	 * Set the "share_to" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_share_to( $value )
	{
		$this->_data['share_to'] = ( is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "share_to" field
	 *
	 * @return array
	 */
	public function get_share_to()
	{
		return $this->_data['share_to'] ? json_decode( $this->_data['share_to'], TRUE ) : array();
	}
	
	/**
	 * Set the "returned" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_returned( $value )
	{
		$this->_data['returned'] = ( is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "returned" field
	 *
	 * @return array
	 */
	public function get_returned()
	{
		return $this->_data['returned'] ? json_decode( $this->_data['returned'], TRUE ) : array();
	}
	
	/**
	 * The author object
	 *
	 * @return \IPS\Member
	 */
	public function author()
	{
		if ( $this->author === NULL )
		{
			try
			{
				$this->author = \IPS\Member::load( $this->added_by );
			}
			catch ( \Exception $e )
			{
				$this->author = new \IPS\Member;
			}
		}
		
		return $this->author;
	}
	
	/**
	 * The content object
	 *
	 * @return \IPS\Content
	 */
	public function object()
	{
		if ( $this->object === NULL )
		{
			$class = $this->class;
			$this->object = $class::load( $this->class_id );
		}
		
		return $this->object;
	}
	
	/**
	 * Get the object title
	 *
	 * @return string
	 */
	public function get_objectTitle()
	{
		return static::objectTitle( $this->object() );
	}
	
	/**
	 * Get the object date posted
	 *
	 * @return \IPS\DateTime|NULL
	 */
	public function get_objectDatePosted()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content )
		{
			if ( isset( $object::$databaseColumnMap['date'] ) )
			{
				return \IPS\DateTime::ts( $object->mapped('date') );
			}
			
			/* Valid object, but there isn't any date data available */
			return NULL;
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			/* Valid object, but there isn't any date data available */
			return NULL;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get the object author
	 *
	 * @return \IPS\Member|NULL
	 */
	public function get_objectAuthor()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content )
		{
			return $object->author();
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			try
			{
				return $object->owner();
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get the object unread status
	 *
	 * @return bool|null
	 */
	public function get_objectIsUnread()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content\Item )
		{
			return $object->unread();
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			return $object->item()->unread();
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			if ( $object::$contentItemClass )
			{
				$contentItemClass = $object::$contentItemClass;
				
				return $contentItemClass::containerUnread( $object );
			}
			
			return NULL;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get the number and indefinite article for replies/children where applicable
	 *
	 * @return array|null
	 */
	public function get_objectDataCount()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content\Item )
		{
			try
			{
				$container = $object->container();
			}
			catch( \Exception $e ){}

			if ( $object::supportsComments( NULL, $container ) )
			{
				$count = $object->mapped('num_comments');
				if ( $count AND isset( $object::$firstCommentRequired ) )
				{
					$count--;
				}
				
				return array( 'count' => $count, 'words' => \IPS\Member::loggedIn()->language()->addToStack( 'num_replies', NULL, array( 'pluralize' => array( $count ) ) ) );
			}

			if ( $object::supportsReviews( NULL, $container ) )
			{
				$count = $object->mapped('num_reviews');

				return array( 'count' => $count, 'words' => \IPS\Member::loggedIn()->language()->addToStack( 'num_reviews', NULL, array( 'pluralize' => array( $count ) ) ) );
			}
			
			/* Valid object, but there isn't any date data available */
			return NULL;
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			return NULL;
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			if( $object->_items !== NULL )
			{
				$count = $object->_items;

				return array( 'count' => $count, 'words' => \IPS\Member::loggedIn()->language()->addToStack( $object->_countLanguageString, NULL, array( 'pluralize' => array( $count ) ) ) );
			}

			return NULL;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Returns "Foo posted {{indefart}} in {{container}}, {{date}}
	 *
	 * @return array|null
	 */
	public function get_objectMetaDescription()
	{
		$object = $this->object();
		$author = $this->objectAuthor;
		
		if ( $object instanceof \IPS\Content\Item )
		{
			$container = $object->containerWrapper();

			if ( $container )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_container', NULL, array( 'htmlsprintf' => array( $author->link(), $object->indefiniteArticle(), $container->url(), $container->_title, $this->objectDatePosted->html( FALSE ) ) ) );
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_nocontainer', NULL, array( 'htmlsprintf' => array( $author->link(), $object->indefiniteArticle(), $this->objectDatePosted->html( FALSE ) ) ) );
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_nocontainer', NULL, array( 'htmlsprintf' => array( $author->link(), $object->indefiniteArticle(), $this->objectDatePosted->html( FALSE ) ) ) );
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_node', NULL, array( 'htmlsprintf' => array( $author->link(), $object->url(), $object->_title ) ) );
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get reactions class for this object
	 *
	 * @return array|null
	 */
	public function get_objectReactionClass()
	{
		$object = $this->object();
		$class = NULL;
		
		if ( ! \IPS\Settings::i()->reputation_enabled )
		{
			return NULL;
		}
		
		if ( $object instanceof \IPS\Content\Item )
		{
			/* The first post has the reactions for this item */
			if ( $object::$firstCommentRequired )
			{
				try
				{
					$class = $object->comments( 1, NULL, 'date', 'asc' );
				}
				catch( \Exception $e )
				{
					$class =  NULL;
				}
			}
			else
			{
				$class = $object;
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			$class = $object;
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return NULL;
		}
		
		return ( $class and \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Reactable' ) ) ? $class : NULL;
	}
	
	/**
	 * Send to networks now
	 *
	 * @return void
	 */
	public function send()
	{
		/* Race condition possible, so flag as sent now */
		$this->sent = time();
		$this->save();
		$returned = $this->returned;
		$time = time();
		$hasFailed = false;
		
		foreach( $this->share_to as $service )
		{
			if ( $this->failed )
			{
				/* It failed, but some services may have sent */
				if ( ! $this->serviceFailed( $service ) )
				{
					/* This service did sent, so skip */
					continue;
				}
			}

			$response = array(
				'response_promote_id' => $this->id,
				'response_promote_key' => $service,
				'response_date' => time(),
				'response_sent_date' => $time
			);
		
			try
			{
				$serviceObject = $this->getPromoter( $service )->setMember( \IPS\Member::load( $this->added_by ) );
				
				/* Successful post ID returned */
				$returned[ $service ] = $serviceObject->post( $this );
			}
			catch( \Exception $ex )
			{
				$hasFailed = true;
				$this->scheduled = time() + 600;
				$this->sent = 0;
				
				$response['response_failed'] = 1;
			}
			
			/* Full response stored */
			$response['response_json'] = json_encode( $serviceObject->response, TRUE );
				
			\IPS\Db::i()->insert( 'core_social_promote_content', $response );
		}

		$this->failed = ( $hasFailed ) ? $this->failed + 1 : 0;
		$this->returned = $returned;
		$this->save();
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		
		/* Enable the task again */
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'promote' ) );
	}
	
	/**
	 * Return an array of File objects
	 *
	 * @return array|null
	 */
	public function imageObjects()
	{
		$photos = array();
		if ( count( $this->images ) )
		{
			foreach( $this->images as $image )
			{
				foreach( $image as $ext => $url )
				{
					$photos[] = \IPS\File::get( $ext, $url );
				}
			}
		}
		
		if ( count( $this->media ) )
		{
			foreach( $this->media as $media )
			{
				$photos[] = \IPS\File::get( 'core_Promote', $media );
			}
		}
		
		return ( count( $photos ) ) ? $photos : NULL;
	}
	
	/**
	 * Look for a specific image
	 *
	 * @param	string	$path		Image path monthly_x_x/foo.gif
	 * @param	string	$extension	Storage extension
	 * @return boolean
	 */
	public function hasImage( $path, $extension='core_Attachment' )
	{
		foreach( $this->images as $image )
		{
			foreach( $image as $ext => $url )
			{
				if ( $ext == $extension and $path == $url )
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Returns a \IPS\DateTime object for the scheduled timestamp
	 *
	 * @return \IPS\DateTime
	 */
	public function scheduledDateTime()
	{
		$timezone = new \DateTimeZone( \IPS\Settings::i()->promote_tz );
		return \IPS\DateTime::ts( $this->scheduled )->setTimezone( $timezone );
	}
	
	/**
	 * Returns a \IPS\DateTime object for the sent timestamp
	 *
	 * @return \IPS\DateTime
	 */
	public function sentDateTime()
	{
		$timezone = new \DateTimeZone( \IPS\Settings::i()->promote_tz );
		return \IPS\DateTime::ts( $this->sent )->setTimezone( $timezone );
	}
	
	/**
	 * Shorten URL.
	 *
	 * @param	string		$service	Service key, (such as facebook or twitter)
	 * @returns	boolean
	 */
	public function serviceFailed( $service )
	{
		if ( ! $this->failed )
		{
			return FALSE;
		}
		
		$returned = $this->returned;
		
		return isset( $returned[ $service ] ) ? FALSE : TRUE;
	}
	
	/**
	 * Returns text sent to a named service
	 *
	 * @param	string		$service		Service key (twitter, facebook)
	 * @param	boolean		$forDisplay		Is this for display in output?
	 * @return  string|NULL
	 */
	public function getText( $service, $forDisplay=false )
	{
		if ( in_array( $service, $this->share_to ) )
		{
			$text = $this->text;
			
			return isset( $text[ $service ] ) ? ( $forDisplay ? nl2br( htmlspecialchars( $text[ $service ], ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE ) ) : $text[ $service ] ) : NULL;
		}
		
		return NULL;
	}
	
	/**
	 * Sets text for a named service
	 *
	 * @param	string		$service		Service key (twitter, facebook)
	 * @param	boolean		$text			Text to save
	 * @return  string|NULL
	 */
	public function setText( $service, $text )
	{
		$allText = $this->text;
		$allText[ $service ] = $text;
		$this->text = $allText;
	}
	
	/**
	 * Return the published URL for this post
	 *
	 * @param	string		$service	Service key, (such as facebook or twitter)
	 * @returns	string|NULL
	 */
	public function getPublishedUrl( $service )
	{
		$returned = $this->returned;
		
		if ( isset( $returned[ $service ] ) )
		{
			try
			{
				if ( $url = static::getPromoter( $service )->getUrl( $returned[ $service ] ) )
				{
					return $url;
				}
				
				return $this->object()->url();
			}
			catch ( \InvalidArgumentException $e )
			{
				return NULL;
			}
		}
	}

	/**
	 * Attempt to get all responses for this ID
	 *
	 * @param 	string		$service	Service to return (twitter, facebook, etc)
	 * @return	array|NULL		array( response_id => data )
	 */
	public function responses( $service )
	{
		$responses = array();
		try
		{
			foreach( \IPS\Db::i()->select( '*', 'core_social_promote_content', array( 'response_promote_id=? and response_promote_key=?', $this->id, $service ) ) as $row )
			{
				$responses[ $row['response_id'] ] = json_decode( $row['response_json'], TRUE );
			}
		}
		catch( \Exception $e )
		{
			return NULL;
		}
		
		return count( $responses ) ? $responses : NULL;
	}
	
	/**
	 * Fetch successful sent history for this promoted item
	 *
	 * @return	array|NULL		array( timestamp => array( service => response_time, ... )
	 */
	public function history()
	{
		if ( ! isset( $this->history[ $this->id ] ) )
		{
			$this->history[ $this->id ] = array();
			
			foreach( \IPS\Db::i()->select( '*', 'core_social_promote_content', array( 'response_promote_id=? and response_failed=0', $this->id ) ) as $row )
			{
				$this->history[ $this->id ][ $row['response_sent_date'] ][ $row['response_promote_key'] ] = $row['response_date'];
			}
		}
		
		return count( $this->history[ $this->id ] ) ? $this->history[ $this->id ] : NULL;
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		try
		{
			\IPS\Db::i()->delete( 'core_social_promote_content', array( 'response_promote_id=?', $this->id ) );
		}
		catch( \Exception $e ) { }
		
		return parent::delete();
	}
	
	/**
	 * Promote stream of internally promoted items
	 *
	 * @param	array		$services		Array of services by key (internal, facebook, etc)
	 * @param	int|array	$limit			Number of items to fetch
	 * @param	string		$sortField		Sort by field
	 * @param	string		$sortDirection	Sort by direction (asc, desc)
	 * @return  array
	 */
	public static function internalStream( $limit=10, $sortField='promote_sent', $sortDirection='desc')
	{
		$items = array();
		
		foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_sent > 0 and promote_internal=1' ), $sortField . ' ' . $sortDirection, $limit ) as $row )
		{
			$items[ $row['promote_id'] ] = static::constructFromData( $row );
		}
		
		return $items;
	}
	
	
	/**
	 * Can a member promote anything?
	 *
	 * @param	NULL|\IPS\Member	$member		Member object or NULL for current member
	 * @return	bool
	 */
	public static function canPromote( $member=NULL )
	{
		$member = $member ? $member : \IPS\Member::loggedIn();
		
		/* Got any services enabled? */
		if ( static::promoters() === NULL )
		{
			return FALSE;
		}
		
		if ( \IPS\Settings::i()->promote_members )
		{
			if ( \IPS\Settings::i()->promote_members and in_array( $member->member_id , explode( "\n", \IPS\Settings::i()->promote_members ) ) )
			{
				return TRUE;
			}
		}
		
		if ( $member->group['gbw_promote'] )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Can View wrapper for items and nodes
	 *
	 * @param	object				$object		Object (node or content item)
	 * @param	NULL|\IPS\Member	$member		Member object or NULL for current member
	 * @return boolean
	 */
	public static function objectCanView( $object, $member=NULL )
	{
		$member ?: \IPS\Member::loggedIn();
		
		if ( $object instanceof \IPS\Content )
		{
			return $object->canView( $member );
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return $object->can( 'view', $member );
		}
		
		throw new \OutofRangeException('object_not_valid');
	}

	/**
	 * Return a list of groups that cannot see this item
	 *
	 * @param	object				$object		Object (node or content item)
	 * @return NULL|Array
	 */
	public static function objectCannotViewGroups( $object )
	{
		$groups = array();
		foreach( \IPS\Member\Group::groups() as $group )
		{
			if ( $object instanceof \IPS\Content\Comment )
			{
				if ( ! $object->item()->can( 'view', $group ) )
				{
					$groups[] = $group->name;
				}
			}
			else
			{
				if ( ! $object->can( 'view', $group ) )
				{
					$groups[] = $group->name;
				}
			}
		}
		
		return count( $groups ) ? $groups : NULL;
	}
	
	/**
	 * Get title wrapper for items and nodes
	 *
	 * @param	object	$object		Object (node or content item)
	 * @return string
	 */
	public static function objectTitle( $object )
	{
		if ( $object instanceof \IPS\Content\Item )
		{
			return $object->mapped('title');
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			try
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_thing_in_thing', NULL, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $object::$title ), $object->item()->mapped('title') ) ) );
			}
			catch( \Exception $e )
			{
				return $object->item()->mapped('title');
			}
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return $object->_title;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get content wrapper for items and nodes
	 *
	 * @param	object	$object		Object (node or content item)
	 * @return string
	 */
	public static function objectContent( $object )
	{
		$result = NULL;

		if ( $object instanceof \IPS\Content\Item )
		{
			if ( isset( $object::$databaseColumnMap['content'] ) )
			{
				$result = $object->truncated();
			}
			else if ( $object::$firstCommentRequired )
			{
				$firstComment = $object->comments( 1, NULL, 'date', 'asc' );
				
				$result = $firstComment->truncated();
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			$result = $object->truncated();
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			$result = $object->description;
		}
		
		/* If result was not set, throw exception now */
		if( $result === NULL )
		{
			throw new \OutofRangeException('object_not_valid');
		}

		/* If we treat enter key as newline instead of paragraph, we need to clean up a bit further */
		if( !\IPS\Settings::i()->editor_paragraph_padding )
		{
			$result = str_replace( "\n", '', $result );
		}

		/* Clean up excess newlines */
		$result = trim( preg_replace( "#(<br>){1,}#", "\n",  preg_replace( '#(<br>)\s+#', "\n", $result ) ) );

		/* If this is a node, strip HTML tags for security reasons */
		if ( $object instanceof \IPS\Node\Model )
		{
			$result = strip_tags( $result );
		}

		return $result;
	}
	
	/**
	 * Load promote row for this class and id
	 */
	protected static $classAndIdLookup = array();
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$object = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		static::$classAndIdLookup[ $object->class ][ $object->class_id ] = $object->id;
		return $object;
	}

	/**
	 * Load promote row for this class and id
	 *
	 * @param	string		$class 				Class name
	 * @param	integer		$id					Item ID
	 * @param	boolean		$sent				Only look for sent items
	 * @param	boolean		$futureScheduled	Only look for future scheduled items
	 * @return  \IPS\core\Promote item
	 */
	public static function loadByClassAndId( $class, $id, $sent=FALSE, $futureScheduled=FALSE )
	{		
		if ( !isset( static::$classAndIdLookup[ $class ][ $id ] ) )
		{
			try
			{
				$object = static::constructFromData( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_class=? and promote_class_id=?', $class, $id ) )->first() );
			}
			catch( \UnderflowException $e )
			{
				static::$classAndIdLookup[ $class ][ $id ] = 0;
				return;
			}
		}
		else
		{
			if ( static::$classAndIdLookup[ $class ][ $id ] )
			{
				$object = static::load( static::$classAndIdLookup[ $class ][ $id ] );
			}
			else
			{
				return;
			}
		}
		
		if ( $futureScheduled and $object->scheduled < time() )
		{
			return;
		}
		
		if ( $sent and $object->sent > time() )
		{
			return;
		}
		
		return $object;		
	}
	
	/**
	 * Shorten URL.
	 * We only have bit.ly as a shortener at the moment.
	 *
	 * @param	string		$longUrl	The original URL
	 * @returns	string		NULL if no shortnener available or it fails
	 * @throws	RuntimeException if shortener fails
	 * @throws	UnderflowException if no shortener available
	 */
	public static function shortenUrl( $longUrl )
	{
		if ( ! \IPS\Settings::i()->bitly_enabled or ! \IPS\Settings::i()->bitly_token )
		{
			throw new \UnderflowException;
		}
		
		/* Have a bash at it like */
		try
		{
			$response = \IPS\Http\Url::external( "https://api-ssl.bitly.com/v3/shorten" )->setQueryString( array( 'access_token' => \IPS\Settings::i()->bitly_token, 'longUrl' => $longUrl ) )->request()->get()->decodeJson();

			if ( $response['status_code'] !== 200 )
			{
				throw new \RuntimeException;
			}
			
			return $response['data']['url'];
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \RuntimeException;
		}
	}
	
	/**
	 * Get the next auto schedule timestamp
	 *
	 * @return null|DateTime object
	 */
	public static function getNextAutoSchedule()
	{
		if ( ! \IPS\Settings::i()->promote_scheduled )
		{
			return NULL;
		}
		
		$latest = \IPS\Db::i()->select( 'MAX(promote_scheduled)', 'core_social_promote', array( 'promote_schedule_auto=1 and promote_sent=0' ) )->first();
		$timezone = new \DateTimeZone( \IPS\Settings::i()->promote_tz );
		$current = \IPS\DateTime::create()->setTimezone( $timezone );
		$times = explode( ',', \IPS\Settings::i()->promote_scheduled );
		$time = NULL;
		
		if ( $latest )
		{
			$current = \IPS\DateTime::ts( $latest )->setTimezone( $timezone );
		}
		
		/* Fetch the next scheduled time */
		$test = $current;
		foreach( $times as $entry )
		{
			list( $h, $m ) = explode( ':', $entry );
			$test = \IPS\DateTime::create()->setTimezone( $timezone )->setTime( $h, $m );
			
			if ( $current->getTimeStamp() < $test->getTimeStamp() )
			{
				$time = $test;
				break;
			}
		}
		
		/* Still here? Then pick the earliest time for the next day */
		if ( $time === NULL )
		{
			$firstTime = array_shift( $times );
			list( $h, $m ) = explode( ':', $firstTime );
			
			$time = $current->add( new \DateInterval( 'P1D' ) )->setTime( $h, $m );
		}
		
		return $time;
	}
	
	/**
	 * Return a single promote object
	 *
	 * @throws UnderflowException
	 * @return \IPS\Login
	 */

	public static function getPromoter( $key )
	{
		/* Try and get this from the datastore first */
		$promoters = static::promoters();
	
		if ( $promoters !== NULL )
		{
			foreach( $promoters as $promoterKey => $object )
			{
				if ( mb_strtolower( $promoterKey ) == mb_strtolower( $key ) )
				{
					return $object;
				}
			}
		}
		
		/* Fall back */
		return \IPS\Content\Promote\PromoteAbstract::constructFromData( \IPS\Db::i()->select( '*', 'core_social_promote_sharers', array( 'sharer_key=?', $key ) )->first() );
	}
	
	/**
	 * Get Promoter objects
	 *
	 * @return	array
	 */
	public static function promoters()
	{
		/* Fetch the appropriate promoters */
		if ( static::$promoters === NULL )
		{
			
			if ( isset( \IPS\Data\Store::i()->promoters ) )
			{
				$rows = \IPS\Data\Store::i()->promoters;
			}
			else
			{
				$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'core_social_promote_sharers', 'sharer_enabled=1' ) );
				\IPS\Data\Store::i()->promoters = $rows;					
			}
				
			foreach ( $rows as $row )
			{
				try
				{
					static::$promoters[ $row['sharer_key'] ] = \IPS\Content\Promote\PromoteAbstract::constructFromData( $row );
				}
				catch ( \RuntimeException $e ) { }
			}
		}

		return static::$promoters;
	}
	
	/**
	 * Return the promotable services this user has access to
	 *
	 * @return NULL|array of promote classes
	 */
	public static function promoteServices()
	{
		$services = array();
		$promoters = static::promoters();
		
		if ( $promoters === NULL )
		{
			return NULL;
		}
		
		foreach( $promoters as $key => $object )
		{
			if ( $object->setMember( \IPS\Member::loggedIn() )->canPromote() )
			{
				$services[] = $object;
			}
		}
		
		return count( $services ) ? $services : NULL;
	}
	
	/**
	 * Process any queued items
	 *
	 * @return void
	 */
	public static function processQueue()
	{
		$processed = 0;
		foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_sent=0 and promote_failed < 4 and ( promote_scheduled < ' . time() . ' and promote_scheduled > 0 )' ), 'promote_scheduled asc', array( 0, 5 ) ) as $row )
		{
			$processed++;
			$promote = static::constructFromData( $row );
			$promote->send();
		}
		
		if ( ! $processed )
		{
			/* Disable the task for now, but only if there is nothing to send later */
			try
			{
				$future = \IPS\Db::i()->select( 'COUNT(*)', 'core_social_promote', array( "promote_sent=?", 0 ) )->first();
			}
			catch( \Exception $e )
			{
				$future = 0;
			}
			
			if ( !$future )
			{
				\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 'promote' ) );
			}
		}
	}
	
	/**
	 * Reschedule queued items
	 *
	 * @return void
	 */
	public static function rescheduleQueue()
	{
		/* Reset times */
		if ( \IPS\Settings::i()->promote_scheduled )
		{
			\IPS\Db::i()->update( 'core_social_promote', array( 'promote_scheduled' => 0 ), array( 'promote_schedule_auto=1 and promote_sent=0' ) );
			
			foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_scheduled=0 and promote_schedule_auto=1 and promote_sent=0' ), 'promote_added asc' ) as $row )
			{
				\IPS\Db::i()->update( 'core_social_promote', array( 'promote_scheduled' => static::getNextAutoSchedule()->getTimeStamp() ), array( 'promote_id=?', $row['promote_id'] ) );
			}
		}
	}
}