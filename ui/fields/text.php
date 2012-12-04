<?php
$attributes = array();
$attributes[ 'type' ] = 'text';
$attributes[ 'value' ] = $value;
$attributes[ 'tabindex' ] = 2;
$attributes = FiltersForm::merge_attributes( $attributes, $name, FiltersForm::$field_type, $options );
?>
<input<?php FiltersForm::attributes( $attributes, $name, FiltersForm::$field_type, $options ); ?> />
<?php
FiltersForm::regex( FiltersForm::$field_type, $options );