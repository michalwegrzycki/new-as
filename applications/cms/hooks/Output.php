//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Output extends _HOOK_CLASS_
{
	/**
	 * Display Error Screen
	 *
	 * @param    string 			$message 			language key for error message
	 * @param    mixed 				$code 				Error code
	 * @param    int 				$httpStatusCode 	HTTP Status Code
	 * @param    string 			$adminMessage 		language key for error message to show to admins
	 * @param    array 				$httpHeaders 		Additional HTTP Headers
	 * @param    string 			$extra 				Additional information (such backtrace or API error) which will be shown to admins
	 * @param	int|string|NULL		$faultyAppOrHookId	The 3rd party application or the hook id, which caused this error, NULL if it was a core application
	 */
	public function error( $message, $code, $httpStatusCode=500, $adminMessage=NULL, $httpHeaders=array(), $extra=NULL, $faultyAppOrHookId=NULL )
	{
		if ( ! isset( \IPS\Settings::i()->cms_error_page ) or ! \IPS\Settings::i()->cms_error_page )
		{
			parent::error( $message, $code, $httpStatusCode, $adminMessage, $httpHeaders, $extra, $faultyAppOrHookId );
		}
		
		/* If we just logged out, we don't want to show a "no permission" error */
		if ( isset( \IPS\Request::i()->_fromLogout ) )
		{
			$this->redirect( \IPS\Http\Url::internal('')->stripQueryString()->setQueryString( '_fromLogout', 1 ) );
		}
		
		/* If we're in an external script, just show a simple message */
		if ( !\IPS\Dispatcher::hasInstance() )
		{
			\IPS\Session\Front::i();

			$this->sendOutput( \IPS\Member::loggedIn()->language()->get( $message ), $httpStatusCode, 'text/html', $httpHeaders, FALSE );
			return;
		}
		
		if ( \IPS\Dispatcher::i()->controllerLocation !== 'front' )
		{
			parent::error( $message, $code, $httpStatusCode, $adminMessage, $httpHeaders, $extra );
		}
	
		/* Work out the title */
		$title = "{$httpStatusCode}_error_title";
		$title = \IPS\Member::loggedIn()->language()->checkKeyExists( $title ) ? \IPS\Member::loggedIn()->language()->addToStack( $title ) : \IPS\Member::loggedIn()->language()->addToStack( 'error_title' );

		/* Which message are we showing? */
		if( \IPS\Member::loggedIn()->isAdmin() and $adminMessage )
		{
			$message = $adminMessage;
		}
		if ( \IPS\Member::loggedIn()->language()->checkKeyExists( $message ) )
		{
			$message = \IPS\Member::loggedIn()->language()->addToStack( $message );
		}
		
		/* Replace language stack keys with actual content */
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $message );
								
		/* Log */
		$level = intval( \substr( $code, 0, 1 ) );
		if( !\IPS\Session::i()->userAgent->spider )
		{
			if( $code and \IPS\Settings::i()->error_log_level and $level >= \IPS\Settings::i()->error_log_level )
			{
				\IPS\Db::i()->insert( 'core_error_logs', array(
					'log_member'		=> \IPS\Member::loggedIn()->member_id ?: 0,
					'log_date'			=> time(),
					'log_error'			=> $message,
					'log_error_code'	=> $code,
					'log_ip_address'	=> \IPS\Request::i()->ipAddress(),
					'log_request_uri'	=> $_SERVER['REQUEST_URI'],
					) );
			}

			if( \IPS\Settings::i()->error_notify_level and $level >= \IPS\Settings::i()->error_notify_level )
			{
				\IPS\Email::buildFromTemplate( 'core', 'error_log', array( $code, $message ), \IPS\Email::TYPE_LIST )->send( \IPS\Settings::i()->email_in );
			}
		}
			
		/* If this is an AJAX request, send a JSON response */
		if( \IPS\Request::i()->isAjax() )
		{
			$this->json( $message, $httpStatusCode );
		}
				
		/* Send output */
		\IPS\cms\Pages\Page::errorPage( $title, $message, $code, $httpStatusCode, $httpHeaders );
	}

}