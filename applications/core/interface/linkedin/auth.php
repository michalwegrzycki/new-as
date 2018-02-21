<?php
/**
 * @brief		LinkedIn Account Login Handler Redirect URI Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Mar 2013
 */

require_once str_replace( 'applications/core/interface/linkedin/auth.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

if ( isset( \IPS\Request::i()->error ) and \IPS\Request::i()->error )
{
	\IPS\Dispatcher\Front::i();
	if ( \IPS\Request::i()->error == 'access_denied' )
	{
		/* user didn't proceed with login, we don't want to log this */
		\IPS\Output::i()->error( htmlentities( \IPS\Request::i()->error_description, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE ), '1C271/2', 403 );
	}
	else
	{
		\IPS\Output::i()->error( htmlentities( \IPS\Request::i()->error_description, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE ), '4C271/1', 403 );
	}
}

$state = explode( '-', \IPS\Request::i()->state );

if ( $state[0] == 'ucp' )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=profilesync&service=Linkedin&loginProcess=linkedin", 'front', 'settings_Linkedin' )->setQueryString( array( 'state' => $state[1], 'code' => \IPS\Request::i()->code ) ) );
}
else
{
	$destination = \IPS\Http\Url::internal( "app=core&module=system&controller=login&loginProcess=linkedin", $state[0], 'login', NULL, \IPS\Settings::i()->logins_over_https ? \IPS\Http\Url::PROTOCOL_HTTPS : 0 )->setQueryString( array( 'state' => $state[1], 'code' => \IPS\Request::i()->code ) );
	if ( !empty( $state[2] ) )
	{
		$destination = $destination->setQueryString( 'ref', $state[2] );
	}
	
	\IPS\Output::i()->redirect( $destination );
}