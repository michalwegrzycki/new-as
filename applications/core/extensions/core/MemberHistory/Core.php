<?php
/**
 * @brief		MemberHistory: Core
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Jan 2017
 */

namespace IPS\core\extensions\core\MemberHistory;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member History: Core
 */
class _Core
{
	/**
	 * Return the valid member history log types
	 *
	 * @return array
	 */
	public function getTypes()
	{
		return array(
			'display_name',
			'email_change',
			'group_promotion',
			'group_promotion_o',
			'member_group_id',
			'merged',
			'mfa',
			'mgroup_others',
			'password_change',
			'spammer',
			'social_account',
			'warning',
			'admin_mails',
			'terms_acceptance'
		);
	}

	/**
	 * Parse LogData column
	 *
	 * @param	string		$value		column value
	 * @param	array		$row		entire log row
	 * @return	string
	 */
	public function parseLogData( $value, $row )
	{
		$jsonValue = json_decode( $value, TRUE );

		switch( $row['log_type'] )
		{
			case 'display_name':
				$oldDisplayName = $jsonValue['old'] ?: \IPS\Member::loggedIn()->language()->addToStack('history_unknown'); // Old display name records may be missing this
				return \IPS\Member::loggedIn()->language()->addToStack('history_name_changed', FALSE, array( 'sprintf' => array( $oldDisplayName, $jsonValue['new'] ) ) );
				break;
			case 'email_change':
				$newEmailAddress = $jsonValue['new'] ?: \IPS\Member::loggedIn()->language()->addToStack('history_unknown'); // Previous customer history didn't log what it was changed to
				return \IPS\Member::loggedIn()->language()->addToStack('history_email_changed', FALSE, array( 'sprintf' => array( $jsonValue['old'], $newEmailAddress ) ) );
				break;
			case 'group_promotion':
			case 'group_promotion_o':
				if( $row['log_type'] == 'group_promotion' )
				{
					try
					{
						$groupName = \IPS\Member\Group::load( $jsonValue['new'] )->name;
					}
					catch( \OutOfRangeException $e)
					{
						$groupName = \IPS\Member::loggedIn()->language()->addToStack('history_deleted_group');
					}
				}
				else
				{
					$mgroups = array();

					foreach( explode( ',', $jsonValue['new'] ) as $groupId )
					{
						try
						{
							$mgroups[] = \IPS\Member\Group::load( trim( $groupId ) )->name;
						}
						catch( \OutOfRangeException $e)
						{
							$mgroups[] = \IPS\Member::loggedIn()->language()->addToStack('history_deleted_group');
						}
					}

					$groupName = \IPS\Member::loggedIn()->language()->formatList( $mgroups );
				}

				try
				{
					$reason = \IPS\Member\GroupPromotion::load( $jsonValue['reason'] )->_title;
				}
				catch( \OutOfRangeException $e )
				{
					$reason = \IPS\Member::loggedIn()->language()->addToStack('history_deleted_grouppromotion');
				}

				return \IPS\Member::loggedIn()->language()->addToStack('history_group_promotion', FALSE, array( 'sprintf' => array( $groupName, $reason ) ) );
				break;
			case 'member_group_id':
				try
				{
					$groupName = \IPS\Member\Group::load( $jsonValue['new'] )->name;
				}
				catch( \OutOfRangeException $e)
				{
					$groupName = \IPS\Member::loggedIn()->language()->addToStack('history_deleted_group');
				}

				return \IPS\Member::loggedIn()->language()->addToStack('history_member_group', FALSE, array( 'sprintf' => array( $groupName ) ) );
				break;
			case 'merged':
				return \IPS\Member::loggedIn()->language()->addToStack('history_member_merged', FALSE, array( 'sprintf' => array( $jsonValue['old'] ) ) );
				break;
			case 'mfa':
				$handlerName = \IPS\Member::loggedIn()->language()->addToStack('mfa_' . $jsonValue['handler'] . '_title');
				if( $jsonValue['enable'] === TRUE )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_mfa_enabled', FALSE, array( 'sprintf' => array( $handlerName ) ) );
				}

				if( isset( $jsonValue['optout'] ) AND $jsonValue['optout'] === TRUE )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_mfa_optout');
				}
				
				return \IPS\Member::loggedIn()->language()->addToStack('history_mfa_disabled', FALSE, array( 'sprintf' => array( $handlerName ) ) );
				break;
			case 'mgroup_others':
				$groups = explode( ',', $jsonValue['new'] );

				if( count( $groups ) )
				{
					array_walk( $groups, function( &$id )
					{
						try
						{
							$id = \IPS\Member\Group::load( $id )->name;
						}
						catch( \OutOfRangeException $e)
						{
							$id = \IPS\Member::loggedIn()->language()->addToStack('history_deleted_group');
						}
					});
				}
				else
				{
					$groups = array( \IPS\Member::loggedIn()->language()->addToStack('history_no_group') );
				}

				return \IPS\Member::loggedIn()->language()->addToStack('history_member_secondary_groups', FALSE, array( 'sprintf' => array( implode( ', ', $groups ) ) ) );

				break;
			case 'password_change':
				return \IPS\Member::loggedIn()->language()->addToStack('history_password_changed');
				break;
			case 'spammer':
				if( $jsonValue['set'] === TRUE )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_flagged_spammer');
				}

				return \IPS\Member::loggedIn()->language()->addToStack('history_unflagged_spammer');
				break;
			case 'social_account':
				$handlerName = \IPS\Member::loggedIn()->language()->addToStack('login_handler_' . ucfirst( $jsonValue['service'] ) );
				if( $jsonValue['linked'] === TRUE )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_connected_social', FALSE, array( 'sprintf' => array( $handlerName ) ) );
				}
				elseif( isset( $jsonValue['registered'] ) )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_registered_social', FALSE, array( 'sprintf' => array( $handlerName ) ) );
				}

				return \IPS\Member::loggedIn()->language()->addToStack('history_disconnected_social', FALSE, array( 'sprintf' => array( $handlerName ) ) );
				break;
			case 'warning':
				try
				{
					$warning = \IPS\core\Warnings\Warning::load( $jsonValue['wid'] );
					return \IPS\Member::loggedIn()->language()->addToStack('history_received_warning_link', FALSE, array( 'sprintf' => array( $warning->url() ) ) );
				}
				catch( \OutOfRangeException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_received_warning');
				}
				break;
			case 'admin_mails':
				if( $jsonValue['enabled'] === TRUE )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_enabled_admin_mails');
				}

				return \IPS\Member::loggedIn()->language()->addToStack('history_disabled_admin_mails');
				break;
			case 'terms_acceptance':
				if( $jsonValue['type'] == 'privacy' )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_terms_accepted_privacy');
				}

				if( $jsonValue['type'] == 'terms' )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('history_terms_accepted_terms');
				}
				break;
		}

		return $value;
	}

	/**
	 * Parse LogType column
	 *
	 * @param	string		$value		column value
	 * @param	array		$row		entire log row
	 * @return	string
	 */
	public function parseLogType( $value, $row )
	{
		return \IPS\Theme::i()->getTemplate( 'members', 'core' )->logType( $value );
	}
}