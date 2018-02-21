<?php
/**
 * @brief		Admin CP Member Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		08 Apr 2013
 */

namespace IPS\core\extensions\core\MemberForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Member Form
 */
class _BasicInformation
{
	/**
	 * Action Buttons
	 *
	 * @param	\IPS\Member	$member	The Member
	 * @return	array
	 */
	public function actionButtons( $member )
	{
		$return = array();

		/* If we can sign in as, flag as spammer, merge, ban or delete content */
		if ( ( \IPS\Member::loggedIn()->member_id != $member->member_id AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_login' ) AND !$member->isBanned() ) || ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit_admin' ) or !$member->isAdmin() ) AND $member->member_id != \IPS\Member::loggedIn()->member_id ) || \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'members_merge' ) || ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_ban' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_ban_admin' ) or !$member->isAdmin() ) AND $member->member_id != \IPS\Member::loggedIn()->member_id ) || ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_delete' ) ) )
		{
			$return['actions'] = array(
				'title'		=> 'member_account_actions',
				'icon'		=> 'user',
				'primary'	=> TRUE,
				'link'		=> '#',
				'menu'		=> array()
			);

			if ( \IPS\Member::loggedIn()->member_id != $member->member_id AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_login' ) AND !$member->isBanned() )
			{
				$return['actions']['menu'][] = array(
					'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'login_as_x', FALSE, array( 'sprintf' => array( $member->name ) ) ),
					'icon'		=> 'key',
					'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=login&id={$member->member_id}" ),
					'class'		=> '',
					'target'    => '_blank'
				);
			}

			/* Flag */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit_admin' ) or !$member->isAdmin() ) AND $member->member_id != \IPS\Member::loggedIn()->member_id )
			{
				$return['actions']['menu'][] = array(
					'title'		=> $member->members_bitoptions['bw_is_spammer'] ? 'spam_unflag' : 'spam_flag',
					'icon'		=> 'flag',
					'link'		=>  \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=spam&id=' )->setQueryString( array( 'id' => $member->member_id, 'status' => $member->members_bitoptions['bw_is_spammer'] ? 0 : 1 ) ),
					'class'		=> ''
				);
			}

			/* Merge */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'members_merge' ) )
			{
				$return['actions']['menu'][] = array(
					'title'		=> 'merge',
					'icon'		=> 'level-up',
					'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=merge&id={$member->member_id}" ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('merge') )
				);
			}

			/* Ban */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_ban' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_ban_admin' ) or !$member->isAdmin() ) AND $member->member_id != \IPS\Member::loggedIn()->member_id )
			{
				$return['actions']['menu'][] = array(
					'title'		=> $member->temp_ban ? 'adjust_ban' : 'ban',
					'icon'		=> 'times',
					'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=ban&id={$member->member_id}" ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => $member->temp_ban ? \IPS\Member::loggedIn()->language()->addToStack('adjust_ban') : \IPS\Member::loggedIn()->language()->addToStack('ban') )
				);
			}

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_delete' ) )
			{
				$return['actions']['menu'][] = array(
					'title'		=> 'member_delete_content',
					'icon'		=> 'trash-o',
					'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=deleteContent&id={$member->member_id}" ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('member_delete_content') )
				);
			}
		}

		$return['view'] = array(
			'title'		=> 'profile_view_profile',
			'icon'		=> 'search',
			'link'		=> $member->url(),
			'class'		=> '',
			'target'    => '_blank'
		);

		if ( \IPS\Settings::i()->warn_on AND \IPS\Member::loggedIn()->modPermission('mod_see_warn') )
		{
			$return['warnings'] = array(
				'title'		=> 'modcp_view_warnings',
				'icon'		=> 'exclamation-triangle',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&id=" . $member->member_id, 'front', 'warn_list', array( $member->members_seo_name ) ),
				'target'    => '_blank'
			);
		}

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_history' ) )
		{
			$return['history'] = array(
					'title'		=> 'member_history',
					'icon'		=> 'history',
					'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=history&id=" . $member->member_id )
			);
		}
		
		return $return;
	}
	
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member				$member	Existing Member
	 * @return	void
	 */
	public function process( &$form, $member )
	{
		/* Username */
		$form->addHeader('member_basic_information');
		$form->add( new \IPS\Helpers\Form\Text( 'ips_member_name', $member->name, TRUE, array( 'accountUsername' => $member ), NULL, NULL, '<a data-ipsDialog data-ipsDialog-title="' . \IPS\Member::loggedIn()->language()->addToStack( 'dname_history' ) . '" data-ipsDialog-url="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=viewDnameHistory&id={$member->member_id}" ) . '" href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=viewDnameHistory&id={$member->member_id}" ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'view_username_history' ) . '</a>' ) );
		
		/* Password */
		$form->add( new \IPS\Helpers\Form\Custom( 'password', NULL, FALSE, array(
			'getHtml'	=> function( $element ) use ( $member )
			{
				return \IPS\Theme::i()->getTemplate('members')->changePassword( $element->name, $member->member_id );
			}
		) ) );
		
		/* Email */
		$form->add( new \IPS\Helpers\Form\Email( 'ips_address_mail', $member->email, TRUE, array( 'accountEmail' => $member ) ) );
		
		/* Group - if this member is an admin, we need the "Can move admins into other groups" restriction */
		if ( !$member->isAdmin() or \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin1' ) )
		{
			/* If we are editing ourselves, we can only move ourselves into a group with the same restrictions as what we have now... */
			if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				/* Get the row... */
				try
				{
					$currentRestrictions = \IPS\Db::i()->select( 'row_perm_cache', 'core_admin_permission_rows', array( 'row_id=? AND row_id_type=?', $member->member_group_id, 'group' ) )->first();
					$availableGroups = array();
					foreach( \IPS\Db::i()->select( 'row_id', 'core_admin_permission_rows', array( 'row_perm_cache=? AND row_id_type=?', $currentRestrictions, 'group' ) ) AS $groupId )
					{
						$availableGroups[ $groupId ] = \IPS\Member\Group::load( $groupId );
					}
				}
				/* If we don't have a row in core_admin_permission_rows, we're an admin as a member rather than apart of our group, so we can be moved anywhere and it won't matter because member-level restrictions override group-level */
				catch ( \UnderflowException $e )
				{
					$availableGroups = \IPS\Member\Group::groups( TRUE, FALSE );
				}
			}
			/* Not editing ourselves - do we have the Can move members into admin groups"" restriction? */
			else
			{
				$availableGroups = \IPS\Member\Group::groups( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin2' ), FALSE );
			}
			
			$form->add( new \IPS\Helpers\Form\Select( 'group', $member->member_group_id, TRUE, array( 'options' => $availableGroups, 'parse' => 'normal' ) ) );
			$form->add( new \IPS\Helpers\Form\Select( 'secondary_groups', array_filter( explode( ',', $member->mgroup_others ) ), FALSE, array( 'options' => \IPS\Member\Group::groups( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin2' ), FALSE ), 'multiple' => TRUE, 'parse' => 'normal' ) ) );
		}
		
		/* Counts */
		$confirmButtons = json_encode( array(
			'yes'		=>	\IPS\Member::loggedIn()->language()->addToStack('yes'),
			'no'		=>	\IPS\Member::loggedIn()->language()->addToStack('recount_all'),
			'cancel'	=>	\IPS\Member::loggedIn()->language()->addToStack('cancel'),
		) );
		$form->addHeader('member_counts');
		$form->add( new \IPS\Helpers\Form\Number( 'member_content_items', $member->member_posts, FALSE, array(), NULL, NULL, '<a data-confirm data-confirmType="verify" data-confirmButtons=\'' . $confirmButtons . '\' data-confirmSubMessage="' . \IPS\Member::loggedIn()->language()->addToStack('member_content_items_recount') . '" href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=recountContent&id={$member->member_id}") . '">' . \IPS\Member::loggedIn()->language()->addToStack('recount') . '</a>' ) );
		if ( \IPS\Settings::i()->reputation_enabled )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'member_reputation', $member->pp_reputation_points, FALSE, array( 'min' => NULL ), NULL, NULL, '<a data-confirm data-confirmType="verify" data-confirmButtons=\'' . $confirmButtons . '\' data-confirmSubMessage="' . \IPS\Member::loggedIn()->language()->addToStack('member_reputation_recount') . '" href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=recountReputation&id={$member->member_id}") . '">' . \IPS\Member::loggedIn()->language()->addToStack('recount') . '</a>' . ' &middot; <a data-confirm href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=removeReputation&id={$member->member_id}&type=given") . '">' . \IPS\Member::loggedIn()->language()->addToStack('reputation_remove_given') . '</a>' . ' &middot; <a data-confirm href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=removeReputation&id={$member->member_id}&type=received") . '">' . \IPS\Member::loggedIn()->language()->addToStack('reputation_remove_received') . '</a>' ) );
		}
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member			$member	The member
	 * @return	void
	 */
	public function save( $values, &$member )
	{
		if ( $values['ips_member_name'] != $member->name )
		{
			foreach ( \IPS\Login::handlers( TRUE ) as $handler )
			{
				try
				{
					$handler->changeUsername( $member, $member->name, $values['ips_member_name'] );
				}
				catch( \BadMethodCallException $e ) {}
			}
		}
		
		if ( $values['ips_address_mail'] != $member->email )
		{
			$oldEmail = $member->email;
			foreach ( \IPS\Login::handlers( TRUE ) as $handler )
			{
				try
				{
					$handler->changeEmail( $member, $oldEmail, $values['ips_address_mail'] );
				}
				catch( \BadMethodCallException $e ) {}
			}
			$member->invalidateSessionsAndLogins( TRUE, \IPS\Session::i()->id );
		}
		
		if ( !$member->isAdmin() or \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin1' ) )
		{
			$member->member_group_id		= $values['group'];
			$member->mgroup_others			= implode( ',', $values['secondary_groups'] );
		}
		$member->member_posts					= $values['member_content_items'];
		if ( \IPS\Settings::i()->reputation_enabled )
		{
			$member->pp_reputation_points	= $values['member_reputation'];
		}
		
		if ( $values['password'] )
		{
			$member->members_pass_salt	= $member->generateSalt();
			$member->members_pass_hash	= $member->encryptedPassword( $values['password'] );
			$member->invalidateSessionsAndLogins( TRUE, \IPS\Session::i()->id );
		}
		
		/* Reset Profile Complete flag in case this was an optional step */
		$member->members_bitoptions['profile_completed'] = FALSE;
	}
}