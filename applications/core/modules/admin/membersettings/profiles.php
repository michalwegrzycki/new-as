<?php
/**
 * @brief		Profile Fields and Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Apr 2013
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile Fields and Settings
 */
class _profiles extends \IPS\Node\Controller
{
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\core\ProfileFields\Group';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'profilefields_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		$activeTab = \IPS\Request::i()->tab ?: 'fields';
		$activeTabContents = '';
		$tabs = array( 'fields' => 'profile_fields' );
		
		/* Add a tab for settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'profiles_manage' ) )
		{
			$tabs['settings'] = 'profile_settings';
			$tabs['completion'] = 'profile_completion';
		}
		
		$method = '_tab' . ucwords( $activeTab );
		if ( method_exists( $this, $method ) )
		{
			$activeTabContents = (string) $this->$method();
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('module__core_profile');
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=membersettings&controller=profiles" ) );
		}
	}
	
	/**
	 * Fields Tab
	 *
	 * @return	string
	 */
	protected function _tabFields()
	{
		parent::manage();
		return \IPS\Output::i()->output;
	}
	
	/**
	 * Settings Tab
	 *
	 * @return	\IPS\Helpers\Form
	 */
	protected function _tabSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'profiles_manage' );
		
		$form = new \IPS\Helpers\Form;
		
		$form->addHeader( 'photos' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_gravatars', \IPS\Settings::i()->allow_gravatars ) );

		if( \IPS\Image::canWriteText() )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'letter_photos', \IPS\Settings::i()->letter_photos, FALSE, array( 'options' => array( 'default' => 'letterphoto_default', 'letters' => 'letterphoto_letters' ) ) ) );
		}

		$form->addHeader( 'usernames' );
		$form->add( new \IPS\Helpers\Form\Custom( 'user_name_length', array( \IPS\Settings::i()->min_user_name_length, \IPS\Settings::i()->max_user_name_length ), FALSE, array(
			'getHtml'	=> function( $field ) {
				return \IPS\Theme::i()->getTemplate('members')->usernameLengthSetting( $field->name, $field->value );
			}
		),
		function( $val )
		{
			if ( $val[0] < 1 )
			{
				throw new \DomainException('user_name_length_too_low');
			}
			if ( $val[1] > 255 )
			{
				throw new \DomainException('user_name_length_too_high');
			}
			if ( $val[0] > $val[1] )
			{
				throw new \DomainException('user_name_length_no_match');
			}
		} ) );
		$form->add( new \IPS\Helpers\Form\Text( 'username_characters', \IPS\Settings::i()->username_characters, FALSE, array( 'max' => 255 ) ) );
		$form->addHeader( 'signatures' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'signatures_enabled', \IPS\Settings::i()->signatures_enabled ) );
		$form->addHeader( 'statuses_profile_comments' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'profile_comments', \IPS\Settings::i()->profile_comments, FALSE, array( 'togglesOn' => array( 'profile_comment_approval' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'profile_comment_approval', \IPS\Settings::i()->profile_comment_approval, FALSE, array(), NULL, NULL, NULL, 'profile_comment_approval' ) );
		$form->addHeader( 'profile_settings_birthdays' );
		$form->add( new \IPS\Helpers\Form\Radio( 'profile_birthday_type', \IPS\Settings::i()->profile_birthday_type, TRUE, array(
			'options'	=> array( 'public' => 'profile_birthday_type_public', 'private' => 'profile_birthday_type_private', 'none' => 'profile_birthday_type_none' )
		), NULL, NULL, NULL, 'profile_birthday_type' ) );

		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'profiles', 'member_history_prune' ) )
		{
			$form->addHeader( 'profile_member_history' );
			$form->add( new \IPS\Helpers\Form\Number( 'prune_member_history', \IPS\Settings::i()->prune_member_history, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), \IPS\Member::loggedIn()->language()->addToStack('days'), 'prune_member_history' ) );
		}

		$form->addHeader( 'profile_settings_ignore' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ignore_system_on', \IPS\Settings::i()->ignore_system_on, FALSE, array(), NULL, NULL, NULL, 'ignore_system_on' ) );
		if ( $values = $form->values() )
		{
			$values['min_user_name_length'] = $values['user_name_length'][0];
			$values['max_user_name_length'] = $values['user_name_length'][1];
			unset( $values['user_name_length'] );
		
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__profile_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&tab=settings' ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * Completion Tab
	 *
	 * @return	\IPS\Helpers\Form
	 */
	protected function _tabCompletion()
	{
		/* Is the quick register function disabled? */
		if ( ! \IPS\Settings::i()->quick_register )
		{
			return \IPS\Theme::i()->getTemplate( 'members' )->quickRegisterDisabled();
		}
		
		$table = new \IPS\Helpers\Table\Db( 'core_profile_steps', \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&tab=completion' ) );
		$table->langPrefix = 'profile_acp_table_';
		$table->include = array( 'step_extension', 'step_required' );
		$table->rootButtons = array(
			array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=membersettings&controller=profiles&do=step" )
			)
		);
		$table->parsers = array(
			'step_extension' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'members' )->profileCompleteTitle( $row );
			},
			'step_required' => function( $val ) {
				if ( $val )
				{
					return '&#10003;';
				}
				else
				{
					return '&#10007;';
				}
			}
		);
		$table->rowButtons = function( $row )
		{
			return array(
				'edit'	=> array(
					'title'	=> 'edit',
					'icon'	=> 'pencil',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&do=step&id=' . $row['step_id'] )
				),
				'delete'	=> array(
					'title'	=> 'delete',
					'icon'	=> 'times-circle',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&do=deleteStep&id=' . $row['step_id'] ),
					'data'	=> array( 'delete' => '' )
				),
			);
		};
				
		return \IPS\Theme::i()->getTemplate( 'members' )->profileCompleteBlurb() . $table;
	}
	
	/**
	 * Enable quick registration
	 *
	 * @return	void
	 */
	public function enableQuickRegister()
	{
		\IPS\Settings::i()->changeValues( array( 'quick_register' => 1 ) );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&tab=completion' ), 'profile_complete_quick_register_off_enabled' );

	}
	
	/**
	 * Add Step Form
	 *
	 * @return	void
	 */
	public function step()
	{
		$form = new \IPS\Helpers\Form;
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$step = \IPS\Member\ProfileStep::load( \IPS\Request::i()->id );
			}
			catch( \OutOFRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C360/1', 404, '' );
			}
		}
		else
		{
			$step = new \IPS\Member\ProfileStep;
		}
		
		$step->form( $form );
		
		if ( $values = $form->values() )
		{
			$values = $step->formatFormValues( $values );
			
			$step->extension = \IPS\Member\ProfileStep::findExtensionFromAction( $values['step_completion_act'] );
			if ( isset( $values['step_required'] ) )
			{
				$step->required = $values['step_required'];
			}
			else
			{
				$step->required = FALSE;
			}
			$step->completion_act = $values['step_completion_act'];
			$step->subcompletion_act = $values['step_subcompletion_act'];
			$step->save();
			
			$step->postSaveForm( $values );
			
			if ( method_exists( $step->extension, 'postAcpSave' ) )
			{
				$step->extension->postAcpSave( $step, $values );
			}
			
			\IPS\Member::updateAllMembers( array( "members_bitoptions2 = members_bitoptions2 & ~" . \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['profile_completed'] ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=membersettings&controller=profiles&tab=completion" ), 'saved' );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&tab=completion' ), \IPS\Member::loggedIn()->language()->addToStack('profile_completion') );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'profile_completion' );
		\IPS\Output::i()->output	= $form;
	}
	
	/**
	 * Delete Step
	 *
	 * @return	void
	 */
	public function deleteStep()
	{
		try
		{
			$step = \IPS\Member\ProfileStep::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C360/2', 404, '' );
		}
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		$step->delete();
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=membersettings&controller=profiles&tab=completion" ), 'deleted' );
	}

	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$nodeClass = $this->nodeClass;
		
		if ( $nodeClass::canAddRoot() )
		{
			$add = array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> $this->url->setQueryString( 'do', 'form' ),
				'data'	=> ( $nodeClass::$modalForms ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') ) : array() )
			);

			
			return array( 'add' => $add );
		}
		return array();
	}
}