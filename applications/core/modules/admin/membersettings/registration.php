<?php
/**
 * @brief		Registration
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Jul 2013
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Registration
 */
class _registration extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'registration_manage' );
		parent::execute();
	}

	/**
	 * General Member Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = $this->getSettingsForm();

		if ( $values = $form->values() )
		{
			if ( isset( $values['allowed_reg_email'] ) and is_array( $values['allowed_reg_email'] ) )
			{
				$values['allowed_reg_email'] = implode( ',', $values['allowed_reg_email'] );
			}
			
			if ( $values['use_coppa'] OR $values['minimum_age'] )
			{
				$values['quick_register'] = 0;
			}

			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__general_settings' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('membersettings_registration_title');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'membersettings_registration_title', $form );
	}

	/**
	 * Get the settings form
	 *
	 * @return \IPS\Helpers\Form
	 */
	protected function getSettingsForm()
	{
		$form = new \IPS\Helpers\Form;

		$form->addHeader('registration');
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_reg', \IPS\Settings::i()->allow_reg, FALSE, array( 'togglesOn' => array( 'quick_register', 'new_reg_notify', 'reg_auth_type', 'form_header_coppa', 'use_coppa' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'quick_register', \IPS\Settings::i()->quick_register, FALSE, array(), NULL, NULL, NULL, 'quick_register' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'new_reg_notify', \IPS\Settings::i()->new_reg_notify, FALSE, array(), NULL, NULL, NULL, 'new_reg_notify' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'reg_auth_type', \IPS\Settings::i()->reg_auth_type, TRUE, array(
			'options'	=> array( 'user' => 'reg_auth_type_user', 'admin' => 'reg_auth_type_admin', 'admin_user' => 'reg_auth_type_admin_user', 'none' => 'reg_auth_type_none' ),
			'toggles'	=> array( 'user' => array( 'validate_day_prune' ), 'admin_user' => array( 'validate_day_prune' ) )
		), NULL, NULL, NULL, 'reg_auth_type' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'validate_day_prune', \IPS\Settings::i()->validate_day_prune, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), \IPS\Member::loggedIn()->language()->addToStack('days'), 'validate_day_prune' ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'allowed_reg_email', explode( ',', \IPS\Settings::i()->allowed_reg_email ), FALSE, array(), NULL, NULL, NULL ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'updates_consent_default', \IPS\Settings::i()->updates_consent_default, TRUE, array(
			'options'	=> array( 'enabled' => 'updates_consent_enabled', 'disabled' => 'updates_consent_disabled' ),
		), NULL, NULL, NULL, 'updates_consent_default' ) );

		$form->addHeader('age_requirements');
		$form->add( new \IPS\Helpers\Form\Number( 'minimum_age', \IPS\Settings::i()->minimum_age, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'any_age', 'unlimitedToggles' => array( 'quick_register' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'use_coppa', \IPS\Settings::i()->use_coppa, FALSE, array( 'togglesOn' => array( 'coppa_fax', 'coppa_address' ), 'togglesOff' => array( 'quick_register' ) ), NULL, NULL, NULL, 'use_coppa' ) );
		$form->add( new \IPS\Helpers\Form\Tel( 'coppa_fax', \IPS\Settings::i()->coppa_fax, FALSE, array(), NULL, NULL, NULL, 'coppa_fax' ) );
		$form->add( new \IPS\Helpers\Form\Address( 'coppa_address', \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->coppa_address ), FALSE, array(), NULL, NULL, NULL, 'coppa_address' ) );

		return $form;
	}
}