<?php
/**
 * Filters List Table class
 *
 * @package Filters
 * @since 0.1
 * @access private
 */
class Filters_Posts_List_Table extends WP_Posts_List_Table {
    var $post_type = null;
    var $post_type_object = null;

	function __construct( $_post_type = null, $post_type_object = null ) {
		global $wpdb, $post_type;

        $post_type = $_post_type;

        if ( empty( $post_type ) )
		    $post_type = get_current_screen()->post_type;

        if ( empty( $post_type_object ) )
		    $post_type_object = get_post_type_object( $post_type );

        $this->post_type = $post_type;
        $this->post_type_object = $post_type_object;

		if ( !current_user_can( $this->post_type_object->cap->edit_others_posts ) ) {
			$this->user_posts_count = $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT( 1 ) FROM $wpdb->posts
				WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' )
				AND post_author = %d
			", $post_type, get_current_user_id() ) );

			if ( $this->user_posts_count && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['all_posts'] ) && empty( $_REQUEST['author'] ) && empty( $_REQUEST['show_sticky'] ) )
				$_GET['author'] = get_current_user_id();
		}

		if ( 'post' == $post_type && $sticky_posts = get_option( 'sticky_posts' ) ) {
			$sticky_posts = implode( ', ', array_map( 'absint', (array) $sticky_posts ) );
			$this->sticky_posts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( 1 ) FROM $wpdb->posts WHERE post_type = %s AND post_status != 'trash' AND ID IN ($sticky_posts)", $post_type ) );
		}

        $construct = array(
            'singular' => 'post',
            'plural' => 'posts',
            'ajax' => true
        );

        parent::__construct( $construct );
	}

    function get_views() {
        global $post_type_object, $locked_post_status, $avail_post_stati;

        $post_type = $post_type_object->name;

        if ( !empty( $locked_post_status ) )
            return array();

        $num_posts = wp_count_posts( $post_type, 'readable' );
        $class = '';
        $allposts = '';

        $current_user_id = get_current_user_id();

        if ( $this->user_posts_count ) {
            if ( isset( $_GET[ 'author' ] ) && ( $_GET[ 'author' ] == $current_user_id ) )
                $class = ' class="current"';
            $url = filters_var_update( array( 'post_type' => $post_type, 'author' => $current_user_id, 'paged' => '', 'action' => '', 'action2' => '' ), null, array( 'paged', 'action', 'action2' ) );
            $status_links[ 'mine' ] = "<a href='" . $url . "'$class>" . sprintf( _nx( 'Mine <span class="count">(%s)</span>', 'Mine <span class="count">(%s)</span>', $this->user_posts_count, 'posts' ), number_format_i18n( $this->user_posts_count ) ) . '</a>';
            $allposts = '&all_posts=1';
        }

        $total_posts = array_sum( (array) $num_posts );

        // Subtract post types that are not included in the admin all list.
        foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state )
            $total_posts -= $num_posts->$state;

        $class = empty( $class ) && ( 'all' == $_REQUEST[ 'post_status' ] || empty( $_REQUEST[ 'post_status' ] ) ) && empty( $_REQUEST[ 'show_sticky' ] ) ? ' class="current"' : '';
        $url = filters_var_update( array( 'post_type' => $post_type, 'post_status' => '', 'all_posts' => ( empty( $allposts ) ? '' : 1 ), 'paged' => '', 'action' => '', 'action2' => '' ), null, array( 'paged', 'action', 'action2' ) );
        $status_links[ 'all' ] = "<a href='{$url}'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

        foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
            $class = '';

            $status_name = $status->name;

            if ( !in_array( $status_name, $avail_post_stati ) )
                continue;

            if ( empty( $num_posts->$status_name ) )
                continue;

            if ( isset( $_REQUEST[ 'post_status' ] ) && $status_name == $_REQUEST[ 'post_status' ] )
                $class = ' class="current"';

            $url = filters_var_update( array( 'post_type' => $post_type, 'post_status' => $status_name, 'paged' => '', 'action' => '', 'action2' => '' ), null, array( 'paged', 'action', 'action2' ) );
            $status_links[ $status_name ] = "<a href='{$url}'$class>" . sprintf( translate_nooped_plural( $status->label_count, $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
        }

        if ( !empty( $this->sticky_posts_count ) ) {
            $class = !empty( $_REQUEST[ 'show_sticky' ] ) ? ' class="current"' : '';

            $url = filters_var_update( array( 'post_type' => $post_type, 'show_sticky' => 1, 'paged' => '', 'action' => '', 'action2' => '' ), null, array( 'paged', 'action', 'action2' ) );
            $sticky_link = array( 'sticky' => "<a href='{$url}'$class>" . sprintf( _nx( 'Sticky <span class="count">(%s)</span>', 'Sticky <span class="count">(%s)</span>', $this->sticky_posts_count, 'posts' ), number_format_i18n( $this->sticky_posts_count ) ) . '</a>' );

            // Sticky comes after Publish, or if not listed, after All.
            $split = 1 + array_search( ( isset( $status_links[ 'publish' ] ) ? 'publish' : 'all' ), array_keys( $status_links ) );
            $status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
        }

        return $status_links;
    }

    /**
     * Display the list of views available on this table.
     *
     * @since 3.1.0
     * @access public
     */
    function views () {
        // uses search_box() now
    }

    function extra_tablenav ( $which ) {
        global $post_type_object, $cat;
?>
    <div class="alignleft actions">
        <?php
            if ( $this->is_trash && current_user_can( $post_type_object->cap->edit_others_posts ) ) {
                submit_button( __( 'Empty Trash' ), 'button-secondary apply', 'delete_all', false );
            }
        ?>
    </div>
<?php
    }

	/**
	 * Display the search box.
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param string $text The search button text
	 * @param string $input_id The search input id
	 */
	function search_box( $text, $input_id ) {
		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';

        // @todo Get the filters and fields
        $filters = array();
        $fields = array();

        foreach ( $filters as $k => $filter ) {
            if ( in_array( $fields[ $filter ][ 'type' ], array( 'date', 'datetime', 'time' ) ) ) {
                if ( '' == filters_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true ) && '' == filters_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true ) )
                    unset( $filters[ $k ] );
            }
            elseif ( '' == filters_var_raw( 'filter_' . $filter, 'get', '', null, true ) )
                unset( $filters[ $k ] );
        }

        $filtered = false;

        $taxonomies = get_taxonomies( array(), 'objects' );

        foreach ( $taxonomies as $taxonomy ) {
            if ( is_object_in_taxonomy( $this->post_type_object->name, $taxonomy->name ) ) {
                $selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

                if ( !empty( $selected_tax ) ) {
                    $filtered = true;

                    break;
                }
            }
        }

        if ( !empty( $filters ) )
            $filtered = true;
