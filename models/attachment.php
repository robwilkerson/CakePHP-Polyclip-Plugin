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
	
	public $hasMany = array(
		'AttachmentThumbnail' => array( 'className' => 'Polyclip.AttachmentThumbnail', 'foreignKey' => 'polyclip_attachment_id', 'dependent' => true )
	);
	public $hasOne = array(
		'AttachmentImage' => array( 'className' => 'Polyclip.AttachmentImage', 'foreignKey' => 'entity_id', 'dependent' => true )
	);

	public $validate  = array();
	
	/**
	 * PUBLIC METHODS
	 */
	
	/**
	 * Creates or replaces a physical file, attaching it to another model
	 * in the process.
	 *
	 * @param		$model			string		The name of the model to which the file
	 *  															will be attached.
	 * @param		$entity_id	uuid			ID of the model instance
	 * @param		$alias			string		The model alias as specified when
	 * 																AttachableBehavior is attached
	 * @param		$new				array			The newly uploaded attachment
	 * @param		$old				array			The attachment to be replaced
	 * @return 	uuid				The new file's identifier
	 */
	public function attach( $model, $entity_id, $alias, $new, $old = null ) {
		$this->log( 'Attaching a file to a(n) ' . $model . ' with id ' . $entity_id, LOG_DEBUG );
		
		if( !is_array( $new ) || ( isset( $old ) && !is_array( $old ) ) ) {
			throw new Exception( 'Polyclip.Attachment::attach() expects at least one binary array argument' );
		}
		
		if( $new['error'] === UPLOAD_ERR_OK ) {
			$this->bindModel(
				array( 'belongsTo' => array( $model => array( 'className' => $model, 'foreignKey' => 'entity_id', 'conditions' => array( $this->alias . '.model' => $model ) ) ) )
			);
			
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
	
	public function has_thumbnails() {
		# TODO: How do we determine whether thumbnails exist?
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
		$base_path = APP . 'plugins/polyclip/webroot';
		$base_url  = '/polyclip';
		
		/**
		 * Determine where the file should be saved
		 */
		$save_as = $base_path . $this->asset_path() . '/' . $attachment['name'];
		while( file_exists( $save_as ) ) {
			$save_as = $base_path . $this->asset_path() . '/' . $attachment['name'];
		}

		try {
			if( move_uploaded_file( $attachment['tmp_name'], $save_as ) ) {
				$attachment['mimetype'] = $attachment['type'];
				$attachment['path']     = str_replace( APP, '/', $save_as );
				$attachment['url']      = str_replace( $base_path, $base_url, $save_as );
				
				$data[$attachment['alias']] = $attachment;
				
				if( preg_match( '/^image\//', $data[$attachment['alias']]['mimetype'] ) ) {
					$info = getimagesize( $save_as );
					
					$data['AttachmentImage']['model']  = $this->alias;
					$data['AttachmentImage']['width']  = $info[0];
					$data['AttachmentImage']['height'] = $info[1];
					
					# Generate thumbnails, if necessary
					if( isset( $this->{$attachment['model']}->actsAs['Polyclip.attachable'][$attachment['alias']]['Thumbnails'] ) ) {
						$thumbnails = $this->{$attachment['model']}->actsAs['Polyclip.attachable'][$attachment['alias']]['Thumbnails'];
						$data['AttachmentThumbnail'] = array();
						
						foreach( $thumbnails as $thumbnail_alias => $details ) {
							# thumbnails should be aliased as small, medium, large, square, etc.
							$thumb = $this->AttachmentThumbnail->generate( $details['method'], $data[$attachment['alias']], $thumbnail_alias, $details['width'], $details['height'], 85 );
							$thumb['alias'] = $thumbnail_alias;
							
							array_push( $data['AttachmentThumbnail'], $thumb );
						}
					}
				}
				
				try {
					$this->mode( $save_as );
				}
				catch( Exception $e ) {
					/**
					 * Fail silently since there's nothing to be done about the inability to
					 * change perms from within the runtime context.
					 */
				}
				
				$this->saveAll( $data );
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
	private function mode( $file, $octal = 0644 ) {
		try {
			chmod( $file, $octal );
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
