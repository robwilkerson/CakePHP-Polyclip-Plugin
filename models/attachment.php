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
    'ImageAttachment' => array( 'className' => 'Polyclip.ImageAttachment', 'foreignKey' => 'entity_id', 'dependent' => true )
  );
  public $hasMany = array(
    'AttachmentThumbnail' => array( 'className' => 'Polyclip.AttachmentThumbnail', 'foreignKey' => 'polyclip_attachment_id', 'dependent' => true )
  );

  public $validate  = array();
  
  /**
   * PUBLIC METHODS
   */
  
  /**
   * Creates or replaces a physical file, attaching it to another model
   * in the process.
   *
   * @param   $model      string    The name of the model to which the file
   *                                will be attached.
   * @param   $entity_id  uuid      ID of the model instance
   * @param   $alias      string    The model alias as specified when
   *                                AttachableBehavior is attached
   * @param   $new        array     The newly uploaded attachment
   * @param   $old        array     The attachment to be replaced
   * @return  void
   * @access  public
   */
  public function attach( $model, $entity_id, $alias, $new, $old = null ) {
    if( Configure::read( 'debug' ) > 0 ) $this->log( '{Attachment::attach} --> Attaching a file to a(n) ' . $model . ' with id ' . $entity_id, LOG_DEBUG );
    
    if( !is_array( $new ) || ( isset( $old ) && !is_array( $old ) ) ) {
      throw new Exception( 'Polyclip.Attachment::attach() expects at least one binary array argument' );
    }
    
    if( $new['error'] === UPLOAD_ERR_OK ) {
      # Evidently the association created in AttachableBehavior doesn't persist.
      $this->bindModel(
        array( 'belongsTo' => array( $model => array( 'className' => $model, 'foreignKey' => 'entity_id', 'conditions' => array( $this->alias . '.model' => $model ) ) ) )
      );
      
      $new['model']     = $model;
      $new['entity_id'] = $entity_id;
      $new['alias']     = $alias;
      
      try {
        if( !isset( $old ) ) { // if no file already exists for this model
          if( Configure::read( 'debug' ) > 0 ) $this->log( '{Attachment::attach} --> This is a new attachment', LOG_DEBUG );
          
          $this->upload( $new );
        }
        else { // replace an existing model file
          if( Configure::read( 'debug' ) > 0 ) $this->log( '{Attachment::attach} --> This is a replacement attachment', LOG_DEBUG );
          
          $this->replace( $old, $new, $alias );
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
   * Deletes an attachment record and its physical file.
   *
   * @param   array   $attachment 
   * @param   string  $alias  The alias of the attachment being deleted.
   * @return  boolean
   * @access  public
   */
  public function delete( $attachment, $alias ) {
    if( isset( $attachment['Thumbnail'][$alias] ) ) {
      $thumbnails = $attachment['Thumbnail'][$alias];

      # Delete the associated thumbnails
      foreach( $thumbnails as $thumb ) {
        $this->unlink( APP . $thumb['path'] );
        $this->AttachmentThumbnail->delete( $thumb['id'] );
      }
    }
    
    # Delete the physical file and the attachment record
    $this->unlink( APP . $attachment[$alias]['path'] );
    return parent::delete( $attachment[$alias]['id'] );
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
   * @param   $file   The file array to be uploaded
   * @return  mixed   The id of the binary record created.
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
   * @param   $old    the old object
   * @param   $new    the new object
   * @param   $alias  the attachment alias
   * @return  string    id of the new binary object record
   */
  private function replace( $old, $new, $alias ) {
    # Delete the old
    $this->delete( $old, $alias );
    
    # Upload the new
    return $this->upload( $new );
  }
  
  /**
   * Saves a physical file to the file system and creates an associated database record.
   *
   * @param $file   The file array
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
      # Attempt to create the directory if it doesn't exist
      if( !file_exists( dirname( $save_as ) ) ) {
        mkdir( dirname( $save_as ), 0777, true );
      }
      
      if( move_uploaded_file( $attachment['tmp_name'], $save_as ) ) {
        $attachment['mimetype'] = $attachment['type'];
        $attachment['path']     = str_replace( APP, '/', $save_as );
        $attachment['url']      = str_replace( $base_path, $base_url, $save_as );
        
        $data[$attachment['alias']] = $attachment;
        
        if( preg_match( '/^image\//', $data[$attachment['alias']]['mimetype'] ) ) {
          if( Configure::read( 'debug' ) > 0 ) $this->log( '{Attachment::write} --> This is an image attachment', LOG_DEBUG );
          $info = getimagesize( $save_as );
          
          $data['ImageAttachment']['model']  = $this->alias;
          $data['ImageAttachment']['width']  = $info[0];
          $data['ImageAttachment']['height'] = $info[1];
          
          # Generate thumbnails, if necessary
          if( isset( $this->{$attachment['model']}->actsAs['Polyclip.Attachable'][$attachment['alias']]['Thumbnails'] ) ) {
            $thumbnails = $this->{$attachment['model']}->actsAs['Polyclip.Attachable'][$attachment['alias']]['Thumbnails'];
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
   * @param   $path
   */
  private function unlink( $path = null ) {
    if( file_exists( $path ) ) {
      if( Configure::read( 'debug' ) > 0 ) $this->log( 'Attachment::unlink() -> Destroying attachment at' . $path, LOG_DEBUG );
      unlink( $path );
    }
    else {
      $this->log( 'Attachment::unlink() -> ' . $path . ' does not exist. No attachement was deleted.', LOG_DEBUG );
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
   * @param $file   The absolute file path.
   * @return  void
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
   * @param   $code   The PHP error code (0-6)
   * @return  string
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
