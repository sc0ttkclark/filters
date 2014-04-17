<?php
global $post_type_object, $typenow;

global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version, $current_site, $update_title, $total_update_count, $parent_file;

global $menu;

if ( is_admin() && false !== strpos( $_SERVER[ 'REQUEST_URI' ], '/wp-admin/edit.php' ) ) {
	$blacklist_post_types = apply_filters( 'filters_post_type_blacklist', array() );

	if ( in_array( $typenow, $blacklist_post_types ) ) {
		return;
	}

	add_action( 'load-edit.php', 'filters_ui', 100 );
	add_action( 'request', 'filters_ui_restrict' );
}

function filters_ui() {

	if ( !empty( $_REQUEST[ 'action' ] ) ) {
		do_action( 'admin_action_' . $_REQUEST[ 'action' ] );
	}

	global $post_type_object, $typenow;

	global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version, $current_site, $update_title, $total_update_count, $parent_file;

	global $menu;

	$pagenow = 'edit.php';

	include FILTERS_DIR . 'form/FiltersForm.php';
	include FILTERS_DIR . 'form/FiltersField.php';

	remove_action( 'load-edit.php', 'filters_ui', 100 );

	wp_register_style( 'filters-posts-list-table', FILTERS_URL . 'ui/wp/filters-posts-list-table.css' );

	add_action( 'admin_init', 'filters_ui_init' );
	add_action( 'admin_print_styles-edit.php', 'filters_ui_styles' );
	add_action( 'admin_print_scripts-edit.php', 'filters_ui_scripts' );

	if ( !class_exists( 'WP_List_Table' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	}

	if ( !class_exists( 'WP_Posts_List_Table' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );
	}

	$version_dir = '3.9.x'; // no major changes in 3.8 vs 3.9, just use 3.9

	/*if ( version_compare( '3.8', $wp_version, '<=' ) ) {
		$version_dir = '3.9.x';
	}*/

	require_once FILTERS_DIR . 'ui/wp/table.php';
	require_once FILTERS_DIR . 'ui/wp/' . $version_dir . '/edit.php';

	die();
}

function filters_ui_fields_filters( $post_type, $where, $request = null ) {

	$filters = array();
	$fields = array();

	$pod = null;

	// Pods integration
	if ( function_exists( 'pods_api' ) ) {
		$api = pods_api();

		$pod = $api->load_pod( array( 'name' => $post_type, 'fields' => true ), false );

		if ( !empty( $pod ) && 'post_type' == $pod[ 'type' ] ) {
			$fields = $pod[ 'fields' ];

			foreach ( $fields as $field => $field_data ) {
				$fields[ $field ][ 'from' ] = 'pods';
			}

			$filters = apply_filters( 'filters_pods_filters', null, $pod, $fields );

			if ( null === $filters || false === $filters ) {
				$filters = array_keys( $fields );
			} // allow filters for all fields
			elseif ( empty( $filters ) ) {
				$filters = array();
			}
		}
		else {
			$pod = null;
		}
	}

	// CFS integration
	/**
	 * @var Custom_Field_Suite
	 */
	global $cfs;

	if ( is_object( $cfs ) ) {
		$rules = array(
			'post_types' => $post_type
		);

		$groups = $cfs->api->get_matching_groups( $rules );

		$cfs_fields = array();
		$cfs_filters = array();

		if ( !empty( $groups ) ) {
			$groups = array_keys( $groups );

			$cfs_inputs = $cfs->api->find_input_fields( array( 'post_id' => $groups ) );

			// needs Pods-style field array
			if ( !empty( $cfs_inputs ) ) {
				foreach ( $cfs_inputs as $cfs_input ) {
					$cfs_fields[ $cfs_input[ 'name' ] ] = array(
						'name' => $cfs_input[ 'name' ],
						'label' => $cfs_input[ 'label' ],
						'type' => 'text',
						'from' => 'cfs'
					);
				}
			}

			$cfs_filters = apply_filters( 'filters_cfs_filters', null, $groups, $cfs_inputs );

			if ( null === $cfs_filters || false === $cfs_filters ) {
				$cfs_filters = array_keys( $cfs_fields );
			} // allow filters for all fields
			elseif ( empty( $filters ) ) {
				$cfs_filters = array();
			}
		}

		$fields = array_merge( $fields, $cfs_fields );
		$filters = array_merge( $filters, $cfs_filters );
	}

	$filters = apply_filters( 'filters_ui_filters', apply_filters( 'filters_ui_filters_' . $post_type, apply_filters( 'filters_ui_' . $where . '_filters_' . $post_type, $filters, $fields, $where, $request ), $fields, $where, $request ), $fields, $where, $request );

	$fields = apply_filters( 'filters_ui_fields', apply_filters( 'filters_ui_fields_' . $post_type, apply_filters( 'filters_ui_' . $where . '_fields_' . $post_type, $fields, $filters, $where, $request ), $filters, $where, $request ), $filters, $where, $request );

	return array( 'filters' => $filters, 'fields' => $fields, 'pod' => $pod );
}

function filters_ui_restrict( $request ) {

	$blacklist_post_types = apply_filters( 'filters_post_type_blacklist', array() );

	if ( in_array( $request[ 'post_type' ], $blacklist_post_types ) ) {
		return $request;
	}

	Filters_Plugin::$active_filters = array();

	$taxonomies = get_taxonomies( array(), 'objects' );

	$relation = 'AND';

	if ( 'any' == filters_var_raw( 'filters_relation', 'get', '', null, true ) ) {
		$relation = 'OR';
	}

	if ( !isset( $request[ 'tax_query' ] ) ) {
		$request[ 'tax_query' ] = array();
	}

	foreach ( $taxonomies as $taxonomy ) {
		if ( is_object_in_taxonomy( $request[ 'post_type' ], $taxonomy->name ) ) {
			$compare = strtoupper( filters_var_raw( 'filters_compare_' . $taxonomy->name, 'get', 'AND', null, true ) );

			// Restrict to supported comparisons
			if ( !in_array( $compare, array( 'AND', 'IN', 'NOT IN' ) ) ) {
				$compare = 'AND';
			}

			$value = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '', null, true );

			if ( empty( $value ) ) {
				if ( isset( $_GET[ 'filter_' . $taxonomy->name ] ) ) {
					unset( $_GET[ 'filter_' . $taxonomy->name ] );
				}

				continue;
			}

			$tax_query_array = array(
				'taxonomy' => $taxonomy->name,
				'field' => 'id',
				'terms' => (array) $value,
				'operator' => $compare
			);

			Filters_Plugin::$active_filters[ $taxonomy->name ] = $tax_query_array;

			$request[ 'tax_query' ][ ] = $tax_query_array;
		}
	}

	if ( empty( $request[ 'tax_query' ] ) ) {
		unset( $request[ 'tax_query' ] );
	}
	else {
		$request[ 'tax_query' ][ 'relation' ] = $relation;
	}

	if ( !isset( $request[ 'meta_query' ] ) ) {
		$request[ 'meta_query' ] = array();
	}

	$fields_filters = filters_ui_fields_filters( $request[ 'post_type' ], 'request', $request );

	$filters = $fields_filters[ 'filters' ];
	$fields = $fields_filters[ 'fields' ];

	foreach ( $filters as $filter ) {
		if ( !apply_filters( 'filters_compare_allow_empty', false, $filter, $fields[ $filter ] ) ) {
			if ( in_array( $fields[ $filter ][ 'type' ], array( 'date', 'datetime', 'time' ) ) ) {
				if ( '' == filters_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true ) && '' == filters_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true ) ) {
					continue;
				}
			}
			elseif ( '' === filters_var_raw( 'filter_' . $filter, 'get', '' ) ) {
				continue;
			}
		}

		$default_search = true;

		$compare = strtoupper( filters_var_raw( 'filters_compare_' . $filter, 'get', 'LIKE', null, true ) );

		// Restrict to supported comparisons
		if ( !in_array( $compare, array(
			'=',
			'!=',
			'>',
			'>=',
			'<',
			'<=',
			'LIKE',
			'NOT LIKE',
			'IN',
			'NOT IN',
			'BETWEEN',
			'NOT BETWEEN',
			'EXISTS',
			'NOT EXISTS'
		) )
		) {
			$compare = 'LIKE';
		}
		else {
			$default_search = false;
		}

		if ( 'pick' == $fields[ $filter ][ 'type' ] ) {
			$compare = '=';
		}

		$value = filters_var_raw( 'filter_' . $filter, 'get', '', null, true );

		// Restrict to supported array comparisons
		if ( is_array( $value ) && !in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			if ( in_array( $compare, array( '!=', 'NOT LIKE' ) ) ) {
				$compare = 'NOT IN';
			}
			else {
				$compare = 'IN';
			}
		}
		// Restrict to supported string comparisons
		elseif ( !is_array( $value ) && in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			$check_value = preg_split( '/[,\s]+/', $value );

			if ( 1 < count( $check_value ) ) {
				$value = $check_value;
			}
			elseif ( in_array( $compare, array( 'NOT IN', 'NOT BETWEEN' ) ) ) {
				$compare = '!=';
			}
			else {
				$compare = '=';
			}
		}

		$type = filters_var_raw( 'type', $fields[ $filter ], 'text', null, true );

		if ( in_array( $type, array( 'avatar', 'file', 'pick' ) ) ) {
			$type = 'CHAR';
		}
		elseif ( 'boolean' == $type ) {
			$type = 'NUMERIC';
		}
		elseif ( in_array( $type, array(
			'code',
			'color',
			'email',
			'paragraph',
			'password',
			'phone',
			'slug',
			'text',
			'website',
			'wysiwyg'
		) )
		) {
			$type = 'CHAR';
		}
		elseif ( in_array( $type, array( 'currency', 'number' ) ) ) {
			$decimals = filters_var_raw( $type . '_', $fields[ $filter ], 0, null, true );

			$type = 'NUMERIC';

			if ( 0 < $decimals ) {
				$type = 'DECIMAL';
			}
		}
		elseif ( in_array( $type, array( 'date', 'datetime', 'time' ) ) ) {
			$type = strtoupper( $type );
		}
		else {
			$type = 'CHAR';
		}

		if ( in_array( strtolower( $type ), array( 'date', 'datetime', 'time' ) ) ) {
			$start = filters_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true );
			$end = filters_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true );

			if ( empty( $start ) && empty( $end ) && in_array( $compare, array( '=', 'BETWEEN' ) ) ) {
				if ( isset( $_GET[ 'filter_' . $filter . '_start' ] ) ) {
					unset( $_GET[ 'filter_' . $filter . '_start' ] );
				}

				if ( isset( $_GET[ 'filter_' . $filter . '_end' ] ) ) {
					unset( $_GET[ 'filter_' . $filter . '_end' ] );
				}

				continue;
			}

			$value = array();

			$has_start = $has_end = false;

			if ( !empty( $start ) && !in_array( $start, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) ) {
				$start = FiltersForm::field_method( strtolower( $type ), 'convert_date', $start, 'Y-m-d h:m:s' );

				$value[ ] = $start;

				$has_start = true;
			}

			if ( !empty( $end ) && !in_array( $end, array( '0000-00-00', '0000-00-00 00:00:00', '00:00:00' ) ) ) {
				$end = FiltersForm::field_method( strtolower( $type ), 'convert_date', $end, 'Y-m-d h:m:s' );

				$value[ ] = $end;

				$has_end = true;
			}

			if ( $has_start && !$has_end ) {
				$compare = '<=';
			}
			elseif ( $has_end && !$has_start ) {
				$compare = '>=';
			}
			elseif ( $has_start && $has_end && 'NOT BETWEEN' != $compare ) {
				$compare = 'BETWEEN';
			}

			$meta_query_array = array(
				'key' => $filter,
				'value' => $value,
				'compare' => $compare,
				'type' => 'DATETIME'
			);

			Filters_Plugin::$active_filters[ $filter ] = $meta_query_array;

			$request[ 'meta_query' ][ ] = $meta_query_array;
		}
		else {
			if ( $default_search && empty( $value ) ) {
				if ( isset( $_GET[ 'filter_' . $filter ] ) ) {
					unset( $_GET[ 'filter_' . $filter ] );
				}

				continue;
			}

			$meta_query_array = array(
				'key' => $filter,
				'value' => $value,
				'compare' => $compare,
				'type' => $type
			);

			Filters_Plugin::$active_filters[ $filter ] = $meta_query_array;

			$request[ 'meta_query' ][ ] = $meta_query_array;
		}
	}

	if ( empty( $request[ 'meta_query' ] ) ) {
		unset( $request[ 'meta_query' ] );
	}
	else {
		$request[ 'meta_query' ][ 'relation' ] = $relation;

		if ( isset( $_GET[ 'filters_debug' ] ) && is_super_admin() ) {
			var_dump( $request[ 'meta_query' ] );
		}
	}

	return $request;
}

function filters_ui_styles() {

	wp_enqueue_style( 'filters-posts-list-table' );

	wp_enqueue_style( 'thickbox' );
}

function filters_ui_scripts() {

	wp_enqueue_script( 'thickbox' );
}

function filters_ui_sort_fix( $column ) {

	if ( !is_array( $column ) ) {
		return $column;
	}

	$column_key = current( array_keys( $column ) );

	if ( false !== strpos( $column_key, 'column-taxonomy-' ) ) {
		return false;
	}

	return $column;
}

add_filter( 'cpac-get-orderby-type', 'filters_ui_sort_fix' );