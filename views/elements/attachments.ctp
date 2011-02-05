<?php foreach( $attachments as $alias => $details ): ?>
  <?php $label = !empty( $details['label'] ) ? $details['label'] : Inflector::humanize( Inflector::underscore( $alias ) ) ?>
  
  <?php echo $this->Form->input( $alias . '.upload', array( 'type' => 'file', 'label' => $label ) ) ?>
<?php endforeach; ?>
