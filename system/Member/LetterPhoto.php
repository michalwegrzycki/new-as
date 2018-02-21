<?php
/**
 * @brief		Letter photo generator for member accounts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Feb 2017
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Letter photo generator for member accounts
 */
class _LetterPhoto
{
	/**
	 * @brief	File storage class to use
	 */
	protected $fileStorageClass		= 'core_Profile';

	/**
	 * @brief	Member object
	 */
	protected $member		= NULL;

	/**
	 * @brief	Text size
	 */
	protected $textSize	= 75;

	/**
	 * Create a new photo object
	 *
	 * @param	\IPS\Member	$member		Member object
	 * @return	void
	 */
	public function __construct( $member )
	{
		$this->member		= $member;
	}

	/**
	 * Create the photo
	 *
	 * @param	int		$maxWidth		Max width
	 * @param	int		$maxHeight		Max height
	 * @param	int		$letterCount	How many letters to use
	 * @return	\IPS\Http\Url|NULL
	 */
	public function create( $maxWidth, $maxHeight, $letterCount=1 )
	{
		/* If we don't have any sizes, we can't generate a proper image */
		if( !$maxWidth OR !$maxHeight )
		{
			return NULL;
		}

		/* Set the font size to 1/2 of the $maxWidth and $maxHeight (which should be the same value) */
		$this->textSize = ( ( $maxWidth / 2 ) / $letterCount );

		/* If we don't have a name, there's nothing to create */
		if( !$this->member->name )
		{
			return NULL;
		}

		/* Get the first character of the name...if we can't detect it, there's again nothing to convert */
		$letters	= $this->getLetters( $letterCount );

		if( !$letters )
		{
			return NULL;
		}

		/* Now we have to generate the background color we will be using */
		$rgbColor = $this->generateColorCode();

		/* Create the image */
		$image = \IPS\Image::newImageCanvas( $maxWidth, $maxHeight, $rgbColor );

		/* Draw the text on the image */
		$image->write( $letters, $this->getFont( $letters ), $this->textSize );

		/* Save the image and return it */
		try
		{
			return \IPS\File::create( $this->fileStorageClass, $letters . '_member_' . $this->member->member_id . '.png', (string) $image, NULL, TRUE, NULL, FALSE );
		}
		catch( \Exception $e )
		{
			\IPS\Log::debug( $e, 'letter_photo' );

			return NULL;
		}
	}

	/**
	 * Return the first X non-punctuation characters of the name
	 *
	 * @param	int		$letters	Number of letters to return
	 * @return	string|NULL
	 */
	protected function getLetters( $letters=1 )
	{
		$name = str_replace( array( '<', '>', '=', '-', '+', '"', "'" ), '', $this->member->name );
		$name = preg_replace( "/(\pP)+/u", '', trim( $name ) );

		return mb_strtoupper( mb_substr( $name, 0, $letters ) );
	}

	/**
	 * @brief	Color saturation to use for HSV colors
	 */
	protected $colorCodeSaturation	= .45;

	/** 
	 * @brief	Color value to use for HSV colors
	 */
	protected $colorCodeValue		= 0.575;

	/**
	 * @brief	If we have just generated a hue on this page load, store it to better randomize the next color
	 */
	protected static $lastHue		= NULL;

	/**
	 * Return a unique color code based on the name.
	 * 
	 * @return	array (RGB value)
	 */
	protected function generateColorCode()
	{
		/* If we've already generated a hue on this page load, use the golden ratio to find a number sufficiently distinct */
		if( static::$lastHue )
		{
			$hue = static::$lastHue + 0.618033988749895;

			if( $hue > 1.0 )
			{
				$hue -= 1.0;
			}

			static::$lastHue = $hue;

			return $this->convertHsvToRgb( $hue, $this->colorCodeSaturation, $this->colorCodeValue );
		}

		$hue	= ( ( ord( \mb_strtolower( $this->member->name ) ) - 97 ) / 25.0 ) + 0.25;
		$hue	*= 1.61803398875;
		$hue	*= (float) ( '1.' . rand() ) / 1;

		if( rand( 0, 1 ) )
		{
			$float = (float) ( "1." . ord( array_rand( range( 'a', 'z' ) ) ) );
			$hue = abs( $float - $hue );
		}

		while( $hue > 1.0 )
		{
			$hue -= 1.0;
		}

		static::$lastHue = $hue;

		return $this->convertHsvToRgb( $hue, $this->colorCodeSaturation, $this->colorCodeValue );
	}

