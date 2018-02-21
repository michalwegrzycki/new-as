<?php
/**
 * @brief		Clubs View
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Feb 2017
 */

namespace IPS\core\modules\front\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs View
 */
class _view extends \IPS\Helpers\CoverPhoto\Controller
{
	/**
	 * @brief	The club being viewed
	 */
	protected $club;
	
	/**
	 * @brief	The logged in user's status
	 */
	protected $memberStatus;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( \IPS\Request::i()->do != 'embed' )
		{
			/* Permission check */
			if ( !\IPS\Settings::i()->clubs )
			{
				\IPS\Output::i()->error( 'no_module_permission', '2C350/P', 403, '' );
			}
			
			/* Load the club */
			try
			{
				$this->club = \IPS\Member\Club::load( \IPS\Request::i()->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C350/1', 404, '' );
			}
			$this->memberStatus = $this->club->memberStatus( \IPS\Member::loggedIn() );
			
			/* If we can't even know it exists, show an error */
			if ( !$this->club->canView() )
			{
				\IPS\Output::i()->error( \IPS\Member::loggedIn()->member_id ? 'no_module_permission' : 'no_module_permission_guest', '2C350/2', 403, '' );
			}
							
			/* Sort out the breadcrumb */
			\IPS\Output::i()->breadcrumb = array(
				array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') ),
				array( $this->club->url(), $this->club->name )
			);
			
			/* Add a "Search in this club" contextual search option */
			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_club' ) ] = array( 'type' => '', 'club' => "c{$this->club->id}" );
	
			/* CSS */
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs.css', 'core', 'front' ) );
			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs_responsive.css', 'core', 'front' ) );
			}
	
			/* JS */
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_clubs.js', 'core', 'front' ) );
			
			/* Location for online list */
			if ( $this->club->type !== \IPS\Member\Club::TYPE_PRIVATE )
			{
				\IPS\Session::i()->setLocation( $this->club->url(), array(), 'loc_clubs_club', array( $this->club->name => FALSE ) );
			}
			else
			{
				\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), array(), 'loc_clubs_directory' );
			}
		}
		
		/* Pass upwards */
		parent::execute();
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* If this is a closed club, and we don't have permission to read it, show the member page instead */
		if ( $this->club->type == \IPS\Member\Club::TYPE_CLOSED && !$this->club->canRead() )
		{
			$this->members();
			return;
		}

		/* Get the activity stream */
		$activity = \IPS\Content\Search\Query::init()->filterByClub( $this->club )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED )->search();
				
		/* Get who joined the club in between those results */
		if ( $this->club->type != \IPS\Member\Club::TYPE_PUBLIC )
		{
			$lastTime = NULL;
			foreach ( $activity as $key => $result )
			{
				if ( $result !== NULL )
				{
					$lastTime = $result->createdDate->getTimestamp();
				}
				else
				{
					unset( $activity[ $key ] );
				}
			}
			$joins = array();
			$joinWhere = array( array( 'club_id=?', $this->club->id ), array( \IPS\Db::i()->in( 'status', array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER ) ) ) );
			if ( $lastTime )
			{
				$joinWhere[] = array( 'core_clubs_memberships.joined>?', $lastTime );
			}
			$select = 'core_clubs_memberships.joined' . ',' . implode( ',', array_map( function( $column ) {
				return 'core_members.' . $column;
			}, \IPS\Member::columnsForPhoto() ) );
			foreach ( \IPS\Db::i()->select( $select, 'core_clubs_memberships', $joinWhere, 'joined DESC', array( 0, 50 ), NULL, NULL, \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS )->join( 'core_members', 'core_members.member_id=core_clubs_memberships.member_id' ) as $join )
			{
				$joins[] = new \IPS\Content\Search\Result\Custom(
					\IPS\DateTime::ts( $join['core_clubs_memberships']['joined'] ),
					\IPS\Member::loggedIn()->language()->addToStack( 'clubs_activity_joined', FALSE, array( 'htmlsprintf' => \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $join['core_members']['member_id'], $join['core_members']['name'], $join['core_members']['members_seo_name'] ) ) ),
					\IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userPhotoFromData( $join['core_members']['member_id'], $join['core_members']['name'], $join['core_members']['members_seo_name'], \IPS\Member::photoUrl( $join['core_members'] ), 'tiny' )
				);
			}
			
			/* Merge them in */
			if ( !empty( $joins ) )
			{
				$activity = array_filter( array_merge( iterator_to_array( $activity ), $joins ) );
				uasort( $activity, function( $a, $b )
				{
					if ( $a->createdDate->getTimestamp() == $b->createdDate->getTimestamp() )
					{
						return 0;
					}
					elseif( $a->createdDate->getTimestamp() < $b->createdDate->getTimestamp() )
					{
						return 1;
					}
					else
					{
						return -1;
					}
				} );
			}
		}
				
		/* Display */				
		\IPS\Output::i()->title = $this->club->name;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clubs')->view( $this->club, $activity, $this->club->fieldValues() );
	}
	
	/**
	 * Map Callback
	 *
	 * @return	void
	 */
	protected function mapPopup()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clubs')->mapPopup( $this->club );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		if ( !( $this->club->owner and $this->club->owner == \IPS\Member::loggedIn() ) and !\IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/A', 403, '' );
		}
		
		if ( $form = $this->club->form() )
		{
			\IPS\Output::i()->title = $this->club->name;
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
			}
			else
			{
				\IPS\Output::i()->output = $form;
			}
		}
		else
		{
			\IPS\Output::i()->redirect( $this->club->url() );
		}
	}
	
	/**
	 * Edit Photo
	 *
	 * @return	void
	 */
	protected function editPhoto()
	{
		if ( !( $this->club->owner and $this->club->owner == \IPS\Member::loggedIn() ) and !\IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/R', 403, '' );
		}
		\IPS\Output::i()->title = $this->club->name;
		
		$form = new \IPS\Helpers\Form( 'club_profile_photo', 'continue' );
		$form->ajaxOutput = TRUE;
		$form->add( new \IPS\Helpers\Form\Upload( 'club_profile_photo', $this->club->profile_photo_uncropped ? \IPS\File::get( 'core_Clubs', $this->club->profile_photo_uncropped ) : NULL, FALSE, array( 'storageExtension' => 'core_Clubs', 'image' => array( 'maxWidth' => 200, 'maxHeight' => 200 ) ) ) );
		if ( $values = $form->values() )
		{
			if ( !$values['club_profile_photo'] or $this->club->profile_photo_uncropped != (string) $values['club_profile_photo'] )
			{
				foreach ( array( 'profile_photo', 'profile_photo_uncropped' ) as $k )
				{
					try
					{
						\IPS\File::get( 'core_Clubs', $this->club->$k )->delete();
					}
					catch ( \Exception $e ) { }
					$this->club->$k = NULL;
				}
			}
			
			if ( $values['club_profile_photo'] )
			{
				$this->club->profile_photo_uncropped = (string) $values['club_profile_photo'];
				$this->club->save();
				
				if ( \IPS\Request::i()->isAjax() )
				{					
					$this->cropPhoto();
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'cropPhoto' ) );
				}
			}
			else
			{
				$this->club->save();
				\IPS\Output::i()->redirect( $this->club->url() );
			}
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}
	
	/**
	 * Crop Photo
	 *
	 * @return	void
	 */
	protected function cropPhoto()
	{
		if ( !( $this->club->owner and $this->club->owner == \IPS\Member::loggedIn() ) and !\IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/V', 403, '' );
		}
		\IPS\Output::i()->title = $this->club->name;
		
		/* Get the photo */
		if ( !$this->club->profile_photo_uncropped )
		{
			\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'editPhoto' ) );
		}
		$original = \IPS\File::get( 'core_Clubs', $this->club->profile_photo_uncropped );
		$image = \IPS\Image::create( $original->contents() );
		
		/* Work out which dimensions to suggest */
		if ( $image->width < $image->height )
		{
			$suggestedWidth = $suggestedHeight = $image->width;
		}
		else
		{
			$suggestedWidth = $suggestedHeight = $image->height;
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form( 'photo_crop', 'save', $this->club->url()->setQueryString( 'do', 'cropPhoto' ) );
		$form->class = 'ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Custom('photo_crop', array( 0, 0, $suggestedWidth, $suggestedHeight ), FALSE, array(
			'getHtml'	=> function( $field ) use ( $original )
			{
				return \IPS\Theme::i()->getTemplate('profile')->photoCrop( $field->name, $field->value, $this->club->url()->setQueryString( 'do', 'cropPhotoGetPhoto' )->csrf() );
			}
		) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Crop it */
			$image->cropToPoints( $values['photo_crop'][0], $values['photo_crop'][1], $values['photo_crop'][2], $values['photo_crop'][3] );
			
			/* Delete the existing */
			if ( $this->club->profile_photo )
			{
				try
				{
					\IPS\File::get( 'core_Clubs', $this->club->profile_photo )->delete();
				}
				catch ( \Exception $e ) { }
			}
						
			/* Save it */
			$croppedFilename = mb_substr( $original->originalFilename, 0, mb_strrpos( $original->originalFilename, '.' ) ) . '.cropped' . mb_substr( $original->originalFilename, mb_strrpos( $original->originalFilename, '.' ) );
			$cropped = \IPS\File::create( 'core_Clubs', $original->originalFilename, (string) $image );
			$this->club->profile_photo = (string) $cropped;
			$this->club->save();

			/* Edited member, so clear widget caches (stats, widgets that contain photos, names and so on) */
			\IPS\Widget::deleteCaches();
							
			/* Redirect */
			\IPS\Output::i()->redirect( $this->club->url() );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}
	
	/**
	 * Get photo for cropping
	 * If the photo is on a different domain to the JS that handles cropping,
	 * it will be blocked because of CORS. See notes in Cropper documentation.
	 *
	 * @return	void
	 */
	protected function cropPhotoGetPhoto()
	{
		\IPS\Session::i()->csrfCheck();
		$original = \IPS\File::get( 'core_Clubs', $this->club->profile_photo_uncropped );
		$headers = array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'inline', $original->filename ) );
		\IPS\Output::i()->sendOutput( $original->contents(), 200, \IPS\File::getMimeType( $original->filename ), $headers );
	}
	
	/**
	 * See Members
	 *
	 * @return	void
	 */
	protected function members()
	{
		/* Public groups have no member list */
		if ( $this->club->type === \IPS\Member\Club::TYPE_PUBLIC )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/H', 404, '' );
		}
		
		/* What members are we getting? */
		$filter = NULL;
		$statuses = array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER );
		$baseUrl = $this->club->url()->setQueryString( 'do', 'members' );
		if ( isset( \IPS\Request::i()->filter ) )
		{
			switch ( \IPS\Request::i()->filter )
			{
				case \IPS\Member\Club::STATUS_LEADER:
					$filter = \IPS\Member\Club::STATUS_LEADER;
					$statuses = array( \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER );
					$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_LEADER );
					break;
				
				case \IPS\Member\Club::STATUS_REQUESTED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_REQUESTED;
						$statuses = array( \IPS\Member\Club::STATUS_REQUESTED );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED );
					}
					break;
					
				case \IPS\Member\Club::STATUS_BANNED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_BANNED;
						$statuses = array( \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED );
					}
					break;
					
				case \IPS\Member\Club::STATUS_INVITED:
					if ( $this->club->isLeader() )
					{
						$filter = \IPS\Member\Club::STATUS_INVITED;
						$statuses = array( \IPS\Member\Club::STATUS_INVITED );
						$baseUrl = $baseUrl->setQueryString( 'filter', \IPS\Member\Club::STATUS_INVITED );
					}
					break;
			}
		}
		
		/* What are we sorting by? */
		$orderByClause = 'core_clubs_memberships.joined DESC';
		$sortBy = 'joined';
		if ( isset( \IPS\Request::i()->sortby ) and \IPS\Request::i()->sortby === 'name' )
		{
			$orderByClause = 'core_members.name ASC';
			$sortBy = 'name';
		}
		
		/* Sort out the offset */
		$perPage = 28;

		$activePage = isset( \IPS\Request::i()->page ) ? intval( \IPS\Request::i()->page ) : 1;

		if( $activePage < 1 )
		{
			$activePage = 1;
		}

		$offset = ( $activePage - 1 ) * $perPage;

		/* Fetch them */
		$members = $this->club->members( $statuses, array( $offset, $perPage ), $orderByClause, $this->club->isLeader() ? 3 : 1 );
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ceil( $members->count(TRUE) / $perPage ), $activePage, $perPage );
						
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'rows' => \IPS\Theme::i()->getTemplate( 'clubs' )->membersRows( $this->club, $members ), 'pagination' => $pagination, 'extraHtml' => '' ) );
		}
		else
		{
			\IPS\Output::i()->title = $this->club->name;
			\IPS\Output::i()->breadcrumb[] = array( $this->club->url()->setQueryString( 'do', 'members' ), \IPS\Member::loggedIn()->language()->addToStack('club_members') );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs' )->members( $this->club, $members, $pagination, $sortBy, $filter );
		}
	}
	
	/**
	 * Accept a join request
	 *
	 * @return	void
	 */
	protected function acceptRequest()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/3', 403, '' );
		}
		
		/* Check the member's request is pending */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_REQUESTED )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/4', 403, '' );
		}
		
		/* Add them */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_MEMBER, TRUE, \IPS\Member::loggedIn(), NULL, TRUE );
		$this->club->recountMembers();
		
		/* Notify the member */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_response', $this->club, array( $this->club, TRUE ) );
		$notification->recipients->attach( $member );
		$notification->send();
		
		/* Send a notification to any leaders besides ourselves */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_join', $this->club, array( $this->club, $member ), array( 'response' => TRUE ) );
		foreach ( $this->club->members( array( \IPS\Member\Club::STATUS_LEADER ), NULL, NULL, 2 ) as $leader )
		{
			$leader = \IPS\Member::constructFromData( $leader );
			if ( $leader->member_id != \IPS\Member::loggedIn()->member_id )
			{
				$notification->recipients->attach( $leader );
			}
		}
		if ( count( $notification->recipients ) )
		{
			$notification->send();
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'status' => 'approved' ) );
		}
		else
		{
			/* If other requests are pending, send us back, otherwise take us to the main member list */
			$url = $this->club->url()->setQueryString( 'do', 'members' );
			if ( count( $this->club->members( array( \IPS\Member\Club::STATUS_REQUESTED ) ) ) )
			{
				\IPS\Output::i()->redirect( $url->setQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED ) );
			}
			else
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
	}
	
	/**
	 * Decline a join request
	 *
	 * @return	void
	 */
	protected function declineRequest()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/F', 403, '' );
		}
		
		/* Check the member's request is pending */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_REQUESTED )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/G', 403, '' );
		}
		
		/* Decline them */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_DECLINED, TRUE, \IPS\Member::loggedIn() );
		
		/* Notify the member */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_response', $this->club, array( $this->club, FALSE ) );
		$notification->recipients->attach( $member );
		$notification->send();
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'status' => 'declined' ) );
		}
		else
		{
			/* If other requests are pending, send us back, otherwise take us to the main member list */
			$url = $this->club->url()->setQueryString( 'do', 'members' );
			if ( count( $this->club->members( array( \IPS\Member\Club::STATUS_REQUESTED ) ) ) )
			{
				\IPS\Output::i()->redirect( $url->SetQueryString( 'filter', \IPS\Member\Club::STATUS_REQUESTED ) );
			}
			else
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
	}
	
	/**
	 * Make a member a leader
	 *
	 * @return	void
	 */
	protected function makeLeader()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/6', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( !in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/7', 403, '' );
		}
		
		/* Promote */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_LEADER, TRUE );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Demote a member from being a leader
	 *
	 * @return	void
	 */
	protected function demoteLeader()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/8', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_LEADER )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/9', 403, '' );
		}
		
		/* Promote */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_MEMBER, TRUE );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Make a member a moderator
	 *
	 * @return	void
	 */
	protected function makeModerator()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/K', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( !in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_LEADER ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/L', 403, '' );
		}
		
		/* Promote */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_MODERATOR, TRUE );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Demote a member from being a moderator
	 *
	 * @return	void
	 */
	protected function demoteModerator()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/M', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( $this->club->memberStatus( $member ) != \IPS\Member\Club::STATUS_MODERATOR )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/N', 403, '' );
		}
		
		/* Promote */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_MEMBER, TRUE );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Remove a member
	 *
	 * @return	void
	 */
	protected function removeMember()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/C', 403, '' );
		}
		
		/* Get member */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		$status = $this->club->memberStatus( $member );
		if ( !in_array( $status, array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER ) ) ) 
		{
			\IPS\Output::i()->error( 'node_error', '2C350/9', 403, '' );
		}
		if ( $this->club->owner and $this->club->owner->member_id === $member->member_id )
		{
			\IPS\Output::i()->error( 'club_cannot_remove_owner', '2C350/E', 403, '' );
		}
		
		/* Remove */
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_BANNED, TRUE, \IPS\Member::loggedIn() );
		$this->club->recountMembers();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url()->setQueryString( 'do', 'members' ) );
	}
	
	/**
	 * Join
	 *
	 * @return	void
	 */
	protected function join()
	{
		/* Can we join? */
		if ( !$this->club->canJoin() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/I', 403, '' );
		}
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* If this is an open club, or the member was invited, or they have mod access anyway go ahead and add them */
		if ( $this->memberStatus === \IPS\Member\Club::STATUS_INVITED or $this->club->type === \IPS\Member\Club::TYPE_OPEN or \IPS\Member::loggedIn()->modPermission('can_access_all_clubs') )
		{
			$this->club->addMember( \IPS\Member::loggedIn(), \IPS\Member\Club::STATUS_MEMBER, TRUE, NULL, NULL, TRUE );
			$this->club->recountMembers();
			$notificationKey = 'club_join';
		}
		/* Otherwise, add the request */
		else
		{
			$this->club->addMember( \IPS\Member::loggedIn(), \IPS\Member\Club::STATUS_REQUESTED );
			$notificationKey = 'club_request';
		}
		
		/* Send a notification to any leaders */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), $notificationKey, $this->club, array( $this->club, \IPS\Member::loggedIn() ) );
		foreach ( $this->club->members( array( \IPS\Member\Club::STATUS_LEADER ), NULL, NULL, 2 ) as $member )
		{
			$notification->recipients->attach( \IPS\Member::constructFromData( $member ) );
		}
		$notification->send();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url() );
	}
	
	/**
	 * Leave
	 *
	 * @return	void
	 */
	protected function leave()
	{
		/* Can we leave? */
		if ( !in_array( $this->club->memberStatus( \IPS\Member::loggedIn() ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER ) ) or ( $this->club->owner and $this->club->owner->member_id == \IPS\Member::loggedIn()->member_id ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/S', 403, '' );
		}
		
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Leave */
		$this->club->removeMember( \IPS\Member::loggedIn() );
		$this->club->recountMembers();
		$this->club->save();
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ) );
	}
	
	/**
	 * Invite Members
	 *
	 * @return	void
	 */
	protected function invite()
	{
		if ( !$this->club->canInvite() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/5', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'club_send_invitations' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Member( 'members', NULL, TRUE, array( 'multiple' => NULL ) ) );
		
		if ( $values = $form->values() )
		{
			$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_invitation', $this->club, array( $this->club, \IPS\Member::loggedIn() ), array( 'invitedBy' => \IPS\Member::loggedIn()->member_id ) );
			foreach ( $values['members'] as $member )
			{
				if ( $member instanceof \IPS\Member )
				{
					$memberStatus = $this->club->memberStatus( $member );
					if ( !$memberStatus or in_array( $memberStatus, array( \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_REQUESTED, \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) )
					{
						$this->club->addMember( $member, \IPS\Member\Club::STATUS_INVITED, TRUE, NULL, \IPS\Member::loggedIn(), TRUE );
						$notification->recipients->attach( $member );
					}
				}
			}
			$notification->send();
			
			\IPS\Output::i()->redirect( $this->club->url(), 'club_notifications_sent' );
		}
		
		\IPS\Output::i()->title = $this->club->name;
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}
	
	/**
	 * Re-invite a banned memer
	 *
	 * @return	void
	 */
	protected function reInvite()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !$this->club->isLeader() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/J', 403, '' );
		}
		
		/* Check the member needs to be reinvited */
		$member = \IPS\Member::load( \IPS\Request::i()->member );
		if ( !in_array( $this->club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C350/K', 403, '' );
		}
		
		/* Add them */
		$this->club->removeMember( $member );
		$this->club->addMember( $member, \IPS\Member\Club::STATUS_INVITED, FALSE, NULL, \IPS\Member::loggedIn() );
		$this->club->recountMembers();
		
		/* Notify the member */
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_invitation', $this->club, array( $this->club, \IPS\Member::loggedIn() ), array( 'invitedBy' => \IPS\Member::loggedIn()->member_id ) );
		$notification->recipients->attach( $member );
		$notification->send();
				
		/* If other requests are banned, send us back, otherwise take us to the main member list */
		$url = $this->club->url()->setQueryString( 'do', 'members' );
		if ( count( $this->club->members( array( \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) ) )
		{
			\IPS\Output::i()->redirect( $url->SetQueryString( 'filter', \IPS\Member\Club::STATUS_BANNED ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $url );
		}
	}
	
	/**
	 * Feature
	 *
	 * @return	void
	 */
	protected function feature()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->modPermission('can_manage_featured_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/Q', 403, '' );
		}
		
		/* Feature */
		$this->club->featured = TRUE;
		$this->club->save();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url() );
	}
	
	/**
	 * Unfeature
	 *
	 * @return	void
	 */
	protected function unfeature()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->modPermission('can_manage_featured_clubs') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/Q', 403, '' );
		}
		
		/* Unfeature */
		$this->club->featured = FALSE;
		$this->club->save();
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->club->url() );
	}
	
	/**
	 * Approve/Deny
	 *
	 * @return	void
	 */
	protected function approve()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->modPermission('can_manage_featured_clubs') or $this->club->approved or !\IPS\Settings::i()->clubs_require_approval )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/U', 403, '' );
		}
		
		/* Approve... */
		if ( \IPS\Request::i()->approved )
		{
			$this->club->approved = TRUE;
			$this->club->save();
			
			\IPS\Output::i()->redirect( $this->club->url() );
		}
		
		/* ... or delete */
		else
		{
			$this->club->delete();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ) );
		}
	}
	
	/**
	 * Create a node
	 *
	 * @return	void
	 */
	protected function nodeForm()
	{
		/* Permission check */
		$class = \IPS\Request::i()->type;
		if ( !$this->club->isLeader() or !in_array( $class, \IPS\Member\Club::availableNodeTypes( \IPS\Member::loggedIn() ) ) or ( \IPS\Settings::i()->clubs_require_approval and !$this->club->approved ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C350/T', 403, '' );
		}
		
		/* Load if editing */
		if ( isset( \IPS\Request::i()->node ) )
		{
			try
			{
				$node = $class::load( \IPS\Request::i()->node );
				$club = $node->club();
				if ( !$club or $club->id !== $this->club->id )
				{
					throw new \Exception;
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C350/O', 404, '' );
			}
		}
		else
		{
			$node = new $class;
		}
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$node->clubForm( $form );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$node->saveClubForm( $this->club, $values );
			\IPS\Output::i()->redirect( $this->club->url() );
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('club_create_node');
		\IPS\Output::i()->output = \IPS\Request::i()->isAjax() ? $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) ) : $form;
	}
	
	/* !Cover Photo */
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	protected function _coverPhotoStorageExtension()
	{
		return 'core_Clubs';
	}
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @return	void
	 */
	protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo )
	{
		$this->club->cover_photo = (string) $photo->file;
		$this->club->cover_offset = (int) $photo->offset;
		$this->club->save();
	}
	
	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	protected function _coverPhotoGet()
	{
		return $this->club->coverPhoto();
	}
	
	/**
	 * Embed
	 *
	 * @return	void
	 */
	protected function embed()
	{
		$title = \IPS\Member::loggedIn()->language()->addToStack( 'error_title' );
		
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->id );
			if ( !$club->canView() )
			{
				$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedNoPermission();
			}
			else
			{
				$output = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->embedClub( $club );
			}
		}
		catch( \Exception $e )
		{
			$output = \IPS\Theme::i()->getTemplate( 'embed', 'core', 'global' )->embedUnavailable();
		}
		
		/* Make sure our iframe contents get the necessary elements and JS */
		$js = array(
			\IPS\Output::i()->js( 'js/commonEmbedHandler.js', 'core', 'interface' ),
			\IPS\Output::i()->js( 'js/internalEmbedHandler.js', 'core', 'interface' )
		);
		\IPS\Output::i()->base = '_parent';

		/* We need to keep any embed.css files that have been specified so that we can re-add them after we re-fetch the css framework */
		$embedCss = array();
		foreach( \IPS\Output::i()->cssFiles as $cssFile )
		{
			if( \mb_stristr( $cssFile, 'embed.css' ) )
			{
				$embedCss[] = $cssFile;
			}
		}

		/* We need to reset the included CSS files because by this point the responsive files are already in the output CSS array */
		\IPS\Output::i()->cssFiles = array();
		\IPS\Theme::i()->settings = array_merge( \IPS\Theme::i()->settings, array( 'responsive' => FALSE ) );
		\IPS\Dispatcher\Front::baseCss();
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, $embedCss );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/embeds.css', 'core', 'front' ) );

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->embedInternal( $output, $js ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
	}
}
