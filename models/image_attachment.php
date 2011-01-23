<?php

class ImageAttachment extends AppModel {
	public $name      = 'ImageAttachment';
	public $useTable  = 'polyclip_images'; # non-standard to avoid conflict
	
	public $belongsTo = array(
		'Attachment'          => array( 'className' => 'Polyclip.Attachment', 'foreignKey' => 'entity_id' ),
    'AttachmentThumbnail' => array( 'className' => 'Polyclip.AttachmentThumbnail', 'foreignKey' => 'entity_id' )
	);
	
	/**
	 * CALLBACK METHODS
	 */
	
	public function afterSave( $created ) {
		$attachment  = $this->Attachment->read( null, $this->data['ImageAttachment']['polyclip_attachment_id'] );
		$attached_to = array_shift( array_keys( $this->Attachment->belongsTo ) );

		# Assuming 1 belongsTo association. If more, FAIL NOW so it can be fixed.
		if( count( $this->Attachment->belongsTo ) !== 1 ) {
			new PHPDump( $this->Attachment->belongsTo );
			exit( '[ImageAttachment::afterSave] Houston, we\'re about to have a big problem. This attachment belongs to more than one model' );
		}
		/** 
		new PHPDump( $attached_to, 'ATTACHED TO' );
		new PHPDump( $this->Attachment, 'ATTACHMENT' );
		
		new PHPDump( $this->Attachment->data['Attachment']['alias'], 'ALIAS' );
		
		exit( 'EXIT' );
		*/
		/**
		new PHPDump( $attached_to, 'ATTACHED TO' );
		new PHPDump( $this->Attachment, 'ATTACHMENT' );
		exit( 'EXIT' );
		*/

		$thumbnails = isset( $this->Attachment->{$attached_to}->actsAs['Polyclip.attachable'][$this->Attachment->data['Attachment']['alias']]['Thumbnails'] )
			? $this->Attachment->{$attached_to}->actsAs['Polyclip.attachable'][$this->Attachment->data['Attachment']['alias']]['Thumbnails']
			: array();
		
		foreach( $thumbnails as $thumbnail_alias => $details ) {
			# thumbnails should be aliased as small, medium, large, square, etc.
			$this->ImageAttachmentThumbnail->generate( $details['method'], $attachment, $thumbnail_alias, $details['width'], $details['height'], 85 );
		}
	}
}
