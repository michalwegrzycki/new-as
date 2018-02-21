<?php
/**
 * @brief		Reaction Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Reaction Trait
 */
trait Reactable
{
	/**
	 * Reaction type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		throw new \BadMethodCallException;
	}
	
	/**
	 * Reaction class
	 *
	 * @return	string
	 */
	public static function reactionClass()
	{
		return get_called_class();
	}
	
	/**
	 * React
	 *
	 * @param	\IPS\core\Reaction		THe reaction
	 * @param	\IPS\Member				The member reacting, or NULL 
	 * @return	void
	 * @throws	\DomainException
	 */
	public function react( \IPS\Content\Reaction $reaction, \IPS\Member $member = NULL )
	{
		/* Did we pass a member? */
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Figure out the owner of this - if it is content, it will be the author. If it is a node, then it will be the person who created it */
		if ( $this instanceof \IPS\Content )
		{
			$owner = $this->author();
		}
		else if ( $this instanceof \IPS\Node\Model )
		{
			$owner = $this->owner();
		}

		/* Can we react? */
		if ( !$this->canView( $member ) or !$this->canReact( $member ) or !$reaction->enabled )
		{
			throw new \DomainException( 'cannot_react' );
		}
		
		/* Have we hit our limit? Also, why 999 for unlimited? */
		if ( $member->group['g_rep_max_positive'] !== -1 )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', array( 'member_id=? AND rep_date>?', $member->member_id, \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() ) )->first();
			if ( $count >= $member->group['g_rep_max_positive'] )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'react_daily_exceeded', FALSE, array( 'sprintf' => array( $member->group['g_rep_max_positive'] ) ) ) );
			}
		}
		
		/* Figure out our app - we do it this way as content items and nodes will always have a lowercase namespace for the app, so if the match below fails, then 'core' can be assumed */
		$app = explode( '\\', get_class( $this ) );
		if ( \strtolower( $app[1] ) === $app[1] )
		{
			$app = $app[1];
		}
		else
		{
			$app = 'core';
		}
		
		/* If this is a comment, we need the parent items ID */
		$itemId = 0;
		if ( $this instanceof \IPS\Content\Comment )
		{
			$item			= $this->item();
			$itemIdColumn	= $item::$databaseColumnId;
			$itemId			= $item->$itemIdColumn;
		}
		
		/* Have we already reacted? */
		if ( $this->reacted( $member ) )
		{
			$this->removeReaction( $member );
		}
		
		/* Actually insert it */
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->insert( 'core_reputation_index', array(
			'member_id'				=> $member->member_id,
			'app'					=> $app,
			'type'					=> static::reactionType(),
			'type_id'				=> $this->$idColumn,
			'rep_date'				=> \IPS\DateTime::create()->getTimestamp(),
			'rep_rating'			=> $reaction->value,
			'member_received'		=> $owner->member_id,
			'rep_class'				=> static::reactionClass(),
			'class_type_id_hash'	=> md5( static::reactionClass() . ':' . $this->$idColumn ),
			'item_id'				=> $itemId,
			'reaction'				=> $reaction->id
		) );
		
		/* Send the notification */
		if ( $this->author()->member_id AND $this->author() != \IPS\Member::loggedIn() AND $this->canView( $owner ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load('core'), 'new_likes', $this, array( $this, $member ), array(), TRUE, \IPS\Content\Reaction::isLikeMode() ? NULL : 'notification_new_react' );
			$notification->recipients->attach( $owner );
			$notification->send();
		}
		
		if ( $owner->member_id )
		{
			$owner->pp_reputation_points += $reaction->value;
			$owner->save();
		}
	}
	
	/**
	 * Remove Reaction
	 *
	 * @param	\IPS\Member|NULL		The member, or NULL
	 * @return	void
	 */
	public function removeReaction( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		try
		{
			try
			{
				$idColumn	= static::$databaseColumnId;
				
				$where = $this->getReactionWhereClause( NULL, FALSE );
				$where[] = array( 'member_id=?', $member->member_id );
				$rep		= \IPS\Db::i()->select( '*', 'core_reputation_index', $where )->first();
			}
			catch( \UnderflowException $e )
			{
				throw new \OutOfRangeException;
			}
			
			$memberReceived		= \IPS\Member::load( $rep['member_received'] );
			$reaction			= \IPS\Content\Reaction::load( $rep['reaction'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \DomainException;
		}
		
		if ( $memberReceived->member_id )
		{
			$memberReceived->pp_reputation_points = $memberReceived->pp_reputation_points - $reaction->value;
			$memberReceived->save();
		}
		
		\IPS\Db::i()->delete( 'core_reputation_index', array( "id=?", $rep['id'] ) );

		/* Remove Notifications */
		$memberIds	= array();

		foreach( \IPS\Db::i()->select( 'member', 'core_notifications', array( 'notification_key=? AND item_class=? AND item_id=?', 'new_likes', (string) get_class( $this ), (int) $this->$idColumn ) ) as $member )
		{
			$memberIds[ $member ]	= $member;
		}

		\IPS\Db::i()->delete( 'core_notifications', array( 'notification_key=? AND item_class=? AND item_id=?', 'new_likes', (string) get_class( $this ), (int) $this->$idColumn ) );

		foreach( $memberIds as $member )
		{
			\IPS\Member::load( $member )->recountNotifications();
		}
	}
	
	/**
	 * Can React
	 *
	 * @param	\IPS\Member|NULL		The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canReact( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $this instanceof \IPS\Content )
		{
			$owner = $this->author();
		}
		else if ( $this instanceof \IPS\Node\Model )
		{
			$owner = $this->owner();
		}
		
		/* Only members can react */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		if ( !$owner->member_id )
		{
			return FALSE;
		}
		
		/* Protected Groups */
		if ( $owner->inGroup( explode( ',', \IPS\Settings::i()->reputation_protected_groups ) ) )
		{
			return FALSE;
		}
		
		/* Reactions per day */
		if ( $member->group['g_rep_max_positive'] == 0 )
		{
			return FALSE;
		}
		
		/* React to own content */
		if ( !\IPS\Settings::i()->reputation_can_self_vote AND $this->author()->member_id == $member->member_id )
		{
			return FALSE;
		}
		
		/* Still here? All good. */
		return TRUE;
	}
	
	/**
	 * @brief	Reactions Cache
	 */
	protected $_reactions = NULL;
	
	/**
	 * Reactions
	 *
	 * @param	array|NULL	$mixInData	If the data is already know, it can be passed here to be manually set
	 * @return	array
	 */
	public function reactions()
	{
		if ( $this->_reactionCount === NULL )
		{
			$this->_reactionCount = 0;
		}
		
		if ( $this->_reactions === NULL )
		{
			$idColumn	= static::$databaseColumnId;
			$this->_reactions = array();
			
			if ( is_array( $this->reputation ) )
			{
				foreach( $this->reputation AS $memberId => $reactionId )
				{
					try
					{
						$this->_reactionCount += \IPS\Content\Reaction::load( $reactionId )->value;
						$this->_reactions[ $memberId ][] = $reactionId;
					}
					catch( \OutOfRangeException $e ) { }
				}
			}
			else
			{
				/* Set the data in $this->reputation to save queries later */
				$this->reputation = array();
				foreach( \IPS\Db::i()->select( '*', 'core_reputation_index', $this->getReactionWhereClause() )->join( 'core_reactions', 'reaction=reaction_id' ) AS $reaction )
				{
					$this->reputation[ $reaction['member_id'] ] = $reaction['reaction'];
					$this->_reactions[ $reaction['member_id'] ][] = $reaction['reaction'];
					$this->_reactionCount += $reaction['rep_rating'];
				}
			}
		}
		
		return $this->_reactions;
	}
	
	/**
	 * @brief Reaction Count
	 */
	protected $_reactionCount = NULL;
	
	/**
	 * Reaction Count
	 *
	 * @return int
	 */
	public function reactionCount()
	{
		$this->reactions();
		return $this->_reactionCount;
	}
	
	/**
	 * Reaction Where Clause
	 *
	 * @param	\IPS\Content\Reaction|array|int|NULL	$reactions			This can be any one of the following: An \IPS\Content\Reaction object, an array of \IPS\Content\Reaction objects, and integer, or an array of integers, or NULL
	 * @param	bool									$enabledTypesOnly 	If TRUE, only reactions of the enabled reaction types will be included (must join core_reactions)
	 * @return	array
	 */
	public function getReactionWhereClause( $reactions = NULL, $enabledTypesOnly=TRUE )
	{
		$idColumn = static::$databaseColumnId;
		$where = array( array( 'rep_class=? AND type=? AND type_id=?', static::reactionClass(), static::reactionType(), $this->$idColumn ) );
		
		if ( $enabledTypesOnly )
		{
			$where[] = array( 'reaction_enabled=1' );
		}
		
		if ( $reactions !== NULL )
		{
			if ( !is_array( $reactions ) )
			{
				$reactions = array( $reactions );
			}
			
			$in = array();
			foreach( $reactions AS $reaction )
			{
				if ( $reaction instanceof \IPS\Content\Reaction )
				{
					$in[] = $reaction->id;
				}
				else
				{
					$in[] = $reaction;
				}
			}
			
			if ( count( $in ) )
			{
				$where[] = array( \IPS\Db::i()->in( 'reaction', $in ) );
			}
		}
		
		return $where;
	}
	
	/**
	 * Reaction Table
	 *
	 * @return	\IPS\Helpers\Table\Db
	 */
	public function reactionTable( $reaction=NULL )
	{
		if ( !\IPS\Member::loggedIn()->group['gbw_view_reps'] or !$this->canView() )
		{
			throw new \DomainException;
		}
		
		$idColumn = static::$databaseColumnId;
		
		$table = new \IPS\Helpers\Table\Db( 'core_reputation_index', $this->url('showReactions'), $this->getReactionWhereClause( $reaction ) );
		$table->sortBy			= 'rep_date';
		$table->sortDirection	= 'desc';
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'reactionLogTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'reactionLog' );
		$table->joins = array( array( 'from' => 'core_reactions', 'where' => 'reaction=reaction_id' ) );

		$table->rowButtons = function( $row )
		{
			return array(
				'delete'	=> array(
					'icon'	=> 'times-circle',
					'title'	=> 'delete',
					'link'	=> $this->url( 'unreact' )->csrf()->setQueryString( array( 'member' => $row['member_id'] ) ),
					'data' 	=> array( 'confirm' => TRUE )
				)
			);
		};
		
		return $table;
	}
	
	/**
	 * Has reacted?
	 *
	 * @param	\IPS\Member|NULL	The member, or NULL for currently logged in
	 * @return	\IPS\Content\Reaction|FALSE
	 */
	public function reacted( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$idColumn = static::$databaseColumnId;
		try
		{
			if ( is_array( $this->reputation ) )
			{
				if ( isset( $this->reputation[ $member->member_id ] ) )
				{
					return \IPS\Content\Reaction::load( $this->reputation[ $member->member_id ] );
				}
				else
				{
					return FALSE;
				}
			}
			else
			{
				$where = $this->getReactionWhereClause( NULL, FALSE );
				$where[] = array( 'member_id=?', $member->member_id );
				return \IPS\Content\Reaction::load( \IPS\Db::i()->select( 'reaction', 'core_reputation_index', $where )->first() );
			}
		}
		catch( \UnderflowException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * @brief	Cached React Blurb
	 */
	public $reactBlurb = NULL;
	
	/**
	 * React Blurb
	 *
	 * @return	string
	 */
	public function reactBlurb()
	{
		if ( $this->reactBlurb === NULL )
		{
			$this->reactBlurb = array();
			
			if ( count( $this->reactions() ) )
			{
				$idColumn = static::$databaseColumnId;
				if ( is_array( $this->reputation ) )
				{
					foreach( $this->reputation AS $memberId => $reaction )
					{
						if ( !isset( $this->reactBlurb[ $reaction ] ) )
						{
							$this->reactBlurb[ $reaction ] = 0;
						}
						
						$this->reactBlurb[ $reaction ]++;
					}
				}
				else
				{
					foreach( \IPS\Db::i()->select( 'reaction', 'core_reputation_index', $this->getReactionWhereClause() )->join( 'core_reactions', 'reaction=reaction_id' ) AS $rep )
					{
						if ( !isset( $this->reactBlurb[ $rep ] ) )
						{
							$this->reactBlurb[ $rep ] = 0;
						}
						
						$this->reactBlurb[ $rep ]++;
					}
				}
				
				/* Error suppressor for https://bugs.php.net/bug.php?id=50688 */
				@uksort( $this->reactBlurb, function( $a, $b ) {
					try
					{
						$a = \IPS\Content\Reaction::load( $a );
						$b = \IPS\Content\Reaction::load( $b );
					}
					/* One of the reactions does not exist */
					catch( \OutOfRangeException $e )
					{
						return 1;
					}
					
					if ( $a->position < $b->position )
					{
						return -1;
					}
					elseif ( $a->position == $b->position )
					{
						return 0;
					}
					else
					{
						return 1;
					}
				} );
			}
			else
			{
				$this->reactBlurb = array();
			}
		}
		return $this->reactBlurb;
	}
	
	/**
	 * @brief	Cached like blurb
	 */
	public $likeBlurb	= NULL;
	
	/**
	 * Who Reacted
	 *
	 * @param	bool|NULL	Use like text instead? NULL to automatically determine
	 * @return	string
	 */
	public function whoReacted( $isLike = NULL )
	{
		if ( $isLike === NULL )
		{
			$isLike =  \IPS\Content\Reaction::isLikeMode();
		}
		
		if( $this->likeBlurb === NULL )
		{
			$langPrefix = 'react_';
			if ( $isLike )
			{
				$langPrefix = 'like_';
			}

			/* Did anyone like it? */
			$numberOfLikes = $this->reactionCount(); # int
			if ( $numberOfLikes )
			{				
				/* Is it just us? */
				$userLiked = ( $this->reacted() );
				if ( $userLiked and $numberOfLikes < 2 )
				{
					$this->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack("{$langPrefix}blurb_just_you");
				}
				
				/* Nope, we need to display a number... */
				else
				{
					$peopleToDisplayInMainView = array();
					$andXOthers = $numberOfLikes;
					
					/* If the user liked, we always show "You" first */
					if ( $userLiked )
					{
						$peopleToDisplayInMainView[] = \IPS\Member::loggedIn()->language()->addToStack("{$langPrefix}blurb_you_and_others");
						$andXOthers--;
					}
					
					$peopleToDisplayInSecondaryView = array();
					
					/* Some random names */
					$idColumn = static::$databaseColumnId;
					$i = 0;
					$peopleToDisplayInSecondaryView = array();
					/* Figure out our app - we do it this way as content items and nodes will always have a lowercase namespace for the app, so if the match below fails, then 'core' can be assumed */
					$app = explode( '\\', static::reactionClass() );
					if ( \strtolower( $app[1] ) === $app[1] )
					{
						$app = $app[1];
					}
					else
					{
						$app = 'core';
					}
					$where = $this->getReactionWhereClause();
					$where[] = array( 'member_id!=?', \IPS\Member::loggedIn()->member_id ?: 0 );
					foreach ( \IPS\Db::i()->select( '*', 'core_reputation_index', $where, 'RAND()', $userLiked ? 17 : 18 )->join( 'core_reactions', 'reaction=reaction_id' ) as $rep )
					{
						if ( $i < ( $userLiked ? 2 : 3 ) )
						{
							$peopleToDisplayInMainView[] = htmlspecialchars( \IPS\Member::load( $rep['member_id'] )->name, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE );
							$andXOthers--;
						}
						else
						{
							$peopleToDisplayInSecondaryView[] = htmlspecialchars( \IPS\Member::load( $rep['member_id'] )->name, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE );
						}
						$i++;
					}
					
					/* If there's people to display in the secondary view, add that */
					if ( $peopleToDisplayInSecondaryView )
					{
						if ( count( $peopleToDisplayInSecondaryView ) < $andXOthers )
						{
							$peopleToDisplayInSecondaryView[] = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_others_secondary", FALSE, array( 'pluralize' => array( $andXOthers - count( $peopleToDisplayInSecondaryView ) ) ) );
						}
						$peopleToDisplayInMainView[] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reputationOthers( $this->url( 'showReactions' ), \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_others", FALSE, array( 'pluralize' => array( $andXOthers ) ) ), json_encode( $peopleToDisplayInSecondaryView ) );
					}
					
					/* Put it all together */
					$this->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb", FALSE, array( 'pluralize' => array( $numberOfLikes ), 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $peopleToDisplayInMainView ) ) ) );
				}
				
			}
			/* Nobody liked it - show nothing */
			else
			{
				$this->likeBlurb = '';
			}
		}
				
		return $this->likeBlurb;
	}
}