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
			: array( 'File' );
		
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
