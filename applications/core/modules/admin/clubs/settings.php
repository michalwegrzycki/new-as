<?php
/**
 * @brief		Clubs Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Feb 2017
 */

namespace IPS\core\modules\admin\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs Settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_settings_manage' );
		parent::execute();
	}

	/**
	 * Manage Club Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_enabled_setting', \IPS\Settings::i()->clubs, FALSE, array( 'togglesOn' => array( 'clubs_default_sort', 'clubs_header', 'clubs_locations', 'clubs_modperms', 'clubs_require_approval' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'clubs_default_sort', \IPS\Settings::i()->clubs_default_sort, FALSE, array( 'options' => array(
			'last_activity'		=> 'clubs_sort_last_activity',
			'members'			=> 'clubs_sort_members',
			'content'			=> 'clubs_sort_content',
			'created'			=> 'clubs_sort_created',
			'name'				=> 'clubs_sort_name'
		) ), NULL, NULL, NULL, 'clubs_default_sort' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'clubs_header', \IPS\Settings::i()->clubs_header, FALSE, array( 'options' => array(
			'full'		=> 'clubs_header_full',
			'sidebar'	=> 'clubs_header_sidebar',
		) ), NULL, NULL, NULL, 'clubs_header' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_locations', \IPS\Settings::i()->clubs_locations, FALSE, array(), NULL, NULL, NULL, 'clubs_locations' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'clubs_modperms', explode( ',', \IPS\Settings::i()->clubs_modperms ), FALSE, array( 'options' => array(
			'pin'				=> 'club_modperm_pin',
			'unpin'				=> 'club_modperm_unpin',
			'edit'				=> 'club_modperm_edit',
			'hide'				=> 'club_modperm_hide',
			'unhide'			=> 'club_modperm_unhide',
			'view_hidden'		=> 'club_modperm_view_hidden',
			'future_publish'	=> 'club_modperm_future_publish',
			'view_future'		=> 'club_modperm_view_future',
			'move'				=> 'club_modperm_move',
			'lock'				=> 'club_modperm_lock',
			'unlock'			=> 'club_modperm_unlock',
			'reply_to_locked'	=> 'club_modperm_reply_to_locked',
			'delete'			=> 'club_modperm_delete',
			'split_merge'		=> 'club_modperm_split_merge',
		) ), NULL, NULL, NULL, 'clubs_modperms' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_require_approval', \IPS\Settings::i()->clubs_require_approval, FALSE, array(), NULL, NULL, NULL, 'clubs_require_approval' ) );
		
		if ( $values = $form->values() )
		{
			$values['clubs'] = $values['clubs_enabled_setting'];
			unset( $values['clubs_enabled_setting'] );
			$values['clubs_modperms'] = implode( ',', $values['clubs_modperms'] );			
			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplog__club_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=clubs&controller=clubs') );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_clubs_settings');
		\IPS\Output::i()->output = $form;
	}
}