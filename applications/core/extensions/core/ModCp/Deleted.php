<?php
/**
 * @brief		Moderator Control Panel Extension: Deleted
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		15 Feb 2017
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Moderator Control Panel Extension: Deleted
 */
class _Deleted
{
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string|null
	 */
	public function getTab()
	{
		if ( ! \IPS\Member::loggedIn()->modPermission( 'can_manage_deleted_content' ) )
		{
			return NULL;
		}
		
		return 'deleted';
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		if ( isset( \IPS\Request::i()->modaction ) AND in_array( \IPS\Request::i()->modaction, array( 'restore', 'restore_as_hidden', 'delete' ) ) )
		{
			$this->modaction();
		}
		
		/* Content Types to filter on */
		$contentOptions = array();
		$contentOptions['all'] = 'all';
		foreach( \IPS\Content::routedClasses() AS $class )
		{
			if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
			{
				$contentOptions[ $class ] = $class::$title;
			}
		}
		
		$table					= new \IPS\core\DeletionLog\Table( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=deleted", 'front', 'modcp_deleted' ) );
		$table->sortOptions		= array( 'dellog_deleted_date', 'dellog_deleted_by' );
		$table->advancedSearch	= array(
			'dellog_content_class'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $contentOptions ) ),
			'dellog_deleted_by'		=> \IPS\Helpers\Table\SEARCH_MEMBER
		);
		$table->tableTemplate	= array( \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' ), 'deletedTable' );
		$table->rowsTemplate	= array( \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' ), 'deletedRows' );
		
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'modcp_deleted' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_deleted' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'modcp' )->deletedContent( $table );
	}
	
	/**
	 * Mod Action
	 *
	 * @return	void
	 */
	public function modaction()
	{
		$ids = array();
		foreach( \IPS\Request::i()->moderate AS $id => $status )
		{
			$ids[] = $id;
		}
		
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_deletion_log', array( \IPS\Db::i()->in( 'dellog_id', $ids ) ) ), 'IPS\core\DeletionLog' ) AS $log )
		{
			$class = $log->content_class;
			$content = $class::load( $log->content_id );
			
			switch( \IPS\Request::i()->modaction )
			{
				case 'restore':
					if ( isset( $class::$databaseColumnMap['hidden'] ) )
					{
						$column = $class::$databaseColumnMap['hidden'];
						$content->$column = 0;
					}
					else if ( isset( $class::$databaseColumnMap['approved'] ) )
					{
						$column = $class::$databaseColumnMap['approved'];
						$content->$column = 1;
					}
					
					\IPS\Session::i()->modLog( 'modlog__action_restore', array(
						$content::$title				=> FALSE,
						$content->url()->__toString()	=> FALSE
					) );
					
					try
					{
						if ( $content->container() )
						{
							if ( $content->container()->_comments !== NULL )
							{
								if ( $content instanceof \IPS\Content\Item )
								{
									$content->container()->_comments = ( $content->container()->_comments + $content->mapped('num_comments') );
								}
								else
								{
									$content->container()->_comments = $content->container()->_comments + 1;
								}
							}
							
							if ( $content->container()->_unapprovedComments !== NULL )
							{
								if ( $content instanceof \IPS\Content\Item )
								{
									$content->container()->_unapprovedComments = ( $content->container()->_unapprovedComments + $content->mapped('unapproved_comments') );
								}
							}
							$content->container()->setLastComment();
							$content->container()->save();
						}
					}
					catch( \BadMethodCallException $e ) {}
					
					$content->save();
					$log->delete();
				break;
				
				case 'restore_as_hidden':
					if ( isset( $class::$databaseColumnMap['hidden'] ) )
					{
						$column = $class::$databaseColumnMap['hidden'];
					}
					else if ( isset( $class::$databaseColumnMap['approved'] ) )
					{
						$column = $class::$databaseColumnMap['approved'];
					}
					
					$content->$column = -1;
					
					\IPS\Session::i()->modLog( 'modlog__action_restore_hidden', array(
						$content::$title				=> FALSE,
						$content->url()->__toString()	=> FALSE
					) );
					
					$content->save();
					$log->delete();
				break;
				
				case 'delete':
					\IPS\Session::i()->modLog( 'modlog__action_delete_perm', array(
						$content::$title				=> FALSE,
						$content->url()->__toString()	=> FALSE
					) );
					
					$content->delete();
					$log->delete();
				break;
			}
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=deleted", 'front', 'modcp_deleted' ), 'saved' );
	}
}