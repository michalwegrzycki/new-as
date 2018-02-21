//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class convert_hook_FileSystem extends _HOOK_CLASS_
{
	/**
	 * @brief	Enable copy functionality.
	 */
	public static $converterCopy = FALSE;

	/**
	 * Save File
	 *
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function save()
	{
		/* Save the file */
		if( $this->temporaryFilePath AND static::$converterCopy )
		{
			/* Make the folder */
			$folder = $this->configuration['dir'] . '/' . $this->getFolder();

			if( !@\copy( $this->temporaryFilePath, "{$folder}/{$this->filename}" ) )
			{
				\IPS\Log::log( "Could not copy file from {$this->temporaryFilePath} to {$folder}/{$this->filename}" , 'FileSystem' );
				throw new \RuntimeException( 'COULD_NOT_COPY_FILE' );
			}

			@chmod( "{$folder}/{$this->filename}", \IPS\IPS_FILE_PERMISSION );

			/* Reset temporary file data */
			$this->temporaryFilePath = NULL;
		}

		parent::save();
	}
}
