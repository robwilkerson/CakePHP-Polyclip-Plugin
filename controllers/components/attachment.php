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
			? array_keys( $controller->{$controller->modelClass}->actsAs['Polyclip.attachable'] )
			: array( 'File' );
		
		$controller->set( compact( 'attachments' ) );
	}
}
