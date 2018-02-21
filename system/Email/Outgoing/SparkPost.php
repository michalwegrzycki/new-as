<?php
/**
 * @brief		SparkPost Email Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS\Email\Outgoing;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * SparkPost Email Class
 */
class _SparkPost extends \IPS\Email
{
	/* !Configuration */
	
	/**
	 * @brief	The number of emails that can be sent in one "go"
	 */
	const MAX_EMAILS_PER_GO = 100; // SparkPost "recommends" 2000 per cycle
	
	/**
	 * @brief	If sending a bulk email to more than MAX_EMAILS_PER_GO - does this
	 *			class require waiting between cycles? For "standard" classes like
	 *			PHP and SMTP, this will be TRUE - and will cause bulk mails to go
	 *			to a class. For APIs like SparkPost, this can be FALSE
	 */
	const REQUIRES_TIME_BREAK = FALSE;
	
	/**
	 * @brief	API Key
	 */
	protected $apiKey;
	
	/**
	 * Constructor
	 *
	 * @param	string	$apiKey	API Key
	 * @return	void
	 */
	public function __construct( $apiKey )
	{
		$this->apiKey = $apiKey;
	}
	
	/**
	 * Send the email
	 * 
	 * @param	mixed	$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed	$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed	$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	mixed	$fromEmail			The email address to send from. If NULL, default setting is used
	 * @param	mixed	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array	$additionalHeaders	The name the email should appear from. If NULL, default setting is used
	 * @return	void
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	public function _send( $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array() )
	{
		/* Compile the recipients */
		$recipients = array();
		$mainToAddress = NULL;
		foreach ( array_map( 'trim', explode( ',', static::_parseRecipients( $to, TRUE ) ) ) as $address )
		{
			if ( $mainToAddress === NULL )
			{
				$mainToAddress = $address;
			}
			$recipients[] = array( 'address' => array( 'email' => $address ) );
		}
		if ( $cc )
		{
			foreach ( array_map( 'trim', explode( ',', static::_parseRecipients( $cc, TRUE ) ) ) as $address )
			{
				$recipients[] = array( 'address' => array( 'email' => $address, 'header_to' => $mainToAddress ) );
			}
			$additionalHeaders['Cc'] = static::_parseRecipients( $cc );
		}
		if ( $bcc )
		{
			foreach ( array_map( 'trim', explode( ',', static::_parseRecipients( $bcc, TRUE ) ) ) as $address )
			{
				$recipients[] = array( 'address' => array( 'email' => $address, 'header_to' => $mainToAddress ) );
			}
		}
		
		/* Compile the API call request data */
		$request = array(
			'recipients'	=> $recipients,
			'content'		=> array(
				'html'			=> static::_escapeTemplateTags( $this->compileContent( 'html', static::_getMemberFromRecipients( $to ) ) ),
				'text'			=> static::_escapeTemplateTags( $this->compileContent( 'plaintext', static::_getMemberFromRecipients( $to ) ) ),
				'subject'		=> static::_escapeTemplateTags( $this->compileSubject( static::_getMemberFromRecipients( $to ) ) ),
				'from'				=> array(
					'email'				=> $fromEmail ?: \IPS\Settings::i()->email_out,
					'name'				=> $fromName ?: \IPS\Settings::i()->board_name
				)
			),
			'options'			=> array(
				'transactional'		=> $this->type === static::TYPE_TRANSACTIONAL,
				'open_tracking'		=> (bool) \IPS\Settings::i()->sparkpost_click_tracking,
				'click_tracking'	=> (bool) \IPS\Settings::i()->sparkpost_click_tracking
			)
		);
		if ( \IPS\Settings::i()->sparkpost_ip_pool )
		{
			$request['options']['ip_pool'] = \IPS\Settings::i()->sparkpost_ip_pool;
		}
		$request = $this->_modifyRequestDataWithHeaders( $request, $additionalHeaders );

