<?php

class ImageAttachment extends AppModel {
	public $name      = 'ImageAttachment';
	public $useTable  = 'polyclip_images'; # non-standard to avoid conflict
	
	public $belongsTo = array(
		'Attachment'          => array( 'className' => 'Polyclip.Attachment', 'foreignKey' => 'entity_id' ),
    'AttachmentThumbnail' => array( 'className' => 'Polyclip.AttachmentThumbnail', 'foreignKey' => 'entity_id' )
	);
}
