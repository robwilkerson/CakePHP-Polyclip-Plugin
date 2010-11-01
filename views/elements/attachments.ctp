<?php $this->Html->css( array( '/polyclip/js/jquery/plugins/colorbox/colorbox.css' ), null, array( 'inline' => false ) ); ?>
<?php $this->Html->script( array( '/polyclip/js/jquery/plugins/colorbox/jquery.colorbox-min.js', '/polyclip/js/admin' ), array( 'inline' => false, 'once' => true ) ) ?>

<?php foreach( $attachments as $attachment ): ?>
	<?php if( isset( $this->data[$attachment] ) ): ?>
		<div class="input attachment">
			<label><?php echo ucwords( $attachment ) ?></label>
			<a href="#"><?php echo basename( $this->data[$attachment]['path'] ) ?></a>
		</div>
	<?php else: ?>
		<?php echo $this->Form->input( $attachment . '.upload', array( 'type' => 'file', 'label' => ucwords( $attachment ) ) ) ?>
	<?php endif; ?>
<?php endforeach; ?>

<?php new PHPDump( $data ); ?>
