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
  
  public function afterFind( $model, $results, $primary ) {
    /**
     * Rebuild the data structure into something a little more intuitive
     * TODO: Could this be more efficient using Set? Something else?
     */
    $modified = array();
    foreach( $results as $index => $result ) {
      foreach( array_intersect_assoc( $result, $this->attachables ) as $alias => $attachment ) {
        $url_info  = pathinfo( $attachment['url'] );
        $path_info = pathinfo( $attachment['path'] );
        
        if( !empty( $attachment['ImageAttachment'] ) ) {
          $results[$index][$alias]['width'] = $attachment['ImageAttachment']['width'];
          $results[$index][$alias]['height'] = $attachment['ImageAttachment']['height'];
        }
        unset( $results[$index][$alias]['ImageAttachment'] );
        
        if( !empty( $attachment['AttachmentThumbnail'] ) ) {
          foreach( $attachment['AttachmentThumbnail'] as $thumb ) {
            $size = $thumb['alias'];
            
            if( !isset( $results[$index]['Thumbnail'] ) ) {
              $results[$index]['Thumbnail'] = array();
            }
            $results[$index]['Thumbnail'][$size] = array();
            $results[$index]['Thumbnail'][$size]['width']  = $thumb['ImageAttachment']['width'];
            $results[$index]['Thumbnail'][$size]['height'] = $thumb['ImageAttachment']['height'];
            $results[$index]['Thumbnail'][$size]['size']   = $thumb['size'];
            $results[$index]['Thumbnail'][$size]['url']    =
              $url_info['dirname'] . '/' . $url_info['filename'] . '.' . $size . '.' . $url_info['extension'];
            $results[$index]['Thumbnail'][$size]['path']    =
              $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $size . '.' . $path_info['extension'];
            
          }
        }
        unset( $results[$index][$alias]['AttachmentThumbnail'] );
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
