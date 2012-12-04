<?php
$options[ 'data' ] = (array) filters_var_raw( 'data', $options, array(), null, true );

$data_count = count( $options[ 'data' ] );

if ( 0 < $data_count ) {

    if ( 1 == filters_var( 'grouped', $options, 0, null, true ) ) {
        ?>
<div class="filters-pick-values filters-pick-checkbox">
    <ul>
<?php
    }

    $counter = 1;
    $primary_name = $name;
    $primary_id = 'filters-form-ui-' . FiltersForm::clean( $name );

    foreach ( $options[ 'data' ] as $val => $label ) {
        if ( is_array( $label ) ) {
            if ( isset( $label[ 'label' ] ) )
                $label = $label[ 'label' ];
            else
                $label = $val;
        }

        $attributes = array();

        $attributes[ 'type' ] = 'checkbox';
        $attributes[ 'tabindex' ] = 2;

        if ( $val == $value || ( is_array( $value ) && in_array( $val, $value ) ) )
            $attributes[ 'checked' ] = 'CHECKED';

        $attributes[ 'value' ] = $val;

        if ( 1 < $data_count && false === strpos( $primary_name, '[]' ) )
            $name = $primary_name . '[' . ( $counter - 1 ) . ']';

        $attributes = FiltersForm::merge_attributes( $attributes, $name, FiltersForm::$field_type, $options );

        if ( strlen( $label ) < 1 )
            $attributes[ 'class' ] .= ' filters-form-ui-no-label';

        if ( 1 < $data_count )
            $attributes[ 'id' ] = $primary_id . $counter;

        if ( 1 == filters_var( 'grouped', $options, 0, null, true ) ) {
            ?>
        <li>
<?php
        }
        ?>
        <div class="filters-field filters-boolean">
            <input<?php FiltersForm::attributes( $attributes, $name, FiltersForm::$field_type, $options ); ?> />
            <?php
            if ( 0 < strlen( $label ) ) {
                $help = filters_var_raw( 'help', $options );

                if ( 1 == filters_var( 'grouped', $options, 0, null, true ) || empty( $help ) )
                    $help = '';

                echo FiltersForm::label( $attributes[ 'id' ], $label, $help );
            }
            ?>
        </div>
        <?php

        if ( 1 == filters_var( 'grouped', $options, 0, null, true ) ) {
            ?>
        </li>
<?php
        }

        $counter++;
    }

    if ( 1 == filters_var( 'grouped', $options, 0, null, true ) ) {
        ?>
    </ul>
</div>
<?php
    }
}
