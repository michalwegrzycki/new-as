<?php


namespace IPS\convert\modules\admin\manage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * create
 */
class _create extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->responsive = FALSE;
		\IPS\Dispatcher::i()->checkAcpPermission( 'create_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* We only use the Wizard helper for starting a conversion, so we need to make sure it is always starting new and not using old session data */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'convert_start' );
		
		/* Let's call on the wizard to make some magic */
		\IPS\Output::i()->output = new \IPS\Helpers\Wizard( array(
			'convert_start_application'	=> array( $this, '_stepOne' ),
			'convert_start_source_software' => array( $this, '_stepTwo' ),
			'convert_start_database_details' => array( $this, '_stepThree' ),
		), \IPS\Http\Url::internal( 'app=convert&module=manage&controller=create' ) );
	}

	/**
	 * First step of conversion
	 *
	 * @return	void
	 */
	public function _stepOne( $data )
	{
		foreach( \IPS\Application::applications() AS $app )
		{
			if ( \IPS\Member::loggedIn()->language()->checkKeyExists( "{$app->directory}_node_select" ) )
			{
				\IPS\member::loggedIn()->language()->words["__app_{$app->directory}"] = \IPS\Member::loggedIn()->language()->addToStack( "{$app->directory}_node_select" );
			}
		}
		
		/* Have we done any conversions yet? */
		$appCount = \IPS\Db::i()->select( 'COUNT(*)', 'convert_apps', array( "parent=?", 0 ) )->first();
		
		$form = new \IPS\Helpers\Form( 'convert_application', 'select_source' );
		$form->add( new \IPS\Helpers\Form\Node( 'convert_start_application', NULL, TRUE, array( 'class' => 'IPS\\Application', 'subnodes' => FALSE, 'permissionCheck' => function( $node ) use ( $appCount )
		{
			foreach( new \DirectoryIterator( \IPS\ROOT_PATH . '/applications/convert/sources/Library' ) AS $file )
			{
				if ( ucwords( $node->directory ) === str_replace( '.php', '', $file->getFilename() ) )
				{
					$hasFile = FALSE;
					/* Only show the node if there is a converter available */
					foreach( new \DirectoryIterator( \IPS\ROOT_PATH . '/applications/convert/sources/Software/' . ucwords( $node->directory ) ) AS $softwareFile )
					{
						if( mb_substr( $softwareFile->getFilename(), -3 ) === 'php' )
						{
							$hasFile = TRUE;
							break;
						}
					}

					if ( ( $node->directory === 'core' OR $appCount > 0 ) and $hasFile )
					{
						return TRUE;
					}
				}
			}
			
			return NULL;
		} ) ) );
		
		if ( $values = $form->values() )
		{
			return array( 'convert_start_application' => $values['convert_start_application']->directory );
		}
		
		return $form;
	}

	/**
	 * Second step of conversion
	 *
	 * @return	void
	 */
	public function _stepTwo( $data )
	{
		$options = array();
		foreach( new \DirectoryIterator( \IPS\ROOT_PATH . '/applications/convert/sources/Software/' . ucwords( $data['convert_start_application'] ) ) AS $file )
		{
			$classname = 'IPS\\convert\\Software\\' . ucwords( $data['convert_start_application'] ) . '\\' . ucwords( str_replace( '.php', '', $file->getFilename() ) );
			if ( class_exists( $classname ) AND $classname::canConvert() !== NULL )
			{
				$options[ $classname::softwareKey() ] = $classname::softwareName();
			}
		}
		
		$form = new \IPS\Helpers\Form( 'convert_source_software', 'enter_database_details' );
		$form->add( new \IPS\Helpers\Form\Select( 'convert_start_source_software', NULL, TRUE, array( 'options' => $options ) ) );
		
		if ( $values = $form->values() )
		{
			return $values;
		}
		
		return $form;
	}

	/**
	 * Third step of conversion
	 *
	 * @return	void
	 */
	public function _stepThree( $data )
	{
		$form = new \IPS\Helpers\Form( 'convert_database_details', 'start_conversion' );
		$classname = 'IPS\\convert\\Software\\' . ucwords( $data['convert_start_application'] ) . '\\' . ucwords( $data['convert_start_source_software'] );

		if ( !is_null( $classname::parents() ) AND is_array( $classname::parents() ) )
		{
			/* Do we have any available parents? */
			$where = array();
			$options = array();
			foreach( $classname::parents() AS $app => $sw )
			{
				foreach( $sw AS $key )
				{
					$where[] = array( 'sw=? AND app_key=?', $app, $key );
				}
			}
			
			try
			{
				foreach( \IPS\Db::i()->select( '*', 'convert_apps', $where ) AS $row )
				{
					$name = explode( '_', $row['name'] );
					$parentClassname = 'IPS\\convert\\Software\\' . ucwords( $name[0] ) . '\\' . ucwords( $name[1] );
					$options[$row['app_id']] = $parentClassname::softwareName();
				}
			}
			catch( \Exception $e ) {}

			/* If we have any possible parents, lets add a select menu */
			if ( count( $options ) > 0 )
			{
				$form->addHeader( 'convert_start_select_parent' );
				$form->add( new \IPS\Helpers\Form\Select( 'convert_start_parent', NULL, FALSE, array( 'options' => $options ) ) );
				$form->addHeader( 'convert_start_or_database' );
			}

			/* This software class requires a parent, but we don't have any that have been converted */
			elseif( $classname::requiresParent() )
			{
				\IPS\Output::i()->error( 'convert_no_applicable_parent', '4V346/1' );
			}
		}
		if ( $classname::getPreConversionInformation() !== NULL )
		{
			$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( $classname::getPreConversionInformation() ), '', FALSE );
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'convert_start_database_host', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'convert_start_database_port', 3306, FALSE, array( 'max' => 65535, 'min' => 1 ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'convert_start_database_name', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'convert_start_database_user', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'convert_start_database_pass', NULL, FALSE ) );
		
		/* If the source software doesn't use a prefix, then we should not ask for one to avoid confusion. */
		if ( $classname::usesPrefix() )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'convert_start_database_prefix', NULL, FALSE ) );
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'convert_start_database_charset', NULL, FALSE, array( 'placeholder' => 'utf8' ) ) );

		if ( $values = $form->values() )
		{
			/* Did we select a parent but no DB details? */
			if (
				isset( $values['convert_start_parent'] ) AND
                ( !isset( $values['convert_start_database_host'] ) || empty($values['convert_start_database_host']) ) AND
                ( !isset( $values['convert_start_database_name'] ) || empty($values['convert_start_database_name']) ) AND
                ( !isset( $values['convert_start_database_user'] ) || empty($values['convert_start_database_user']) ) AND
                ( !isset( $values['convert_start_database_pass'] ) || empty($values['convert_start_database_pass']) ) AND
                ( !isset( $values['convert_start_database_prefix'] ) || empty($values['convert_start_database_prefix']) ) AND
                ( !isset( $values['convert_start_database_charset'] ) || empty($values['convert_start_database_charset']) )
			)
            {
				/* We did - load the parent app and store the DB details for that */
				$parentApp = \IPS\convert\App::load( $values['convert_start_parent'] );
				$values['convert_start_database_host']		= $parentApp->db_host;
				$values['convert_start_database_port']		= $parentApp->db_port;
				$values['convert_start_database_name']		= $parentApp->db_db;
				$values['convert_start_database_user']		= $parentApp->db_user;
				$values['convert_start_database_pass']		= $parentApp->db_pass;
				$values['convert_start_database_prefix']	= $parentApp->db_prefix;
				$values['convert_start_database_charset']	= $parentApp->db_charset;
			}

			/* Start the app object */
			$app			= new \IPS\convert\App;
			$app->sw		= $data['convert_start_application'];
			$app->app_key	= $data['convert_start_source_software'];
			
			try
			{
				if ( !isset( $values['convert_start_database_prefix'] ) )
				{
					$values['convert_start_database_prefix'] = '';
				}

				/* Test the connection */
				$connectionSettings = array(
					'sql_host'			=> $values['convert_start_database_host'],
					'sql_port'			=> $values['convert_start_database_port'],
					'sql_user'			=> $values['convert_start_database_user'],
					'sql_pass'			=> $values['convert_start_database_pass'],
					'sql_database'		=> $values['convert_start_database_name'],
					'sql_tbl_prefix'	=> $values['convert_start_database_prefix'],
				);
				
				if ( $values['convert_start_database_charset'] === 'utf8mb4' )
				{
					$connectionSettings['sql_utf8mb4'] = TRUE;
				}

				$db = \IPS\Db::i( 'convertertest', $connectionSettings );

				/* Now test the charset */
				if ( $values['convert_start_database_charset'] AND !in_array( $values['convert_start_database_charset'], array( 'utf8', 'utf8mb4' ) ) )
				{
					/* Get all db charsets and make sure the one we entered is valid */
					$charsets = \IPS\convert\Software::getDatabaseCharsets( $db );

					if ( !in_array( mb_strtolower( $values['convert_start_database_charset'] ), $charsets ) )
					{
						throw new \InvalidArgumentException( 'invalid_charset' );
					}
					
					$db->set_charset( $values['convert_start_database_charset'] );
				}

				/* Try to verify that the db prefix is correct */
				$appClassName = $app->getSource( FALSE, FALSE );
				$canConvert = $appClassName::canConvert();
				$appClass = new $appClassName( $app, FALSE );
				$appClass->db = $db;

				$testAgainst = array_shift( $canConvert );

				/* We specifically use CountRows to test this, canConvert() may contain references to tables that do not exist..
				 * ..This is for the row count look up.
				 */
				try
				{
					$appClass->countRows( $testAgainst['table'] );
				}
				catch( \UnderflowException $e )
				{
					throw new \InvalidArgumentException( 'invalid_prefix' );
				}
			}
			catch( \InvalidArgumentException $e )
			{
				if( $e->getMessage() == 'invalid_charset' )
				{
					$form->error	= \IPS\Member::loggedIn()->language()->addToStack('convert_cant_connect_db_charset');
					return $form;
				}
				else if( $e->getMessage() == 'invalid_prefix' )
				{
					$form->error	= \IPS\Member::loggedIn()->language()->addToStack('convert_cant_connect_db_prefix');
					return $form;
				}
				else
				{
					throw $e;
				}
			}
			catch( \Exception $e )
			{
				$form->error	= \IPS\Member::loggedIn()->language()->addToStack('convert_cant_connect_db');
				return $form;
			}
			
			try
			{
				$appExists	= \IPS\convert\App::load( $data['convert_start_application'] . '_' . $data['convert_start_source_software'], 'name' );
				$app->name	= $data['convert_start_application'] . '_' . $data['convert_start_source_software'] . '_' . time();
			}
			catch( \OutOfRangeException $e )
			{
				$app->name = $data['convert_start_application'] . '_' . $data['convert_start_source_software'];
			}
			
			if ( isset( $values['convert_start_parent'] ) )
			{
				$app->parent = $values['convert_start_parent'];
			}
			else
			{
				$app->parent = 0;
			}
			
			$app->db_host		= $values['convert_start_database_host'];
			$app->db_port		= $values['convert_start_database_port'];
			$app->db_db			= $values['convert_start_database_name'];
			$app->db_user		= $values['convert_start_database_user'];
			$app->db_pass		= $values['convert_start_database_pass'];
			$app->db_prefix		= $values['convert_start_database_prefix'];

			/* Check for existing apps */
			try
			{
				$existingApp = \IPS\DB::i()->select( '*', 'convert_apps', array( 'sw=? AND app_key=? AND db_host=? AND db_db=? AND db_user=? AND db_pass=? AND db_prefix=?', $app->sw, $app->app_key, $app->db_host, $app->db_db, $app->db_user, $app->db_pass, $app->db_prefix ) )->first();

				/* App already exists */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=convert&module=manage&controller=convert&id={$existingApp['app_id']}" ), 'conversion_app_already_exists' );
			}
			catch( \UnderflowException $e ) { }

			if ( !empty( $values['convert_start_database_charset'] ) )
			{
				$app->db_charset	= $values['convert_start_database_charset'];
			}
			else
			{
				$app->db_charset	= 'utf8';
			}

			$app->save();

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=convert&module=manage&controller=convert&id={$app->app_id}" ) );
		}
		
		return $form;
	}
}