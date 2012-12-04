<?php
$date_format = array(
    'mdy' => 'mm/dd/yy',
    'dmy' => 'dd/mm/yy',
    'dmy_dash' => 'dd-mm-yy',
    'dmy_dot' => 'dd.mm.yy',
    'ymd_slash' => 'yy/mm/dd',
    'ymd_dash' => 'yy-mm-dd',
    'ymd_dot' => 'yy.mm.dd'
);

wp_enqueue_script( 'jquery-ui-datepicker' );
wp_enqueue_style( 'jquery-ui' );

$attributes = array();

$type = 'text';

$date_type = 'date';

if ( 1 == filters_var( 'date_html5', $options ) )
    $type = $date_type;

$attributes[ 'type' ] = $type;
$attributes[ 'tabindex' ] = 2;

$format = FiltersForm::field_method( 'date', 'format', $options );

$method = 'datepicker';

$args = array(
    'dateFormat' => $date_format[ filters_var( 'date_format', $options, 'mdy', null, true ) ]
);

$html5_format = 'Y-m-d';

$date = FiltersForm::field_method( 'date', 'createFromFormat', $format, (string) $value );
$date_default = FiltersForm::field_method( 'date', 'createFromFormat', 'Y-m-d', (string) $value );

if ( 'text' != $type && ( 0 == filters_var( 'date_allow_empty', $options, 1 ) || !in_array( $value, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) ) ) {
    $formatted_date = $value;

    if ( false !== $date )
        $value = $date->format( $html5_format );
    elseif ( false !== $date_default )
        $value = $date_default->format( $html5_format );
    elseif ( !empty( $value ) )
        $value = date_i18n( $html5_format, strtotime( (string) $value ) );
    else
        $value = date_i18n( $html5_format );
}

$args = apply_filters( 'filters_form_ui_field_date_args', $args, $type, $options, $attributes, $name, FiltersForm::$field_type );

$attributes[ 'value' ] = $value;

$attributes = FiltersForm::merge_attributes( $attributes, $name, FiltersForm::$field_type, $options );
?>
<input<?php FiltersForm::attributes( $attributes, $name, FiltersForm::$field_type, $options ); ?> />
<script>
    jQuery( function () {
        var <?php echo filters_clean_name( $attributes[ 'id' ] ); ?>_args = <?php echo json_encode( $args ); ?>;

    <?php
    if ( 'text' != $type ) {
        ?>

        if ( 'undefined' == typeof filters_test_date_field_<?php echo $type; ?> ) {
            // Test whether or not the browser supports date inputs
            function filters_test_date_field_<?php echo $type; ?> () {
                var input = jQuery( '<input/>', {
                    'type' : '<?php echo $type; ?>',
                    css : {
                        position : 'absolute',
                        display : 'none'
                    }
                } );

                jQuery( 'body' ).append( input );

                var bool = input.prop( 'type' ) !== 'text';

                if ( bool ) {
                    var smile = ":)";
                    input.val( smile );

                    return (input.val() != smile);
                }
            }
        }

        if ( !filters_test_date_field_<?php echo $type; ?>() ) {
            jQuery( 'input#<?php echo $attributes[ 'id' ]; ?>' ).val( '<?php echo $formatted_date; ?>' );
            jQuery( 'input#<?php echo $attributes[ 'id' ]; ?>' ).<?php echo $method; ?>( <?php echo filters_clean_name( $attributes[ 'id' ] ); ?>_args );
        }

        <?php
    }
    else {
        ?>

        jQuery( 'input#<?php echo $attributes[ 'id' ]; ?>' ).<?php echo $method; ?>( <?php echo filters_clean_name( $attributes[ 'id' ] ); ?>_args );

        <?php
    }
    ?>
    } );
</script>