?>
    <div class="filters-ui-filter-bar">
        <div class="filters-ui-filter-bar-primary">
            <ul class="subsubsub">
                <li class="status-label"><strong>Status</strong></li>

                <?php
                    $screen = get_current_screen();

                    $views = $this->get_views();
                    $views = (array) apply_filters( 'views_' . $screen->id, $views );

                    foreach ( $views as $class => $view ) {
                ?>
                    <li class="<?php echo $class; ?>"><?php echo $view; ?></li>
                <?php
                    }
                ?>
            </ul>

            <p class="search-box">
                <?php
                    if ( $filtered || '' != filters_var_raw( 's', 'get', '', null, true ) ) {
                ?>
                    <a href="<?php echo filters_var_update( array( 'post_status' => '', 'paged' => '', 'action' => '', 'action2' => '' ), array( 'post_type' ) ); ?>" class="filters-ui-filter-reset">[<?php _e( 'Reset', 'filters' ); ?>]</a>
                <?php
                    }
                ?>

                <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>

                <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />

                <?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
            </p>
        </div>

        <div class="filters-ui-filter-bar-secondary">
            <ul class="subsubsub">
                <?php
                    if ( !$filtered ) {
                ?>
                    <li class="filters-ui-filter-bar-add-filter">
                        <a href="#TB_inline?width=640&inlineId=filters-ui-posts-filter-popup" class="thickbox" title="<?php esc_attr_e( 'Advanced Filters', 'filters' ); ?>"><?php _e( 'Advanced Filters', 'filters' ); ?></a>
                    </li>
                <?php
                    }
                    else {
                ?>
                    <li class="filters-ui-filter-bar-add-filter">
                        <a href="#TB_inline?width=640&inlineId=filters-ui-posts-filter-popup" class="thickbox" title="<?php esc_attr_e( 'Advanced Filters', 'filters' ); ?>">+ <?php _e( 'Add Filter', 'filters' ); ?></a>
                    </li>
                <?php } ?>

                <?php
                    foreach ( $taxonomies as $taxonomy ) {
                        if ( is_object_in_taxonomy( $this->post_type_object->name, $taxonomy->name ) ) {
                            $selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

                            if ( empty( $selected_tax ) )
                                continue;

                            if ( function_exists( 'icl_object_id' ) )
                                $selected_tax = icl_object_id( $selected_tax, $taxonomy->name, true );

                            $term = get_term( $selected_tax, $taxonomy->name );

                            if ( empty( $term ) || is_wp_error( $term ) )
                                continue;
                ?>
                    <li class="filters-ui-filter-bar-filter" data-filter="<?php echo $taxonomy->name; ?>">
                        <a href="#TB_inline?width=640&inlineId=filters-ui-posts-filter-popup" class="thickbox" title="<?php esc_attr_e( 'Advanced Filters', 'filters' ); ?>">
                            <strong><?php echo $taxonomy->labels->singular_name; ?>:</strong>
                            <?php echo esc_html( $term->name ); ?>
                        </a>

                        <a href="#remove-filter" class="remove-filter" title="<?php esc_attr_e( 'Remove Filter', 'filters' ); ?>">x</a>

                        <?php echo FiltersForm::field( 'filter_' . $taxonomy->name, $selected_tax, 'hidden' ); ?>
                    </li>
                <?php
                        }
                    }

                    foreach ( $filters as $filter ) {
                        $value = filters_var_raw( 'filter_' . $filter, 'get', '', null, true );
                        $data_filter = 'filter_' . $filter;

                        $start = $end = '';

                        if ( in_array( $fields[ $filter ][ 'type' ], array( 'date', 'datetime', 'time' ) ) ) {
                            $start = filters_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true );
                            $end = filters_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true );

                            if ( !empty( $start ) && !in_array( $start, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) )
                                $start = FiltersForm::field_method( $fields[ $filter ][ 'type' ], 'convert_date', $start, 'n/j/Y' );

                            if ( !empty( $end ) && !in_array( $end, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) )
                                $end = FiltersForm::field_method( $fields[ $filter ][ 'type' ], 'convert_date', $end, 'n/j/Y' );

                            $value = trim( $start . ' - ' . $end, ' -' );

                            $data_filter = 'filter_' . $filter . '_start';
                        }
                ?>
                    <li class="filters-ui-filter-bar-filter" data-filter="<?php echo $data_filter; ?>">
                        <a href="#TB_inline?width=640&inlineId=filters-ui-posts-filter-popup" class="thickbox" title="<?php esc_attr_e( 'Advanced Filters', 'filters' ); ?>">
                            <strong><?php echo $fields[ $filter ][ 'label' ]; ?>:</strong>
                            <?php echo esc_html( $value ); ?>
                        </a>

                        <a href="#remove-filter" class="remove-filter" title="<?php esc_attr_e( 'Remove Filter', 'filters' ); ?>">x</a>

                        <?php
                            if ( in_array( $fields[ $filter ][ 'type' ], array( 'date', 'datetime', 'time' ) ) ) {
                                echo FiltersForm::field( 'filter_' . $filter . '_start', $start, 'hidden' );
                                echo FiltersForm::field( 'filter_' . $filter . '_end', $end, 'hidden' );
                            }
                            else
                                echo FiltersForm::field( $data_filter, $value, 'hidden' );
                        ?>
                    </li>
                <?php
                    }
                ?>
            </ul>
        </div>
    </div>

    <script type="text/javascript">
        jQuery( function() {
            jQuery( '.filters-ui-filter-bar-secondary' ).on( 'click', '.remove-filter', function ( e ) {
                jQuery( '.filters-ui-filter-popup #' + jQuery( this ).parent().data( 'filter' ) ).remove();

                jQuery( this ).parent().find( 'input' ).each( function () {
                    jQuery( this ).remove();
                } );

                jQuery( 'form#posts-filter [name="paged"]' ).prop( 'disabled', true );
                jQuery( 'form#posts-filter [name="action"]' ).prop( 'disabled', true );
                jQuery( 'form#posts-filter [name="action2"]' ).prop( 'disabled', true );

                jQuery( 'form#posts-filter' ).submit();

                e.preventDefault();
            } );
        } );
    </script>
