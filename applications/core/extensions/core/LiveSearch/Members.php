<?php
/**
 * @brief		ACP Live Search Extension: Members
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Sept 2013
 */

namespace IPS\core\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Members
 */
class _Members
{
	/**
	 * Check we have access
	 *
	 * @return	void
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' );
	}
	
	/**
	 * Get the search results
	 *
	 * @param	string	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		/* Check we have access */
		if( !$this->hasAccess() )
		{
			return array();
		}

		/* Init */
		$results = array();
		$searchTerm = mb_strtolower( $searchTerm );
		
		/* Perform the search */
		$members = \IPS\Db::i()->select( "*", 'core_members', array( "LOWER(name) LIKE CONCAT( '%', ?, '%' ) OR LOWER(email) LIKE CONCAT( '%', ?, '%' )", $searchTerm, $searchTerm ), NULL, 50 ); # Limit to 50 so it doesn't take too long to run
		
		/* Format results */
		foreach ( $members as $member )
		{
			$member = \IPS\Member::constructFromData( $member );
			
			$results[] = \IPS\Theme::i()->getTemplate('livesearch')->member( $member );
		}
					
		return $results;
	}
	
	/**
	 * Is default for current page?
	 *
	 * @return	bool
	 */
	public function isDefault()
	{
		return \IPS\Dispatcher::i()->application->directory == 'core' and \IPS\Dispatcher::i()->module->key == 'members' and \IPS\Dispatcher::i()->controller != 'groups';
	}
}