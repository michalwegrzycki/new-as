<?php
/**
 * @brief		Member input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member input class for Form Builder
 */
class _Member extends Text
{
	/**
	 * @brief	Default Options
	 * @code
	 	$childDefaultOptions = array(
	 		'multiple'	=> 1,	// Maximum number of members. NULL for any. Default is 1.
	 	);
	 * @endcode
	 */
	public $childDefaultOptions = array(
		'multiple'	=> 1,
	);


	/**
	 * Constructor
	 * Adds 'confirm' to the available options
	 *
	 * @see		\IPS\Helpers\Form\FormAbstract::__construct
	 * @return	void
	 */
	public function __construct()
	{
		$args = func_get_args();
		
		$this->defaultOptions['autocomplete'] = array(
			'source' 				=>	'app=core&module=system&controller=ajax&do=findMember',
			'resultItemTemplate' 	=> 'core.autocomplete.memberItem',
			'commaTrigger'			=> false,
			'unique'				=> true,
			'minAjaxLength'			=> 3,
			'disallowedCharacters'  => array()
		);
		if( isset( $args[3] ) and array_key_exists( 'multiple', $args[3] ) and $args[3]['multiple'] > 0 )
		{
			$this->defaultOptions['autocomplete']['maxItems'] = $args[3]['multiple'];
		}
		elseif ( !isset( $args[3] ) or !array_key_exists( 'multiple', $args[3] ) )
		{
			$this->defaultOptions['autocomplete']['maxItems'] = $this->childDefaultOptions['multiple'];
		}
		
		call_user_func_array( 'parent::__construct', $args );
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$value = $this->value;
		if ( is_array( $this->value ) )
		{
			$value = array();
			foreach ( $this->value as $v )
			{
				$value[] = ( $v instanceof \IPS\Member ) ? $v->name : $v;
			}
			$value = implode( "\n", $value );
		}
		elseif ( $value instanceof \IPS\Member )
		{
			$value = $value->name;
		}
		
		/* 10/19/15 - adding htmlspecialchars around value if autocomplete is enabled so that html tag characters can be used (e.g. for members) */
		/* This value is decoded by the JS widget before use. */
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->text( $this->name, 'text', ( $this->options['autocomplete'] AND !is_null( $value ) ) ? htmlspecialchars( $value, ENT_QUOTES | \IPS\HTMLENTITIES, 'UTF-8', FALSE ) : $value, $this->required, $this->options['maxLength'], $this->options['size'], $this->options['disabled'], $this->options['autocomplete'], $this->options['placeholder'], NULL, $this->options['nullLang'] );
	}
	
	/**
	 * Format Value
	 *
	 * @return	\IPS\Member|array|NULL|FALSE
	 */
	public function formatValue()
	{
		if ( $this->value !== '' and !( $this->value instanceof \IPS\Member ) )
		{
			$return = array();
			
			foreach ( is_array( $this->value ) ? $this->value : explode( "\n", $this->value ) as $v )
			{
				if ( $v instanceof \IPS\Member )
				{
					$return[ $v->member_id ] = $v;
				}
				elseif( $v !== '' )
				{
					$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );

					$member = \IPS\Member::load( $v, 'name' );
					if ( $member->member_id )
					{
						if ( $this->options['multiple'] === 1 )
						{
							return $member;
						}
						$return[ $member->member_id ] = $member;
					}
				}
			}

			if ( !empty( $return ) )
			{
				return ( $this->options['multiple'] === NULL or $this->options['multiple'] == 0 ) ? $return : array_slice( $return, 0, $this->options['multiple'] );
			}
		}
		
		return $this->value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();
		
		if ( $this->value !== '' and !( $this->value instanceof \IPS\Member ) and !is_array( $this->value ) )
		{
			throw new \InvalidArgumentException('form_member_bad');
		}
		else if ( is_array( $this->value ) )
		{
			foreach( $this->value AS $value )
			{
				if ( $value !== '' AND !( $value instanceof \IPS\Member ) )
				{
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'form_member_bad_multiple', FALSE, array( 'sprintf' => array( $value ) ) ) );
				}
			}
		}
	}
	
	
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string|null
	 */
	public static function stringValue( $value )
	{
		if ( is_array( $value ) )
		{
			if ( !count( $value ) )
			{
				return NULL;
			}
			
			return implode( "\n", array_map( function( $v )
			{
				return $v->member_id;
			}, $value ) );
		}
		
		return $value ? (string) $value->member_id : NULL;
	}
}