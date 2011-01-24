<?php

/**
 * Manages physical files attached to other models.
 */
class AttachableBehavior extends ModelBehavior {
	
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
		
		$this->attachables = isset( $model->actsAs['Polyclip.attachable'] )
			? $model->actsAs['Polyclip.attachable']
			: array( 'Attachment' );
		
		/**
		 * Bind the current model to the Attachment model for each attachable
		 */
		foreach( $this->attachables as $alias => $attachable ) {
			$model->bindModel(
				array( 'hasOne' => array( $alias => array( 'className' => 'Polyclip.Attachment', 'foreignKey' => 'entity_id', 'conditions' => array( $alias . '.model' => $model->alias ) ) ) )
			);
		}
	}
	
	/**
	 * CALLBACK METHODS
	 */
  
  public function beforeFind( $model, $query ) {
    # TODO: Can we do something to get the right stuff in one call
    #       and avoid the work we're currently doing in afterFind?
  }
  
  public function afterFind( $model, $results, $primary ) {
    /**
     * Manually attach thumbnail information
     * TODO: Be a lot better if I could modify the containable bits
     *       on beforeFind()
     */
    foreach( $results as $i => $result ) {
      foreach( array_intersect_assoc( $result, $this->attachables ) as $alias => $attachment ) {
        $url_info  = pathinfo( $attachment['url'] );
        $path_info = pathinfo( $attachment['path'] );
        
        $thumbnails = $model->$alias->AttachmentThumbnail->find( 'all' );
        
        if( !empty( $thumbnails ) ) {
          if( !isset( $results[$i]['Thumbnail'] ) ) {
            $results[$i]['Thumbnail'] = array();
          }
          
          foreach( $thumbnails as $thumb ) {
            $thumb_alias = $thumb['AttachmentThumbnail']['alias'];
            $results[$i]['Thumbnail'][$thumb_alias] = array();
            $results[$i]['Thumbnail'][$thumb_alias]['size']   = $thumb['AttachmentThumbnail']['size'];
            $results[$i]['Thumbnail'][$thumb_alias]['width']  = $thumb['ImageAttachment']['width'];
            $results[$i]['Thumbnail'][$thumb_alias]['height'] = $thumb['ImageAttachment']['height'];
            $results[$i]['Thumbnail'][$thumb_alias]['url']    =
              $url_info['dirname'] . '/' . $url_info['filename'] . '.' . $thumb_alias . '.' . $url_info['extension'];
            $results[$i]['Thumbnail'][$thumb_alias]['path']    =
              $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $thumb_alias . '.' . $path_info['extension'];
          }
        }
      }
    }
    
    return $results;
  }
	
	public function afterSave( $model, $created ) {
    $entity_id = $created
			? $model->getLastInsertId()
			: $model->id;
			
		foreach( $this->attachables as $alias => $attachable ) {
			if( isset( $model->data[$alias] ) ) {
				try {
					$model->{$alias}->attach( $model->alias, $entity_id, $alias, $model->data[$alias]['upload'] );
				}
				catch( Exception $e ) {
					# TODO: Do something more graceful than exit()?
					exit( '{' . $alias . '::attach}' . $e->getMessage() );
				}
			}
		}
		
		return true;
	}
}
