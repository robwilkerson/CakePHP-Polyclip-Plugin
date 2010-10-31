<?php

class AttachmentImage extends AppModel {
	public $name      = 'AttachmentImage';
	public $useTable  = 'polyclip_images'; # non-standard to avoid conflict
	public $actsAs    = array(
		'Polyclip.polymorphic' => array(
			'classField' => 'model',
			'foreignKey' => 'entity_id'
		)
	);
}
