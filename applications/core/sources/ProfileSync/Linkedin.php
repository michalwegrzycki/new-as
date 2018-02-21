<?php
/**
 * @brief		LinkedIn Profile Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jun 2013
 */

namespace IPS\core\ProfileSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * LinkedIn Profile Sync
 */
class _Linkedin extends ProfileSyncAbstract
{
	/** 
	 * @brief	Login handler key
	 */
	public static $loginKey = 'Linkedin';
	
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'linkedin';
			
	/**
	 * @brief	User data
	 */
	protected $user = NULL;
	
	/**
	 * Get user data
	 *
	 * @return	array
	 */
	protected function user()
	{
		if ( $this->user === NULL and $this->member->linkedin_token )
		{
			$this->user = array();
			try
			{
				foreach ( \IPS\Http\Url::external( "https://api.linkedin.com/v1/people/{$this->member->linkedin_id}:(formatted-name,picture-url,current-status)?oauth2_access_token={$this->member->linkedin_token}" )->request()->get()->decodeXml() as $k => $v )
				{
					$this->user[ $k ] = (string) $v;
				}
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				$this->member->linkedin_token = NULL;
				$this->member->save();
			}
		}

		return $this->user;
	}
	
	/**
	 * Is connected?
	 *
	 * @return	bool
	 */
	public function connected()
	{
		return $this->member->linkedin_id and $this->member->linkedin_token;
	}
	
	/**
	 * Get photo
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function photo()
	{
		$user = $this->user();
		if ( isset( $user['picture-url'] ) )
		{
			return \IPS\Http\Url::external( $user['picture-url'] );
		}

		return NULL;
	}
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function name()
	{
		$user = $this->user();
		if ( isset( $user['formatted-name'] ) )
		{
			return $user['formatted-name'];
		}
	}
			
	/**
	 * Disassociate
	 *
	 * @return	void
	 */
	protected function _disassociate()
	{
		$this->member->linkedin_id = 0;
		$this->member->linkedin_token = NULL;
		$this->member->save();
	}

	/**
	 * Get where clause to determine whether a member account is linked to this service
	 *
	 * @param	bool	$invert Returns clause for members not linked
	 * @return	array|FALSE
	 */
	public static function memberFilterLinkedWhere( $invert=FALSE )
	{
		return $invert ? array( '( linkedin_id IS NULL or linkedin_id = 0 )' ) : array( 'linkedin_id > 0' );
	}
}