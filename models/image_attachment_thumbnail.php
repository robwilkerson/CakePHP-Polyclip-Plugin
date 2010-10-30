<?php

class ImageAttachmentThumbnail extends AppModel {
	public $name      = 'ImageAttachmentThumbnail';
	public $useTable  = 'polyclip_images'; # yes, the same table is used for ImageAttachment
	
	public $belongsTo = array(
		'Image' => array( 'className' => 'Polyclip.ImageAttachment', 'foreignKey' => 'parent_id' )
	);
	
	/**
	 * PUBLIC METHODS
	 */
	
	public function generate( $method, $attachment, $thumb_alias, $max_w, $max_h, $quality = 75 ) {
		$this->log( 'Creating a thumbnail not to exceed ' . $max_w . 'x' . $max_h . ' (' . $thumb_alias . ') for ' . json_encode( $attachment ), LOG_DEBUG );
		
		$method = strtolower( $method ) == 'resize_to_fill' ? 'resize_to_fill' : 'resize_to_fit';	# TODO: support other methods?
		$source = APP . 'plugins/polyclip/webroot' . $attachment['Attachment']['path'];
		
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
			$this->log( 'Image is already smaller than this thumbnail\'s max dimensions', LOG_DEBUG );
			
			$scaled_w = $src_w;
			$scaled_h = $src_h;
		}
		else {
			switch( strtolower( $method ) ) {
				case 'resize_to_fit':	# maintain aspect ratio
					# RESIZE TO FIT
					$this->log( 'Resizing to fit', LOG_DEBUG );
					
					$scale    = min( $max_w/$src_w, $max_h/$src_h );
					$scaled_w = $src_w * $scale;
					$scaled_h = $src_h * $scale;
					break;
				
				case 'resize_to_fill':
					# RESIZE TO FILL
					# Resize to whichever dimension needs to shrink less and crop the other
					$this->log( 'Resizing to fill', LOG_DEBUG );
					
					$scale    = max( $max_w/$src_w, $max_h/$src_h );
					$scaled_w = $src_w * $scale;
					$scaled_h = $src_h * $scale;
					
					if( $scaled_w > $max_w ) {
						$start_x = ( $scaled_w - $max_w ) / 2;
					}
					if( $scaled_h > $max_h ) {
						$start_y = ( $scaled_h - $max_h ) / 2;
					}
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
				$this->log( '[ImageAttachmentThumbnail::generate] Unexpected extension. Unable to CREATE ' . $thumb_alias . ' thumbnail (' . $method . ') for ' . json_encode( $attachment ), LOG_WARNING );
				return false;
				break;
		}
		
		# Create a new, empty image with a few options
		$this->log( 'Creating a new, ' . $scaled_w . 'x' . $scaled_h . ' thumbnail', LOG_DEBUG );
		
		$thumb = imagecreatetruecolor( $scaled_w, $scaled_h );
		imagealphablending( $thumb, false );
		imagesavealpha( $thumb, true );
		
		# Resample
		$start_x = isset( $start_x ) ? $start_x : 0;
		$start_y = isset( $start_y ) ? $start_y : 0;
		imagecopyresampled( $thumb, $copy, 0, 0, $start_x, $start_y, $scaled_w, $scaled_h, $src_w, $src_h );
		
		# Write to file
		$this->log( 'Writing ' . $thumb . ' to ' . $save_as, LOG_DEBUG );
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
				$this->log( '[ImageAttachmentThumbnail::generate] Unexpected extension. Unable to WRITE ' . $thumb_alias . ' thumbnail (' . $method . ') for ' . json_encode( $attachment ), LOG_WARNING );
				return false;
				break;
		}
		
		imagedestroy( $thumb );
		imagedestroy( $copy );
		return true;
	}
}
