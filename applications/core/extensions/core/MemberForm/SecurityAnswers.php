<?php
/**
 * @brief		Admin CP Member Form: MFA
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Sep 2016
 */

namespace IPS\core\extensions\core\MemberForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Member Form: MFA
 */
class _SecurityAnswers // It's called this for backwards compatibility from when we just had security answers
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member				$member	Existing Member
	 * @return	void
	 */
	public function process( &$form, $member )
	{
		/* ACP restriction check */
		if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_mfa' ) )
		{
			return;
		}
		
		/* Get all the fields we'll need */
		$fields = array();
		$optOutToggles = array();
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( $member ) )
			{
				foreach ( $handler->acpConfiguration( $member ) as $id => $field )
				{
					$fields[] = $field;
				}
				$optOutToggles[] = "mfa_{$key}_title";
			}
		}
		
		/* If we don't have have any, we need don't to display this tab */
		if ( !count( $fields ) )
		{
			return;
		}
	
		/* We need the opt-out field if the user is in a group which can opt out */
		if ( \IPS\Settings::i()->mfa_required_groups != '*' and !$member->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'mfa_opt_out_admin', $member->members_bitoptions['security_questions_opt_out'], FALSE, array( 'togglesOff' => $optOutToggles ) ) );
		}
		
		/* Now add all the other fields */
		foreach ( $fields as $id => $field )
		{
			if ( $field instanceof \IPS\Helpers\Form\Matrix )
			{
				$form->addMatrix( $field->id, $field );
			}
			else
			{
				$form->add( $field );
			}
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
		/* ACP restriction check */
		if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_mfa' ) )
		{
			return;
		}
		
		/* Reset the failure count and unlock if necessary */
		$member->failed_mfa_attempts = 0;
		$mfaDetails = $member->mfa_details;
		if ( isset( $mfaDetails['_lockouttime'] ) )
		{
			unset( $mfaDetails['_lockouttime'] );
			$member->mfa_details = $mfaDetails;
		}
		
		/* Did we opt out? */
		if ( isset( $values['mfa_opt_out_admin'] ) )
		{
			/* Opt-Out: Disable all handlers */
			if ( $values['mfa_opt_out_admin'] )
			{				
				$member->members_bitoptions['security_questions_opt_out'] = TRUE;
				
				foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
				{
					$handler->disableHandlerForMember( $member );
				}
				$member->save();
			}
			/* Opt-In */
			else
			{
				$member->members_bitoptions['security_questions_opt_out'] = FALSE;
			}
		}
		
		/* Save each of the handlers */
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( $member ) )
			{
				$handler->acpConfigurationSave( $member, $values );
			}
		}
	}
}