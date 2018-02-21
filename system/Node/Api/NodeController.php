<?php
/**
 * @brief		Base API endpoint for Nodes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Apr 2017
 */

namespace IPS\Node\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base API endpoint for Nodes
 */
class _NodeController extends \IPS\Api\Controller
{
	/**
	 * List
	 *
	 * @param	array	$where	Extra WHERE clause
	 * @return	\IPS\Api\PaginatedResponse
	 */
	protected function _list( $where = array() )
	{
		$class = $this->class;

		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', $class::$databaseTable, $where, $class::$databaseColumnOrder ? $class::$databaseColumnOrder . " asc" : NULL ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			$class,
			\IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $where )->first()
		);
	}

	/**
	 * View
	 *
	 * @param	int	$id	ID Number
	 * @return	\IPS\Api\Response
	 */
	protected function _view( $id )
	{
		$class = $this->class;
		return new \IPS\Api\Response( 200, $class::load( $id )->apiOutput() );
	}

	/**
	 * View
	 *
	 * @param	int	$id	ID Number
	 * @throws	1S359/1	INVALID_ID	The member ID does not exist
	 * @return	\IPS\Api\Response
	 */
	protected function _delete( $id )
	{
		$class = $this->class;

		try
		{
			$class::load( $id )->delete();

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1S359/1', 404 );
		}
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\node\Model	$node				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\node\Model $node )
	{
		$node->save();

		/* Return */
		return $node;
	}


	/**
	 * Create
	 *
	 * @return	\IPS\Content\Node
	 */
	protected function _create()
	{
		$class = $this->class;

		/* Create item */
		$node = new $class;

		if( isset( $node::$databaseColumnOrder ) AND $node::$automaticPositionDetermination === TRUE )
		{
			$orderColumn = $node::$databaseColumnOrder;
			$node->$orderColumn = \IPS\Db::i()->select( 'MAX(' . $node::$databasePrefix . $orderColumn . ')', $node::$databaseTable  )->first() + 1;
		}

		$node->save();
		$node = $this->_createOrUpdate( $node );

		/* Output */
		return $node;
	}
}