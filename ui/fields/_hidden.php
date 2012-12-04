<?php
$attributes = array();
$attributes[ 'type' ] = 'hidden';
$attributes[ 'value' ] = $value;
$attributes = FiltersForm::merge_attributes( $attributes, $name, FiltersForm::$field_type, $options );
?>
<input<?php FiltersForm::attributes( $attributes, $name, FiltersForm::$field_type, $options ); ?> />
