<?php
if ( is_admin() && false !== strpos( $_SERVER[ 'REQUEST_URI' ], '/wp-admin/edit.php' ) && isset( $_GET[ 'post_type' ] ) && 'event' == $_GET[ 'post_type' ] ) {
    add_action( 'load-edit.php', 'filters_ui', 100 );
    add_action( 'request', 'filters_ui_restrict' );
}

function filters_ui () {
    if ( !empty( $_REQUEST[ 'action' ] ) )
        do_action( 'admin_action_' . $_REQUEST[ 'action' ] );

    global $post_type_object, $typenow;

    global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version,
           $current_site, $update_title, $total_update_count, $parent_file;

    global $menu;

    $pagenow = 'edit.php';

    // @todo Check if post type is covered
    /*if ( 'post_type_name' != $typenow )
        return;*/

    include FILTERS_DIR . 'form/PodsForm.php';
    include FILTERS_DIR . 'form/PodsField.php';

    remove_action( 'load-edit.php', 'filters_ui', 100 );

    wp_register_style( 'filters-posts-list-table', FILTERS_URL . 'ui/wp/filters-posts-list-table.css' );

    add_action( 'admin_init', 'filters_ui_init' );
    add_action( 'admin_print_styles-edit.php', 'filters_ui_styles' );

    if ( !class_exists( 'WP_List_Table' ) )
        require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

    if ( !class_exists( 'WP_Posts_List_Table' ) )
        require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );

    $version_dir = '3.4.x';

    if ( version_compare( '3.5-beta', $wp_version, '<=' ))
        $version_dir = '3.5';

    require_once FILTERS_DIR . 'ui/wp/' . $version_dir . '/table.php';
    require_once FILTERS_DIR . 'ui/wp/' . $version_dir . '/edit.php';

    die();
}

function filters_ui_restrict ( $request ) {
    if ( 'event' == $request[ 'post_type' ] ) {
        $taxonomies = get_taxonomies( array(), 'objects' );

        if ( !isset( $request[ 'tax_query' ] ) )
            $request[ 'tax_query' ] = array();

        foreach ( $taxonomies as $taxonomy ) {
            if ( is_object_in_taxonomy( $request[ 'post_type' ], $taxonomy->name ) ) {
                $selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

                if ( empty( $selected_tax ) ) {
                    if ( isset( $_GET[ 'filter_' . $taxonomy->name ] ) )
                        unset( $_GET[ 'filter_' . $taxonomy->name ] );

                    continue;
                }

                if ( function_exists( 'icl_object_id' ) )
                    $selected_tax = icl_object_id( $selected_tax, $taxonomy->name, true );

                $request[ 'tax_query' ][] = array(
                    'taxonomy' => $taxonomy->name,
                    'field' => 'id',
                    'terms' => $selected_tax
                );
            }
        }

        if ( empty( $request[ 'tax_query' ] ) )
            unset( $request[ 'tax_query' ] );

        if ( !isset( $request[ 'meta_query' ] ) )
            $request[ 'meta_query' ] = array();

        // @todo Get the filters and fields
        $filters = array();
        $fields = array();

        foreach ( $filters as $filter ) {
            if ( in_array( $fields[ $filter ][ 'type' ], array( 'date', 'datetime', 'time' ) ) ) {
                $start = filters_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true );
                $end = filters_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true );

                if ( empty( $start ) && empty( $end ) ) {
                    if ( isset( $_GET[ 'filter_' . $filter . '_start' ] ) )
                        unset( $_GET[ 'filter_' . $filter . '_start' ] );

                    if ( isset( $_GET[ 'filter_' . $filter . '_end' ] ) )
                        unset( $_GET[ 'filter_' . $filter . '_end' ] );

                    continue;
                }

                $value = array();

                if ( !empty( $start ) && !in_array( $start, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) ) {
                    $start = FiltersForm::field_method( $fields[ $filter ][ 'type' ], 'convert_date', $start, 'Y-m-d h:m:s' );

                    $value[] = $start;
                }

                if ( !empty( $end ) && !in_array( $end, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) ) {
                    $end = FiltersForm::field_method( $fields[ $filter ][ 'type' ], 'convert_date', $end, 'Y-m-d h:m:s' );

                    $value[] = $end;
                }

                $request[ 'meta_query' ][] = array(
                    'key' => $filter,
                    'value' => $value,
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME'
                );
            }
            else {
                $value = filters_var_raw( 'filter_' . $filter, 'get', '', null, true );

                if ( empty( $value ) ) {
                    if ( isset( $_GET[ 'filter_' . $filter ] ) )
                        unset( $_GET[ 'filter_' . $filter ] );

                    continue;
                }

                $request[ 'meta_query' ][] = array(
                    'key' => $filter,
                    'value' => $value
                );
            }
        }

        if ( empty( $request[ 'meta_query' ] ) )
            unset( $request[ 'meta_query' ] );
    }

    return $request;
}

global $post_type_object, $typenow;

global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version,
       $current_site, $update_title, $total_update_count, $parent_file;

global $menu;

function filters_ui_styles () {
    wp_enqueue_style( 'filters-posts-list-table' );
}

function filters_ui_sort_fix ( $column ) {
    if ( !is_array( $column ) )
        return $column;

    $column_key = current( array_keys( $column ) );

    if ( false !== strpos( $column_key, 'column-taxonomy-' ) )
        return false;

    return $column;
}
add_filter( 'cpac-get-orderby-type', 'filters_ui_sort_fix' );