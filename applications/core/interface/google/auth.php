<?php
/**
 * @brief		Google Login Handler Redirect URI Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Mar 2013
 */

require_once str_replace( 'applications/core/interface/google/auth.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
$base = explode( '-', \IPS\Request::i()->state );
if ( $base[0] == 'ucp' )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=profilesync&service=Google&loginProcess=google&base=ucp", 'front', 'settings_Google' )->setQueryString( array( 'state' => $base[1], 'code' => \IPS\Request::i()->code ) ) );
}
else
{
	/* Verify this handler is acceptable for the base we are logging in to */
	$loginHandlers	= \IPS\Login::handlers( TRUE );

	if( !isset( $loginHandlers['Google'] ) OR ( $base[0] == 'admin' AND !$loginHandlers['Google']->acp ) )
	{
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( NULL ) );
	}

	$destination = \IPS\Http\Url::internal( "app=core&module=system&controller=login&loginProcess=google&base={$base[0]}", $base[0], 'login', NULL, \IPS\Settings::i()->logins_over_https ? \IPS\Http\Url::PROTOCOL_HTTPS : 0 )->setQueryString( array( 'state' => $base[1], 'code' => \IPS\Request::i()->code ) );
	if ( !empty( $base[2] ) )
	{
		$destination = $destination->setQueryString( 'ref', $base[2] );
	}
		
	\IPS\Output::i()->redirect( $destination );
}