		/* Make API call */
		$response = $this->_api( 'transmissions', $request );
		if ( isset( $response['errors'] ) )
		{
			$error = $response['errors'][0]['message'];

			if( isset( $response['errors'][0]['description'] ) )
			{
				$error .= ': ' . $response['errors'][0]['description'];
			}

			throw new \IPS\Email\Outgoing\Exception( $error, ( isset( $response['errors'][0]['code'] ) ) ? $response['errors'][0]['code'] : NULL );
		}
	}
	
	/**
	 * Modify the request data that will be sent to the SparkPost API with header data
	 * 
	 * @param	array	$request			SparkPost API request data
	 * @param	array	$additionalHeaders	Additional headers to send
	 * @param	array	$allowedTags		The tags that we want to parse
	 * @return	array
	 */
	protected function _modifyRequestDataWithHeaders( $request, $additionalHeaders = array(), $allowedTags = array() )
	{
		/* Do we have a Reply-To? */
		if ( isset( $additionalHeaders['Reply-To'] ) )
		{
			$request['content']['reply_to'] = static::_escapeTemplateTags( $additionalHeaders['Reply-To'], $allowedTags );
			unset( $additionalHeaders['Reply-To'] );
		}
		
		/* Any other headers? */
		unset( $additionalHeaders['Subject'] );
		unset( $additionalHeaders['From'] );
		unset( $additionalHeaders['To'] );
		if ( count( $additionalHeaders ) )
		{
			$request['content']['headers'] = array_map( function( $v ) use ( $allowedTags ) {
				return static::_escapeTemplateTags( $v, $allowedTags );	
			}, $additionalHeaders );
		}
				
		/* Return */
		return $request;
	}
	
	/**
	 * Merge and Send
	 *
	 * @param	array			$recipients			Array where the keys are the email addresses to send to and the values are an array of variables to replace
	 * @param	mixed			$fromEmail			The email address to send from. If NULL, default setting is used. NOTE: This should always be a site-controlled domin. Some services like Sparkpost require the domain to be validated.
	 * @param	mixed			$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array			$additionalHeaders	Additional headers to send. Merge tags can be used like in content.
	 * @param	\IPS|Lang		$language			The language the email content should be in
	 * @return	int				Number of successful sends
	 */
	public function mergeAndSend( $recipients, $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array(), \IPS\Lang $language )
	{
		/* Work out recipients */
		$varNames = array();
		$recipientsForSparkpost = array();
		$addresses = array();
		foreach ( $recipients as $address => $_vars )
		{
			$addresses[] = $address;
			$vars = array();
			
			foreach ( $_vars as $k => $v )
			{
				$language->parseEmail( $v );

				$vars[ $k ] = $v;
				
				if ( !in_array( $k, $varNames ) )
				{
					$varNames[] = $k;
				}
			}
			
			$recipientsForSparkpost[] = array( 'address' => array( 'email' => $address ), 'substitution_data' => $vars );
		}

		/* Put tags into SparkPost format */
		$htmlContent = str_replace( array( '*|', '|*' ), array( '{{{', '}}}' ), $this->compileContent( 'html', FALSE, $language ) );
		$plaintextContent = str_replace( array( '*|', '|*' ), array( '{{{', '}}}' ), $this->compileContent( 'plaintext', FALSE, $language ) );
		$subject = str_replace( array( '*|', '|*' ), array( '{{{', '}}}' ), $this->compileSubject( NULL, $language ) );
		$_additionalHeaders = array();
		foreach ( $additionalHeaders as $k => $v )
		{
			$_additionalHeaders[ $k ] = str_replace( array( '*|', '|*' ), array( '{{{', '}}}' ), $v );
		}

		/* Compile the API call request data */
		$request = array(
			'recipients'		=> $recipientsForSparkpost,
			'content'			=> array(
				'html'				=> static::_escapeTemplateTags( $htmlContent, $varNames ),
				'text'				=> static::_escapeTemplateTags( $plaintextContent, $varNames ),
				'subject'			=> static::_escapeTemplateTags( $subject, $varNames ),
				'from'				=> array(
					'email'				=> $fromEmail ?: \IPS\Settings::i()->email_out,
					'name'				=> $fromName ?: \IPS\Settings::i()->board_name
				)
			),
			'options'			=> array(
				'transactional'		=> $this->type === static::TYPE_TRANSACTIONAL,
				'open_tracking'		=> (bool) \IPS\Settings::i()->sparkpost_click_tracking,
				'click_tracking'	=> (bool) \IPS\Settings::i()->sparkpost_click_tracking
			)
		);
		if ( \IPS\Settings::i()->sparkpost_ip_pool )
		{
			$request['options']['ip_pool'] = \IPS\Settings::i()->sparkpost_ip_pool;
		}
		$request = $this->_modifyRequestDataWithHeaders( $request, $_additionalHeaders, $varNames );

		/* Make API call */
		try
		{
			$response = $this->_api( 'transmissions', $request );
		}
		catch( \IPS\Email\Outgoing\Exception $e )
		{
			\IPS\Db::i()->insert( 'core_mail_error_logs', array(
				'mlog_date'			=> time(),
				'mlog_to'			=> json_encode( $addresses ),
				'mlog_from'			=> $fromEmail ?: \IPS\Settings::i()->email_out,
				'mlog_subject'		=> $subject,
				'mlog_content'		=> $htmlContent,
				'mlog_resend_data'	=> NULL,
				'mlog_msg'			=> json_encode( $e->getMessage() ),
				'mlog_smtp_log'		=> NULL
			) );

			return 0;
		}

		if ( isset( $response['errors'] ) )
		{
			\IPS\Db::i()->insert( 'core_mail_error_logs', array(
				'mlog_date'			=> time(),
				'mlog_to'			=> json_encode( $addresses ),
				'mlog_from'			=> $fromEmail ?: \IPS\Settings::i()->email_out,
				'mlog_subject'		=> $subject,
				'mlog_content'		=> $htmlContent,
				'mlog_resend_data'	=> NULL,
				'mlog_msg'			=> json_encode( $response['errors'] ),
				'mlog_smtp_log'		=> NULL
			) );
		}
		
		return ( isset( $response['results'] ) and isset( $response['results']['total_accepted_recipients'] ) ) ? $response['results']['total_accepted_recipients'] : 0;
	}
	
	/**
	 * Make API call
	 *
	 * @param	string	$method	Method
	 * @param	string	$apiKey	API Key
	 * @param	array	$args	Arguments
	 * @throws  \IPS\Email\Outgoing\Exception   Indicates an invalid JSON response or HTTP error
	 * @return	array
	 */
	protected function _api( $method, $args=NULL )
	{
		$request = \IPS\Http\Url::external( 'https://api.sparkpost.com/api/v1/' . $method )
			->request( \IPS\LONG_REQUEST_TIMEOUT )
			->setHeaders( array( 'Content-Type' => 'application/json', 'Authorization' => $this->apiKey ) );

		try
		{
			if ( $args )
			{
				$response = $request->post( json_encode( $args ) );
			}
			else
			{
				$response = $request->get();
			}

			return $response->decodeJson();
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \IPS\Email\Outgoing\Exception( $e->getMessage(), $e->getCode() );
		}
		/* Capture json decoding errors */
		catch ( \RuntimeException $e )
		{
			throw new \IPS\Email\Outgoing\Exception( $e->getMessage(), $e->getCode() );
		}
	}
	
	/**
	 * Escape template tags
	 *
	 * @param	string	$content		The content
	 * @param	array	$allowedTags	The tags that we want to parse
	 * @return	array
	 * @see		<a href="https://developers.sparkpost.com/api/#/introduction/substitutions-reference/escaping-start-and-end-tags">Escaping Start and End Tags</a>
	 */
	protected static function _escapeTemplateTags( $content , $allowedTags = array() )
	{
		/* Store the tags we're allowed */
		$replacedTagsI = 0;
		$replacedTagsStore = array();
		$content = preg_replace_callback( '/\{\{\{(' . implode( '|', array_map( function( $val ) { return preg_quote( $val, '/' ); }, $allowedTags ) ) . ')\}\}\}/', function( $matches ) use ( &$replacedTagsI, &$replacedTagsStore )
		{
			$replacedTagsStore[ ++$replacedTagsI ] = $matches[0];
			return '--rt-' . $replacedTagsI . '--';
		}, $content );
		
		/* Escape */
		$content = str_replace( '{{{', '--ot--x--', $content );
		$content = str_replace( '}}}', '--ct--x--', $content );
		$content = str_replace( '{{', '--od--x--', $content );
		$content = str_replace( '}}', '--cd--x--', $content );
		$content = str_replace( '--ot--x--', '{{opening_triple_curly()}}', $content );
		$content = str_replace( '--ct--x--', '{{closing_triple_curly()}}', $content );
		$content = str_replace( '--od--x--', '{{opening_double_curly()}}', $content );
		$content = str_replace( '--cd--x--', '{{closing_double_curly()}}', $content );
		
		/* Put our {{{tags}}} back */
		$content = preg_replace_callback( '/--rt-(.+?)--/', function( $matches ) use ( $replacedTagsStore )
		{
			return isset( $replacedTagsStore[ $matches[1] ] ) ? $replacedTagsStore[ $matches[1] ] : '';
		}, $content );
		
		/* Return */
		return $content;
	}
	
	/**
	 * Get sending domains
	 *
	 * @return	array
	 */
	public function sendingDomains()
	{
		return $this->_api( 'sending-domains' );
	}
	
}