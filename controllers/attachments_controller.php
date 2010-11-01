<?php

class AttachmentsController extends AppController {
	public $name = 'Attachments';

	/**
	 * ADMINISTRATIVE METHODS
	 */

	/**
	 * Convenience method that redirects to the list action.
	 */
	public function admin_edit() {
		new PHPDump( $this, $this->alias );
		exit( 'EXITING' );
	}
}