<?php
	}

    function table_popup () {
        global $pagenow, $typenow, $cat;

        // @todo Check if post type is covered
        if ( 'edit.php' != $pagenow ) // 'post_type_name' != $typenow )
            return;

        // @todo Get the filters and fields
        $filters = array();
        $fields = array();
?>
    <div id="filters-ui-posts-filter-popup" class="hidden">
        <form action="" method="get" class="filters-ui-posts-filter-popup">
            <h2><?php _e( 'Advanced Filters', 'filters' ); ?></h2>

            <div class="filters-ui-posts-filters">
                <?php
                    $excluded_filters = array( 's', 'paged', 'action', 'action2' );

                    foreach ( $filters as $filter ) {
                        $excluded_filters[] = 'filter_' . $filter . '_start';
                        $excluded_filters[] = 'filter_' . $filter . '_end';
                        $excluded_filters[] = 'filter_' . $filter;
                    }

                    $get = $_GET;

                    foreach ( $get as $k => $v ) {
                        if ( in_array( $k, $excluded_filters ) || empty( $v ) )
                            continue;
                ?>
                    <input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>" />
                <?php
                    }

                    if ( 1 == 0 ) {
                ?>
                    <p>
                        <label for="m">Month</label>

                        <?php $this->months_dropdown( $this->post_type_object->name ); ?>
                    </p>
                <?php
                    }

                    $taxonomies = get_taxonomies( array(), 'objects' );

                    $zebra = true;

                    foreach ( $taxonomies as $taxonomy ) {
                        if ( is_object_in_taxonomy( $this->post_type_object->name, $taxonomy->name ) ) {
                            $selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

                            if ( !empty( $selected_tax ) && function_exists( 'icl_object_id' ) )
                                $selected_tax = icl_object_id( $selected_tax, $taxonomy->name, true );

                            $dropdown_options = array(
                                'show_option_all' => __( 'View all' ) . ' ' . $taxonomy->labels->name,
                                'hide_empty' => 1,
                                'hierarchical' => 1,
                                'show_count' => 0,
                                'orderby' => 'name',
                                'name' => 'filter_' . $taxonomy->name,
                                'id' => 'filter_' . $taxonomy->name,
                                'taxonomy' => $taxonomy->name,
                                'selected' => $selected_tax
                            );
                ?>
                    <p class="filters-ui-posts-filter-toggled filters-ui-posts-filter-<?php echo $taxonomy->name . ( $zebra ? ' clear' : '' ); ?>">
                        <span class="filters-ui-posts-filter-toggle toggle-on<?php echo ( empty( $selected_tax ) ? '' : ' hidden' ); ?>">+</span>
                        <span class="filters-ui-posts-filter-toggle toggle-off<?php echo ( empty( $selected_tax ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

                        <label for="taxonomy_<?php echo $taxonomy->name; ?>">
                            <?php echo $taxonomy->labels->singular_name; ?>
                        </label>

                        <span class="filters-ui-posts-filter<?php echo ( empty( $selected_tax ) ? ' hidden' : '' ); ?>">
                            <?php wp_dropdown_categories( $dropdown_options ); ?>
                        </span>
                    </p>
                <?php
                            $zebra = empty( $zebra );
                        }
                    }
                ?>
            </div>

            <div class="filters-ui-posts-filters">
                <?php
                    $zebra = true;

                    foreach ( $filters as $filter ) {
                ?>
                    <p class="filters-ui-posts-filter-toggled filters-ui-posts-filter-<?php echo $filter . ( $zebra ? ' clear' : '' ); ?>">
                        <?php
                            if ( in_array( $fields[ $filter ][ 'type' ], array( 'date', 'datetime', 'time' ) ) ) {
                                $start = filters_var_raw( 'filter_' . $filter . '_start', 'get', filters_var_raw( 'filter_default', $fields[ $filter ], '', null, true ), null, true );
                                $end = filters_var_raw( 'filter_' . $filter . '_end', 'get', filters_var_raw( 'filter_ongoing_default', $fields[ $filter ], '', null, true ), null, true );

                                if ( !empty( $start ) && !in_array( $start, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) )
                                    $start = FiltersForm::field_method( $fields[ $filter ][ 'type' ], 'convert_date', $start, 'n/j/Y' );

                                if ( !empty( $end ) && !in_array( $end, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) )
                                    $end = FiltersForm::field_method( $fields[ $filter ][ 'type' ], 'convert_date', $end, 'n/j/Y' );
                        ?>
                            <span class="filters-ui-posts-filter-toggle toggle-on<?php echo ( ( empty( $start ) && empty( $end ) ) ? '' : ' hidden' ); ?>">+</span>
                            <span class="filters-ui-posts-filter-toggle toggle-off<?php echo ( ( empty( $start ) && empty( $end ) ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

                            <label for="filters-ui-form-ui-filter-<?php echo $filter; ?>_start">
                                <?php echo $fields[ $filter ][ 'label' ]; ?>
                            </label>

                            <span class="filters-ui-posts-filter<?php echo ( ( empty( $start ) && empty( $end ) ) ? ' hidden' : '' ); ?>">
                                <?php echo FiltersForm::field( 'filter_' . $filter. '_start', $start, $fields[ $filter ][ 'type' ], $fields[ $filter ] ); ?>

                                <label for="filters-ui-form-ui-filter-<?php echo $filter; ?>_end">to</label>
                                <?php echo FiltersForm::field( 'filter_' . $filter . '_end', $end, $fields[ $filter ][ 'type' ], $fields[ $filter ] ); ?>
                            </span>
                        <?php
                            }
                            else {
                                $value = filters_var_raw( 'filter_' . $filter, 'get', filters_var( 'filter_default', $fields[ $filter ], '', null, true ), null, true );
                        ?>
                            <span class="filters-ui-posts-filter-toggle toggle-on<?php echo ( empty( $value ) ? '' : ' hidden' ); ?>">+</span>
                            <span class="filters-ui-posts-filter-toggle toggle-off<?php echo ( empty( $value ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

                            <label for="filters-ui-form-ui-filter-<?php echo $filter; ?>">
                                <?php echo $fields[ $filter ][ 'label' ]; ?>
                            </label>

                            <span class="filters-ui-posts-filter<?php echo ( empty( $value ) ? ' hidden' : '' ); ?>">
                                <?php echo FiltersForm::field( 'filter_' . $filter, $value, 'text' ); ?>
                            </span>
                        <?php
                            }
                        ?>
                    </p>
                <?php
                        $zebra = empty( $zebra );
                    }
                ?>

                <p class="filters-ui-posts-filter-toggled filters-ui-posts-filter-s<?php echo ( $zebra ? ' clear' : '' ); ?>">
                    <label for="filters-ui-form-ui-s"><?php _e( 'Search Text', 'filters' ); ?></label>
                    <?php echo FiltersForm::field( 's', filters_var_raw( 's', 'get' ), 'text' ); ?>
                </p>

                <?php $zebra = empty( $zebra ); ?>
            </div>

            <p class="submit<?php echo ( $zebra ? ' clear' : '' ); ?>"><input type="submit" value="<?php esc_attr_e( 'Search', 'filters' ); ?> Events" class="button button-primary" /></p>
        </form>
    </div>

    <script type="text/javascript">
        jQuery( function () {
            jQuery( document ).on( 'click', '.filters-ui-posts-filter-toggle.toggle-on', function ( e ) {
                jQuery( this ).parent().find( '.filters-ui-posts-filter' ).removeClass( 'hidden' );

                jQuery( this ).hide();
                jQuery( this ).parent().find( '.toggle-off' ).show();
            } );

            jQuery( document ).on( 'click', '.filters-ui-posts-filter-toggle.toggle-off', function ( e ) {
                jQuery( this ).parent().find( '.filters-ui-posts-filter' ).addClass( 'hidden' );
                jQuery( this ).parent().find( 'select, input' ).val( '' );

                jQuery( this ).hide();
                jQuery( this ).parent().find( '.toggle-on' ).show();
            } );

            jQuery( document ).on( 'click', '.filters-ui-posts-filter-toggled > label', function ( e ) {
                if ( jQuery( this ).parent().find( '.filters-ui-posts-filter' ).hasClass( 'hidden' ) ) {
                    jQuery( this ).parent().find( '.filters-ui-posts-filter' ).removeClass( 'hidden' );

                    jQuery( this ).parent().find( '.toggle-on' ).hide();
                    jQuery( this ).parent().find( '.toggle-off' ).show();
                }
                else {
                    jQuery( this ).parent().find( '.filters-ui-posts-filter' ).addClass( 'hidden' );
                    jQuery( this ).parent().find( 'select, input' ).val( '' );

                    jQuery( this ).parent().find( '.toggle-on' ).show();
                    jQuery( this ).parent().find( '.toggle-off' ).hide();
                }
            } );
        } );
    </script>
<?php
    }
}