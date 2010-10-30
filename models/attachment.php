<?php

class Attachment extends AppModel {
	public $name      = 'Attachment';
	public $useTable  = 'polyclip_attachments'; # non-standard to avoid conflict
	public $actsAs    = array(
		'Polyclip.polymorphic' => array(
			'classField' => 'model',
			'foreignKey' => 'entity_id'
		)
	);
	
	public $hasOne = array(
		'ImageAttachment' => array( 'className' => 'Polyclip.ImageAttachment', 'foreignKey' => 'polyclip_attachment_id', 'dependent' => true )
	);

	public $validate  = array();
	
	/**
	 * PUBLIC METHODS
	 */
	
	/**
	 * Creates or replaces a physical file, attaching it to another model
	 * in the process.
	 *
	 * @param		$model	string		The name of the model to which the file
	 *  													will be attached.
	 * @param		$id			uuid			ID of the model instance
	 * @param		$new		array			The newly uploaded attachment
	 * @param		$old		array			The attachment to be replaced
	 * @return 	uuid		The new file's identifier
	 */
	public function attach( $model, $entity_id, $alias, $new, $old = null ) {
		$this->log( 'Attaching a file to a(n) ' . $model . ' with id ' . $entity_id, LOG_DEBUG );
		
		if( !is_array( $new ) || ( isset( $old ) && !is_array( $old ) ) ) {
			throw new Exception( 'Polyclip.Attachment::attach() expects at least one binary array argument' );
		}
		
		if( $new['error'] === UPLOAD_ERR_OK ) {
			$new['model']     = $model;
			$new['entity_id'] = $entity_id;
			$new['alias']     = $alias;
			
			try {
				if( !isset( $old ) ) { // if no file already exists for this model
					$this->upload( $new );
				}
				else { // replace an existing model file
					$this->replace( $old, $new );
				}
			}
			catch( Exception $e ) {
				throw new Exception( $e->getMessage() );
			}
		}
		else {
			throw new Exception( $this->upload_error( $new['error'] ) );
		}
	}

	/**
	 * PRIVATE METHODS
	 */

	/**
	 * Uploads a physical file to a specified location on the server.
	 *
	 * @param		$file		The file array to be uploaded
	 * @return	mixed		The id of the binary record created.
	 */
	private function upload( $attachment ) {
		if( !empty( $attachment['tmp_name'] ) && is_uploaded_file( $attachment['tmp_name'] ) ) {
			$this->write( $attachment );
		}
		else {
			throw new Exception( 'Ack! This isn\'t an uploaded file.' );
		}
	}
	
	/**
	 * Deletes an existing physical file before uploading and saving a
	 * new one.
	 *
	 * @param 	$old		the old file object
	 * @param		$new		the new file object
	 * @return	string		id of the new binary object record
	 */
	private function replace( $old, $new ) {
		$this->unlink( FILE_ROOT . $old['path'] );
		return $this->upload( $new );
	}
	
	/**
	 * Saves a physical file to the file system and creates an associated database record.
	 *
	 * @param	$file		The file array
	 */
	private function write( $attachment ) {
		/**
		 * Determine where the file should be saved
		 */
		$save_as = APP . 'plugins/polyclip/webroot' . $this->asset_path() . '/' . $attachment['name'];
		while( file_exists( $save_as ) ) {
			$save_as = APP . 'plugins/polyclip/webroot' . $this->asset_path() . '/' . $attachment['name'];
		}

		try {
			if( move_uploaded_file( $attachment['tmp_name'], $save_as ) ) {
				$attachment['mimetype'] = $attachment['type'];
				$attachment['path']     = str_replace ( APP . 'plugins/polyclip/webroot', '', $save_as );
				$attachment['uri']      = $attachment['path'];
				
				$data['Attachment'] = $attachment;
				
				if( preg_match( '/^image\//', $data['Attachment']['mimetype'] ) ) {
					$info = getimagesize( $save_as );
					
					$data['ImageAttachment']['width']  = $info[0];
					$data['ImageAttachment']['height'] = $info[1];
				}
				
				$this->saveAll( $data );

				try {
					$this->mode( $save_as );
				}
				catch( Exception $e ) {
					/**
					 * Fail silently since there's nothing to be done about the inability to
					 * change perms from within the runtime context.
					 */
				}
			}
			else {
				throw new Exception( 'Unable to save file (' . $save_as . ')' );
			}
		}
		catch( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}
	
	/**
	 * Deletes a file
	 *
	 * @param		$path
	 */
	private function unlink( $path = null ) {
		if( file_exists( $path ) ) {
			$this->log( 'Destroying ' . $path, LOG_DEBUG );
			unlink( $path );
		}
		else {
			$this->log( $path . ' does not exist.', LOG_DEBUG );
		}
	}
	
	/**
	 * Builds a random asset path.  This method assumes that a bin directory structure
	 * exists containing a bin directory
	 *
	 * @param       depth           The hashed depth at which files will be stored.  For the
	 *                              the default value of 2, a path will be returned as
	 *                              "[a-z]/[a-z]".
	 * @return      The bin directory hash in the form "bin/[a-z]/[a-z]".  Leading
	 *              and trailing slashes are removed for readability when concatenating.
	 */
	private function asset_path( $root = '/assets', $depth = 2 ) {
		$path = array( $root );
		for( $i = 0; $i < $depth; $i++ ) {
			array_push( $path, $this->random_char() );
		}
		return implode( '/', $path );
	}
	
	/**
	 * Selects a random, lowercase letter between a and z.
	 *
	 * @return      A lowercase letter between a and z.
	 */
	private function random_char() {
		return chr( ( rand( 0, 25 ) ) + 97 );
	}
	
	/**
	 * Sets permissions on a file.
	 *
	 * @param	$file		The absolute file path.
	 * @return	void
	 */
	private function mode( $file ) {
		try {
			chmod( $file, 0644 );
		}
		catch( Exception $e ) {
			throw new Exception( 'Unable to set permissions on ' . basename( $file ) . '. ' . $e->getMessage() );
		}
	}
	
	/**
	 * Returns a human readable error message.
	 *
	 * @param		$code		The PHP error code (0-6)
	 * @return 	string
	 */
	private function upload_error( $code ) {
		switch( $code ) { 
			case UPLOAD_ERR_INI_SIZE: 
				return 'The uploaded file exceeds the upload_max_filesize directive in php.ini'; 
			case UPLOAD_ERR_FORM_SIZE: 
				return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'; 
			case UPLOAD_ERR_PARTIAL: 
				return 'The uploaded file was only partially uploaded'; 
			case UPLOAD_ERR_NO_FILE: 
				return 'No file was uploaded'; 
			case UPLOAD_ERR_NO_TMP_DIR: 
				return 'Missing a temporary folder'; 
			case UPLOAD_ERR_CANT_WRITE: 
				return 'Failed to write file to disk'; 
			case UPLOAD_ERR_EXTENSION: 
				return 'File upload stopped by extension'; 
			default: 
				return 'Unknown upload error'; 
		} 
	}
}
