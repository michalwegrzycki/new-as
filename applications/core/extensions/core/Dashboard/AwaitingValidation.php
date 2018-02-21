<?php
/**
 * @brief		Dashboard extension: Users Awaiting Validation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Aug 2013
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Failed Admin Logins
 */
class _AwaitingValidation
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		$users = array();
		
		foreach (
			\IPS\Db::i()->select(
				"*",
				'core_validating',
				array( 'user_verified=?', TRUE ),
				'entry_date desc',
				array( 0, 10 )
			)->join(
					'core_members',
					'core_validating.member_id=core_members.member_id'
			) as $user
		)
		{
			$users[ $user['member_id'] ] = \IPS\Member::constructFromData( $user );
		}

		return \IPS\Theme::i()->getTemplate( 'dashboard' )->awaitingValidation( $users );
	}
}