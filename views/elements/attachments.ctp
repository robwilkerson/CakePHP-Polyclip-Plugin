<?php # new PHPDump( $attachments, 'Attachments' ); ?>
<?php # new PHPDump( $data ); ?>

<?php foreach( $attachments as $attachment ): ?>
	<?php if( isset( $this->data[$attachment] ) ): ?>
		<div class="input free-text">
			<label><?php echo ucwords( $attachment ) ?></label>
			<a href="#"><?php echo basename( $this->data[$attachment]['path'] ) ?></a>
		</div>
	<?php else: ?>
		<?php echo $this->Form->input( $attachment . '.upload', array( 'type' => 'file', 'label' => ucwords( $attachment ) ) ) ?>
	<?php endif; ?>
<?php endforeach; ?>
