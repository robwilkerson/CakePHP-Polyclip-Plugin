<?php foreach( $attachments as $attachment ): ?>
  <?php echo $this->Form->input( $attachment . '.upload', array( 'type' => 'file', 'label' => ucwords( $attachment ) ) ) ?>
<?php endforeach; ?>