	/**
	 * Convert the HSV value to RGB
	 *
	 * @param	float	$h	Hue
	 * @param	float	$s	Saturation
	 * @param	float	$v	Value
	 * @return	array
	 */
	protected function convertHsvToRgb( $h, $s, $v )
	{
		$rgb	= array( $v, $v, $v );
		$diff	= ( $v <= 0.5 ) ? ( $v * ( 1.0 + $s ) ) : ( $v + $s - $v * $s );

		if( $diff )
		{
			$m	= $v + $v - $diff;
			$sv	= ( $diff - $m ) / $diff;
			$h	*= 6.0;
			$fract	= $h - floor( $h );
			$vsf	= $diff * $sv * $fract;
			$mid1	= $m + $vsf;
			$mid2	= $diff - $vsf;

			switch( floor( $h ) )
			{
				case 0:
					$rgb = array( $diff, $mid1, $m );
				break;

				case 1:
					$rgb = array( $mid2, $diff, $m );
				break;

				case 2:
					$rgb = array( $m, $diff, $mid1 );
				break;

				case 3:
					$rgb = array( $m, $mid2, $diff );
				break;

				case 4:
					$rgb = array( $mid1, $m, $diff );
				break;

				case 5:
					$rgb = array( $diff, $m, $mid2 );
				break;
			}
		}

		return array_map( function( $value ) { return $value * 256.0; }, $rgb );
	}

	/**
	 * Return a font file path to use
	 *
	 * @param	string	$string		String we are going to write
	 * @return	string
	 */
	protected function getFont( $string='' )
	{
		$codePointMap	= array(
			'NotoSansArmenian-Regular.ttf'		=> array( 0x0530, 0x058F ),
			'NotoSansHebrew-Regular.ttf'		=> array( 0x0590, 0x05FF ),
			'NotoNaskhArabic-Regular.ttf'		=> array( 0x0600, 0x06FF ),
			'NotoSansGeorgian-Regular.ttf'		=> array( 0x10A0, 0x10FF ),
			'NotoSansThai-Regular.ttf'			=> array( 0x0E00, 0x0E7F ),
			'NotoSansDevanagari-Regular.ttf'	=> array( 0x0900, 0x097F ),
			'BMDOHYEON.ttf'						=> array(
				array( 0xAC00, 0xD7A3 ), 
				array( 0x1100, 0x11FF ), 
				array( 0x3130, 0x318F ), 
				array( 0xA960, 0xA97F ), 
				array( 0xD7B0, 0xD7FF ) 
			),
			'HanaMinA.ttf'						=> array( 
				array( 0x4E00, 0x9FFF ), 
				array( 0x3400, 0x4DB5 ), 
				array( 0x3040, 0x309F ),
				array( 0x30A0, 0x30FF ),
				array( 0xFF00, 0xFFEF ),
				array( 0xF900, 0xFAD9 ),
				array( 0x20000, 0x2A6D6 ), 
				array( 0x2A700, 0x2B734 ), 
				array( 0x2B740, 0x2B81D ), 
				array( 0x2B820, 0x2CEA1 ), 
				array( 0x2F800, 0x2FA1D ) 
			),
		);

		/* Get all characters */
		$characters = preg_split( '//u', $string, null, PREG_SPLIT_NO_EMPTY );

		/* Loop over all characters. If any character is in a range defined above, use that font.
			Technically you could have a Korean character and an Arabic character in the same string, but the probability of that occurring
			is sufficiently rare that we will use the first font defined above which support latin ranges already */
		foreach( $characters as $character )
		{
			$decValue = unpack( 'N', mb_convert_encoding( $character, 'UCS-4BE', 'UTF-8' ) );
			$decValue = $decValue[1];

			foreach( $codePointMap as $fontFile => $range )
			{
				if( is_array( $range[0] ) )
				{
					foreach( $range as $subRange )
					{
						if( $decValue > ( $subRange[0] ) AND $decValue < ( $subRange[1] ) )
						{
							return \IPS\ROOT_PATH . '/system/3rd_party/Fonts/' . $fontFile;
						}
					}
				}
				else
				{
					if( $decValue > ( $range[0] ) AND $decValue < ( $range[1] ) )
					{
						return \IPS\ROOT_PATH . '/system/3rd_party/Fonts/' . $fontFile;
					}
				}
			}
		}

		/* Fallback for the majority of languages */
		return \IPS\ROOT_PATH . '/system/3rd_party/Fonts/NotoSans-Regular.ttf';
	}
}