<?php

/**
 * Manages physical files attached to other models.
 */
class AttachableBehavior extends ModelBehavior {
  private $overwrite = null;
	
	/**
	 * TODO: Add option for required attachments
	 */

	/**
	 * Initiate behavior for the model using specified settings.
   *
	 * @param 	object 	$model 		  Model using the behavior
	 * @param 	array 	$settings 	Settings overrides.
	 */
	public function setup( $model, $settings = array() ) {
		# Defaults
		$defaults = array();
		
		if( isset( $settings ) && !is_array( $settings ) ) {
			$settings = array( $settings );
		}
		$this->settings[$model->alias] = array_merge( $defaults, $settings );
    
    if( empty( $this->settings[$model->alias] ) ) {
      $this->settings[$model->alias] = array( 'Attachment' => array() );
    }
	}
	
	/**
	 * CALLBACK METHODS
	 */
  
  public function beforeFind( $model, $query ) {
    $this->associate( $model );
    
    # TODO: Can we do something to get the right stuff in one call
    #       and avoid the work we're currently doing in afterFind?
  }
  
  public function afterFind( $model, $results, $primary ) {
    $attachables = $this->settings[$model->alias];
    
    /**
     * Manually attach thumbnail information
     * TODO: Be a lot better if I could modify the containable bits
     *       on beforeFind()
     */
    foreach( $results as $i => $result ) {
      foreach( array_intersect_assoc( $result, $attachables ) as $alias => $attachment ) {
        if( !empty( $result[$alias]['id'] ) ) {
          $url_info   = pathinfo( $attachment['url'] );
          $path_info  = pathinfo( $attachment['path'] );
          $thumbnails = $model->$alias->AttachmentThumbnail->find( 'all', array( 'conditions' => array( 'AttachmentThumbnail.polyclip_attachment_id' => $result[$alias]['id'] ) ) );
          
          if( !empty( $thumbnails ) ) {
            if( !isset( $results[$i]['Thumbnail'] ) ) {
              $results[$i]['Thumbnail'] = array();
            }
            
            foreach( $thumbnails as $thumb ) {
              $thumb_alias = $thumb['AttachmentThumbnail']['alias'];
              
              $results[$i]['Thumbnail'][$alias][$thumb_alias] = array();
              $results[$i]['Thumbnail'][$alias][$thumb_alias]['size']   = $thumb['AttachmentThumbnail']['size'];
              $results[$i]['Thumbnail'][$alias][$thumb_alias]['width']  = $thumb['ImageAttachment']['width'];
              $results[$i]['Thumbnail'][$alias][$thumb_alias]['height'] = $thumb['ImageAttachment']['height'];
              $results[$i]['Thumbnail'][$alias][$thumb_alias]['url']    =
                $url_info['dirname'] . '/' . $url_info['filename'] . '.' . $thumb_alias . '.' . $url_info['extension'];
              $results[$i]['Thumbnail'][$alias][$thumb_alias]['path']    =
                $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $thumb_alias . '.' . $path_info['extension'];
            }
          }
        }
        else {
          /**
           * If the id field is empty, just clear the attachment array
           * for convenience.
           *
           * TODO: This is pretty ghetto. Try changing polymorphic.
           */
          $results[$i][$alias]              = array();
          $results[$i]['Thumbnail'][$alias] = array();
        }
      }
    }
    
    return $results;
  }
  
  public function beforeSave( $model ) {
    $creating    = empty( $model->id );
    $attachables = $this->settings[$model->alias];
    
    /**
     * Save off current attachment data if
     *   - we're updating (not creating) a record
     *   - we're including an attachment
     *   - an attachment already exists
     */
    if( !$creating ) { # editing an existing record
      foreach( $attachables as $alias => $attachment ) {
        if( isset( $model->data[$alias] ) ) { # An attachment is being uploaded
          $this->associate( $model ); # Ensure that the models are associated
          
          $existing = $model->find( 'first', array( 'conditions' => array( $model->alias . '.id' => $model->id ) ) );
          
          if( !empty( $existing[$alias] ) ) { # An attachment already exists
            $this->overwrite = $existing;
          }
        }
      }
    }
  }
	
	public function afterSave( $model, $created ) {
    $attachables = $this->settings[$model->alias];
    $entity_id   = $created
			? $model->getLastInsertId()
			: $model->id;
			
		foreach( $attachables as $alias => $attachable ) {
      
			if( isset( $model->data[$alias] ) && $model->data[$alias]['upload']['error'] != UPLOAD_ERR_NO_FILE ) {
				try {
          $model->{$alias}->attach( $model->alias, $entity_id, $alias, $model->data[$alias]['upload'], $this->overwrite );
				}
				catch( Exception $e ) {
					# TODO: Do something more graceful than exit()?
					exit( '{' . $alias . '::attach}' . $e->getMessage() );
				}
			}
		}
		
		return true;
	}
  
  /**
   * Bind the current model to the Attachment model for each attachable.
   *
   * @return  void
   * @access  private
   */
  private function associate( $model ) {
    $attachables  = $this->settings[$model->alias];
    $associations = $model->getAssociated();
    
    foreach( $attachables as $alias => $attachable ) {
      if( !isset( $associations[$alias] ) ) {
        $model->bindModel(
          array( 'hasOne' => array( $alias => array( 'className' => 'Polyclip.Attachment', 'foreignKey' => 'entity_id', 'conditions' => array( $alias . '.model' => $model->alias ) ) ) )
        );
      }
    }
  }
}
