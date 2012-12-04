<?php
wp_enqueue_style( 'farbtastic' );

if ( !is_admin() )
    wp_register_script( 'farbtastic', admin_url( "js/farbtastic.js" ), array( 'jquery' ), '1.2', true );

wp_enqueue_script( 'farbtastic' );

$attributes = array();
$attributes[ 'type' ] = 'text';
$attributes[ 'value' ] = $value;
$attributes[ 'tabindex' ] = 2;
$attributes = FiltersForm::merge_attributes( $attributes, $name, FiltersForm::$field_type, $options );
?>
<input<?php FiltersForm::attributes( $attributes, $name, FiltersForm::$field_type, $options ); ?> />

<div id="color_<?php echo $attributes[ 'id' ]; ?>"></div>

<script type="text/javascript">
    if ( 'undefined' == typeof filters_farbastic_changing ) {
        var filters_farbastic_changing = false;
    }

    jQuery( function () {
        jQuery( '#color_<?php echo $attributes[ 'id' ]; ?>' ).hide();

        var filters_farbtastic_<?php echo filters_clean_name( $attributes[ 'id' ] ); ?> = jQuery.farbtastic(
                '#color_<?php echo $attributes[ 'id' ]; ?>',
                function ( color ) {
                    filters_pickColor( '#<?php echo $attributes[ 'id' ]; ?>', color );
                }
        );

        jQuery( '#<?php echo $attributes[ 'id' ]; ?>' ).on( 'focus blur', function () {
            jQuery( '#color_<?php echo $attributes[ 'id' ]; ?>' ).slideToggle();
        } );

        jQuery( '#<?php echo $attributes[ 'id' ]; ?>' ).on( 'keyup', function () {
            var color = jQuery( this ).val();

            filters_farbastic_changing = true;

            if ( '' != color.replace( '#', '' ) && color.match( '#' ) )
                filters_farbtastic_<?php echo filters_clean_name( $attributes[ 'id' ] ); ?>.setColor( color );

            filters_farbastic_changing = false;
        } );

        if ( 'undefined' == filters_pickColor ) {
            function filters_pickColor ( id, color ) {
                if ( !filters_farbastic_changing )
                    jQuery( id ).val( color.toUpperCase() );
            }
        }
    } );
</script>
