<label<?php FiltersForm::attributes( $attributes, $name, 'label' ); ?>>
    <?php
    echo $label;

    if ( 1 == filters_var( 'required', $options, 0 ) )
        echo '<abbr title="required" class="required">*</abbr>';

    if ( 0 == filters_var( 'grouped', $options, 0, null, true ) && !empty( $help ) && 'help' != $help )
        filters_help( $help );
    ?>
</label>
