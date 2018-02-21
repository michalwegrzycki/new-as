<?php
/**
 * @brief		Member History Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Dec 2016
 */

namespace IPS\core\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _History
{
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		\IPS\Db::i()->delete( 'core_member_history', array( 'log_member=?', $member->member_id ) );
	}

	/**
	 * Email address is changed
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param 	string		$new	New email address
	 * @param 	string		$old	Old email address
	 * @return	void
	 */
	public function onEmailChange( $member, $new, $old )
	{
		$member->logHistory( 'core', 'email_change', array( 'old' => $old, 'new' => $new ) );
	}

	/**
	 * Member is merged with another member
	 *
	 * @param	\IPS\Member	$member		Member being kept
	 * @param	\IPS\Member	$member2	Member being removed
	 * @return	void
	 */
	public function onMerge( $member, $member2 )
	{
		\IPS\Db::i()->update( 'core_member_history', array( 'log_member' => $member->member_id ), array( 'log_member=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'core_member_history', array( 'log_by' => $member->member_id ), array( 'log_by=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );

		$member->logHistory( 'core', 'merged', array( 'old' => $member2->name ) );
	}

	/**
	 * Password is changed
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param 	string		$new	New password
	 * @return	void
	 */
	public function onPassChange( $member, $new )
	{
		$member->logHistory( 'core', 'password_change', array( 'password' => '' ) );
	}

	/**
	 * Member is flagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onSetAsSpammer( $member )
	{
		$member->logHistory( 'core', 'spammer', array( 'set' => TRUE ) );
	}

	/**
	 * Member is unflagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onUnSetAsSpammer( $member )
	{
		$member->logHistory( 'core', 'spammer', array( 'set' => FALSE ) );
	}
}