<?php

class AttachmentComponent extends Object {
	/**
	 * CALLBACK METHODS
	 */
	public function beforeRender( $controller ) {
		#
		# Pull attachment details from the associated model so they can be
		# rendered by the element.
		#
		$attachments = isset( $controller->{$controller->modelClass}->actsAs['Polyclip.attachable'] )
			? $controller->{$controller->modelClass}->actsAs['Polyclip.attachable']
			: array( 'Attachment' => null );
      
		$controller->set( compact( 'attachments' ) );
	}
}
