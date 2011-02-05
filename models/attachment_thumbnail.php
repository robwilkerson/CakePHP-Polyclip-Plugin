<?php

class AttachmentThumbnail extends AppModel {
	public $name      = 'AttachmentThumbnail';
	public $useTable  = 'polyclip_thumbnails'; # non-standard to avoid conflict
	
  public $belongsTo = array(
		'Image' => array( 'className' => 'Polyclip.Attachment', 'foreignKey' => 'polyclip_attachment_id', 'dependent' => true )
	);
  public $hasOne = array(
		'ImageAttachment' => array( 'className' => 'Polyclip.ImageAttachment', 'foreignKey' => 'entity_id', 'dependent' => true )
	);
	
	/**
	 * CALLBACK METHODS
	 */
	
	public function afterSave() {
		/**
		 * Save image details for each thumbnail. This will probably happen
		 * within a loop, so the create() method must be called.
		 */
		$data['ImageAttachment']['model']     = $this->alias;
		$data['ImageAttachment']['entity_id'] = $this->id;
		$data['ImageAttachment']['width']     = round( $this->data[$this->alias]['width'] );
		$data['ImageAttachment']['height']    = round( $this->data[$this->alias]['height'] );
		
		$this->ImageAttachment->create();
		$this->ImageAttachment->save( $data );
	}
	
	/**
	 * PUBLIC METHODS
	 */
	
  /**
   * Generates a thumbnail from the specifications.
   *
   * @param   $method
   * @param   $attachment
   * @param   $thumb_alias
   * @param   $max_w
   * @param   $max_h
   * @param   $quality
   * @return  void
   * @access  public
   */
	public function generate( $method, $attachment, $thumb_alias, $max_w, $max_h, $quality = 75 ) {
		if( Configure::read( 'debug' ) > 0 ) $this->log( '{AttachmentThumbnail::generate} Creating a ' . $thumb_alias . ' thumbnail not to exceed ' . $max_w . 'x' . $max_h . ' for ' . json_encode( $attachment ), LOG_DEBUG );
		
		$base_path = APP . 'plugins/polyclip/webroot';
		$base_url  = '/polyclip';
		$method    = strtolower( $method ) == 'resize_to_fill' ? 'resize_to_fill' : 'resize_to_fit';	# TODO: support other methods?
		$source    = $base_path . str_replace( $base_url, '', $attachment['url'] );
		
		# File details
		$info = pathinfo( $source );
		# Image details
		list( $src_w, $src_h, $type ) = getimagesize( $source );
		# Destination
		$save_as = $info['dirname'] . '/' . $info['filename'] . '.' . $thumb_alias . '.' . $info['extension'];
		
		
		if( file_exists( $save_as ) ) { # The file should never exist, but just in case...
			unlink( $save_as );
		}
		
		if( $max_w > $src_w && $max_h > $src_h ) { # Height & width are already smaller than the thumbnail
			if( Configure::read( 'debug' ) > 0 ) $this->log( 'Image is already smaller than this thumbnail\'s max dimensions', LOG_DEBUG );
			
			$scaled_w = $src_w;
			$scaled_h = $src_h;
		}
		else {
			switch( strtolower( $method ) ) {
				case 'resize_to_fit':	# maintain aspect ratio
					# RESIZE TO FIT
					if( Configure::read( 'debug' ) > 0 ) $this->log( '{AttachmentThumbnail::generate} Resizing to fit', LOG_DEBUG );
					
					$scale    = min( $max_w/$src_w, $max_h/$src_h );
					$scaled_w = $src_w * $scale;
					$scaled_h = $src_h * $scale;
					break;
				
				case 'resize_to_fill':
					# RESIZE TO FILL
					# Resize to whichever dimension needs to shrink less and crop
					# the other, clipping half from each side.
					if( Configure::read( 'debug' ) > 0 ) $this->log( '{AttachmentThumbnail::generate} Resizing to fill', LOG_DEBUG );
					
          /* resize to max, then crop to center */
          $max_w   = $max_w > $src_w ? $src_w : $max_w;
          $scale_x = $max_w / $src_w;
          
          $max_h   = $max_h > $src_h ? $src_h : $max_h;
          $scale_y = $max_h / $src_h;
          
          if( $scale_x < $scale_y ) {
            $start_x = ( $src_w - ( $max_w / $scale_y ) ) / 2;
            $src_w   = $max_w / $scale_y;
          }
          else {
            $start_y = ( $src_h - ( $max_h / $scale_x ) ) / 2;
            $src_h   = $max_h / $scale_x;
          }
          $scaled_w  = $max_w;
          $scaled_h  = $max_h;
          break;
			}
		}
			
		switch( strtolower( $info['extension'] ) ) {
			case 'gif':
				$copy = imagecreatefromgif( $source );
				break;
			
			case 'png':
				$copy = imagecreatefrompng( $source );
				break;
			
			case 'jpg':
			case 'jpeg':
				$copy = imagecreatefromjpeg( $source );
				break;
			
			default :
				if( Configure::read( 'debug' ) > 0 ) $this->log( '[AttachmentThumbnail::generate] Unexpected extension. Unable to CREATE ' . $thumb_alias . ' thumbnail (' . $method . ') for ' . json_encode( $attachment ), LOG_WARNING );
				return false;
				break;
		}
		
		# Create a new, empty image with a few options
		$thumb = imagecreatetruecolor( $scaled_w, $scaled_h );
		imagealphablending( $thumb, false );
		imagesavealpha( $thumb, true );
		
		# Resample
		$start_x = isset( $start_x ) ? $start_x : 0;
		$start_y = isset( $start_y ) ? $start_y : 0;
    
    imagecopyresampled( $thumb, $copy, 0, 0, $start_x, $start_y, $scaled_w, $scaled_h, $src_w, $src_h );
		
		# Write to file
		switch( strtolower( $info['extension'] ) ) {
			case 'gif':
				imagegif( $thumb, $save_as, $quality );
				break;
			
			case 'png':
				imagepng( $thumb, $save_as, round( $quality / 10 ) );
				break;
			
			case 'jpg':
			case 'jpeg':
				imagejpeg( $thumb, $save_as, $quality );
				break;
			
			default:
				if( Configure::read( 'debug' ) > 0 ) $this->log( '[AttachmentThumbnail::generate] Unexpected extension. Unable to WRITE ' . $thumb_alias . ' thumbnail (' . $method . ') for ' . json_encode( $attachment ), LOG_WARNING );
				return false;
				break;
		}
		
		imagedestroy( $thumb );
		imagedestroy( $copy );
		
		return array( 'path' => str_replace( APP, '/', $save_as ), 'url' => str_replace( $base_path, $base_url, $save_as ), 'width' => $scaled_w, 'height' => $scaled_h, 'size' => filesize( $save_as ) );
	}
}
