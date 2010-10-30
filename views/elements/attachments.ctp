<?php # new PHPDump( $attachments, 'Attachments' ); ?>
<?php # new PHPDump( $data ); ?>

<?php foreach( $attachments as $attachment ): ?>
	<?php echo $this->Form->input( $attachment . '.upload', array( 'type' => 'file', 'label' => ucwords( $attachment ) ) ) ?>
<?php endforeach; ?>
