<?php
global $post_type, $post_type_object;

require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );

/**
 * Filters List Table class
 *
 * @package Filters
 * @since 0.1
 * @access private
 */
class Filters_Posts_List_Table extends WP_Posts_List_Table {

	/**
	 * @var string
	 */
	public static $filters_post_type = null;

	/**
	 * @var object
	 */
	public static $filters_post_type_object = null;

	/**
	 * @var array
	 */
	public static $filters_fields = array();

	/**
	 * @var array
	 */
	public static $filters_filters = array();

	/**
	 * @var Pods
	 */
	public static $filters_pod = null;

	/**
	 * @var array
	 */
	public static $filters_taxonomies = array();

	public function __construct( $args = array() ) {

		global $post_type_object, $wpdb;

		parent::__construct( array(
			'plural' => 'posts',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$post_type = $this->screen->post_type;
		$post_type_object = get_post_type_object( $post_type );

		if ( !current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			$exclude_states = get_post_stati( array( 'show_in_admin_all_list' => false ) );
			$this->user_posts_count = $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT( 1 ) FROM $wpdb->posts
				WHERE post_type = %s AND post_status NOT IN ( '" . implode( "','", $exclude_states ) . "' )
				AND post_author = %d
			", $post_type, get_current_user_id() ) );

			if ( $this->user_posts_count && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['all_posts'] ) && empty( $_REQUEST['author'] ) && empty( $_REQUEST['show_sticky'] ) )
				$_GET['author'] = get_current_user_id();
		}

		if ( 'post' == $post_type && $sticky_posts = get_option( 'sticky_posts' ) ) {
			$sticky_posts = implode( ', ', array_map( 'absint', (array) $sticky_posts ) );
			$this->sticky_posts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( 1 ) FROM $wpdb->posts WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft') AND ID IN ($sticky_posts)", $post_type ) );
		}

		self::$filters_post_type = $post_type;
		self::$filters_post_type_object = $post_type_object;

		self::$filters_taxonomies = get_taxonomies( array(), 'objects' );

		foreach ( self::$filters_taxonomies as $k => $taxonomy ) {
			if ( !is_object_in_taxonomy( self::$filters_post_type_object->name, $taxonomy->name ) ) {
				unset( self::$filters_taxonomies[ $k ] );
			}
		}

		$fields_filters = filters_ui_fields_filters( self::$filters_post_type, 'ui' );

		self::$filters_fields = $fields_filters[ 'fields' ];
		self::$filters_filters = $fields_filters[ 'filters' ];

		if ( !empty( $fields_filters[ 'pod' ] ) ) {
			self::$filters_pod = $fields_filters[ 'pod' ];
		}

	}

	public function get_views() {

		global $locked_post_status, $avail_post_stati;

		$post_type = $this->screen->post_type;

		if ( !empty( $locked_post_status ) ) {
			return array();
		}

		$num_posts = wp_count_posts( $post_type, 'readable' );
		$class = '';
		$allposts = '';

		$current_user_id = get_current_user_id();

		if ( $this->user_posts_count ) {
			if ( isset( $_GET[ 'author' ] ) && ( $_GET[ 'author' ] == $current_user_id ) ) {
				$class = ' class="current"';
			}

			$url = filters_var_update( array(
				'post_type' => self::$filters_post_type,
				'author' => $current_user_id,
				'paged' => '',
				'action' => '',
				'action2' => ''
			), null, array(
				'paged',
				'action',
				'action2'
			) );

			$status_links[ 'mine' ] = "<a href='" . $url . "'$class>" . sprintf( _nx( 'Mine <span class="count">(%s)</span>', 'Mine <span class="count">(%s)</span>', $this->user_posts_count, 'posts' ), number_format_i18n( $this->user_posts_count ) ) . '</a>';

			$allposts = '&all_posts=1';
		}

		$total_posts = array_sum( (array) $num_posts );

		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= $num_posts->$state;
		}

		$class = ( empty( $class ) && ( isset( $_REQUEST[ 'post_status' ] ) && 'all' == $_REQUEST[ 'post_status' ] || empty( $_REQUEST[ 'post_status' ] ) ) && empty( $_REQUEST[ 'show_sticky' ] ) ) ? ' class="current"' : '';

		$url = filters_var_update( array(
			'post_type' => self::$filters_post_type,
			'post_status' => '',
			'all_posts' => ( empty( $allposts ) ? '' : 1 ),
			'paged' => '',
			'action' => '',
			'action2' => ''
		), null, array(
			'paged',
			'action',
			'action2'
		) );

		$status_links[ 'all' ] = "<a href='{$url}'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( !in_array( $status_name, $avail_post_stati ) ) {
				continue;
			}

			if ( empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( isset( $_REQUEST[ 'post_status' ] ) && $status_name == $_REQUEST[ 'post_status' ] ) {
				$class = ' class="current"';
			}

			$url = filters_var_update( array(
				'post_type' => self::$filters_post_type,
				'post_status' => $status_name,
				'paged' => '',
				'action' => '',
				'action2' => ''
			), null, array(
				'paged',
				'action',
				'action2'
			) );
			$status_links[ $status_name ] = "<a href='{$url}'$class>" . sprintf( translate_nooped_plural( $status->label_count, $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
		}

		if ( !empty( $this->sticky_posts_count ) ) {
			$class = !empty( $_REQUEST[ 'show_sticky' ] ) ? ' class="current"' : '';

			$url = filters_var_update( array(
				'post_type' => self::$filters_post_type,
				'show_sticky' => 1,
				'paged' => '',
				'action' => '',
				'action2' => ''
			), null, array(
				'paged',
				'action',
				'action2'
			) );
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
	public function views() {

		// uses search_box() now

	}

	public function extra_tablenav( $which ) {

?>
		<div class="alignleft actions">
<?php

		if ( $this->is_trash && current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_others_posts ) ) {
			submit_button( __( 'Empty Trash' ), 'apply', 'delete_all', false );
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
	public function search_box( $text, $input_id ) {

		$screen = get_current_screen();

		$views = $this->get_views();
		$views = (array) apply_filters( 'views_' . $screen->id, $views );
		$views = (array) apply_filters( 'filters_ui_views', $views, self::$filters_post_type, self::$filters_post_type_object );

		$input_id = $input_id . '-search-input';

		if ( !empty( $_REQUEST[ 'orderby' ] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST[ 'orderby' ] ) . '" />';
		}
		if ( !empty( $_REQUEST[ 'order' ] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST[ 'order' ] ) . '" />';
		}

		$filtered = false;

		$filters = self::$filters_filters;

		foreach ( $filters as $k => $filter ) {
			if ( !isset( Filters_Plugin::$active_filters[ $filter ] ) ) {
				unset( $filters[ $k ] );
			}
			else {
				$filtered = true;
			}
		}

		$taxonomies = self::$filters_taxonomies;

		foreach ( $taxonomies as $k => $taxonomy ) {
			if ( !isset( Filters_Plugin::$active_filters[ $taxonomy->name ] ) ) {
				unset( $taxonomies[ $k ] );
			}
			else {
				$filtered = true;
			}
		}
		?>
		<div class="filters-ui-filter-bar">
			<div class="filters-ui-filter-bar-primary">
				<ul class="subsubsub">
					<li class="status-label"><strong>Status</strong></li>

					<?php
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
						<a href="<?php echo filters_var_update( array(
							'post_status' => '',
							'paged' => '',
							'action' => '',
							'action2' => ''
						), array( 'post_type' ) ); ?>" class="filters-ui-filter-reset">[<?php _e( 'Reset', 'filters' ); ?>]</a>
					<?php
					}
					?>

					<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>

					<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />

					<?php submit_button( $text, 'button', false, false, array( 'id' => 'search-submit' ) ); ?>
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
						$selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

						$term = false;

						if ( !empty( $selected_tax ) ) {
							$term = get_term( $selected_tax, $taxonomy->name );

							if ( empty( $term ) || is_wp_error( $term ) ) {
								continue;
							}
						}
						?>
						<li class="filters-ui-filter-bar-filter" data-filter="<?php echo $taxonomy->name; ?>">
							<a href="#TB_inline?width=640&inlineId=filters-ui-posts-filter-popup" class="thickbox" title="<?php esc_attr_e( 'Advanced Filters', 'filters' ); ?>">
								<strong><?php echo $taxonomy->labels->singular_name; ?>:</strong>
								<?php echo( !empty( $term ) ? esc_html( $term->name ) : 'is <em>empty</em>' ); ?>
							</a>

							<a href="#remove-filter" class="remove-filter" title="<?php esc_attr_e( 'Remove Filter', 'filters' ); ?>">x</a>

							<?php echo FiltersForm::field( 'filter_' . $taxonomy->name, $selected_tax, 'hidden' ); ?>
						</li>
					<?php
					}

					foreach ( $filters as $filter ) {
						$value = filters_var_raw( 'filter_' . $filter, 'get', '', null, true );
						$data_filter = 'filter_' . $filter;

						$start = $end = $value_label = '';

						if ( 'pods' == filters_var( 'from', self::$filters_fields[ $filter ] ) ) {
							if ( in_array( self::$filters_fields[ $filter ][ 'type' ], array(
								'date',
								'datetime',
								'time'
							) )
							) {
								$start = pods_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true );
								$end = pods_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true );

								if ( !empty( $start ) && !in_array( $start, array(
										'0000-00-00',
										'0000-00-00 00:00:00',
										'00:00:00'
									) )
								) {
									$start = PodsForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $start, 'n/j/Y' );
								}

								if ( !empty( $end ) && !in_array( $end, array(
										'0000-00-00',
										'0000-00-00 00:00:00',
										'00:00:00'
									) )
								) {
									$end = PodsForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $end, 'n/j/Y' );
								}

								$value = trim( $start . ' - ' . $end, ' -' );

								$data_filter = 'filter_' . $filter . '_start';
							}
							elseif ( 'pick' == self::$filters_fields[ $filter ][ 'type' ] ) {
								$value_label = trim( PodsForm::field_method( 'pick', 'value_to_label', $filter, $value, self::$filters_fields[ $filter ], self::$filters_pod, null ) );
							}
							elseif ( 'boolean' == self::$filters_fields[ $filter ][ 'type' ] ) {
								$yesno_options = array(
									'1' => pods_var_raw( 'boolean_yes_label', self::$filters_fields[ $filter ][ 'options' ], __( 'Yes', 'pods' ), null, true ),
									'0' => pods_var_raw( 'boolean_no_label', self::$filters_fields[ $filter ][ 'options' ], __( 'No', 'pods' ), null, true )
								);

								if ( isset( $yesno_options[ (string) $value ] ) ) {
									$value_label = $yesno_options[ (string) $value ];
								}
							}
						}
						elseif ( in_array( self::$filters_fields[ $filter ][ 'type' ], array(
							'date',
							'datetime',
							'time'
						) )
						) {
							$start = filters_var_raw( 'filter_' . $filter . '_start', 'get', '', null, true );
							$end = filters_var_raw( 'filter_' . $filter . '_end', 'get', '', null, true );

							if ( !empty( $start ) && !in_array( $start, array(
									'0000-00-00',
									'0000-00-00 00:00:00',
									'00:00:00'
								) )
							) {
								$start = FiltersForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $start, 'n/j/Y' );
							}

							if ( !empty( $end ) && !in_array( $end, array(
									'0000-00-00',
									'0000-00-00 00:00:00',
									'00:00:00'
								) )
							) {
								$end = FiltersForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $end, 'n/j/Y' );
							}

							$value = trim( $start . ' - ' . $end, ' -' );

							$data_filter = 'filter_' . $filter . '_start';
						}

						if ( strlen( $value_label ) < 1 ) {
							$value_label = $value;
						}
						?>
						<li class="filters-ui-filter-bar-filter" data-filter="<?php echo $data_filter; ?>">
							<a href="#TB_inline?width=640&inlineId=filters-ui-posts-filter-popup" class="thickbox" title="<?php esc_attr_e( 'Advanced Filters', 'filters' ); ?>">
								<strong><?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>:</strong>
								<?php echo( 0 < strlen( $value_label ) ? esc_html( $value_label ) : 'is <em>empty</em>' ); ?>
							</a>

							<a href="#remove-filter" class="remove-filter" title="<?php esc_attr_e( 'Remove Filter', 'filters' ); ?>">x</a>

							<?php
							if ( in_array( self::$filters_fields[ $filter ][ 'type' ], array(
								'date',
								'datetime',
								'time'
							) )
							) {
								echo FiltersForm::field( 'filter_' . $filter . '_start', $start, 'hidden' );
								echo FiltersForm::field( 'filter_' . $filter . '_end', $end, 'hidden' );
							}
							else {
								echo FiltersForm::field( $data_filter, $value, 'hidden' );
							}
							?>
						</li>
					<?php
					}
					?>
				</ul>
			</div>
		</div>

		<script type="text/javascript">
			jQuery( function () {
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

	public function table_popup() {

		ob_start();
		$this->months_dropdown( self::$filters_post_type_object->name );
		$months = ob_get_clean();
		$months = apply_filters( 'filters_ui_popup_months', $months, self::$filters_post_type, self::$filters_post_type_object );
		?>
		<div id="filters-ui-posts-filter-popup" class="hidden">
			<form action="" method="get" class="filters-ui-posts-filter-popup">
				<h2><?php _e( 'Advanced Filters', 'filters' ); ?></h2>

				<div class="filters-ui-posts-filters">
					<?php
					$excluded_filters = array( 's', 'paged', 'action', 'action2' );

					foreach ( self::$filters_filters as $filter ) {
						$excluded_filters[ ] = 'filters_relation';
						$excluded_filters[ ] = 'filters_compare_' . $filter;
						$excluded_filters[ ] = 'filter_' . $filter;
						$excluded_filters[ ] = 'filter_' . $filter . '_start';
						$excluded_filters[ ] = 'filter_' . $filter . '_end';
					}

					foreach ( self::$filters_taxonomies as $k => $taxonomy ) {
						$selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

						$excluded_filters[ ] = 'filters_compare_' . $taxonomy->name;
						$excluded_filters[ ] = 'filter_' . $taxonomy->name;
					}

					$get = $_GET;

					foreach ( $get as $k => $v ) {
						if ( in_array( $k, $excluded_filters ) || empty( $v ) ) {
							continue;
						}
						?>
						<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>" />
					<?php
					}

					if ( !empty( $months ) ) {
						$zebra = true;

						// @todo Make months optional
						$selected_m = filters_var_raw( 'm', 'get', '' );
						?>
						<p class="filters-ui-posts-filter-toggled filters-ui-posts-filter-m<?php echo( $zebra ? ' clear' : '' ); ?>">
							<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( empty( $selected_m ) ? '' : ' hidden' ); ?>">+</span>
							<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( empty( $selected_m ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

							<label for="m">
								<?php _e( 'Month' ); ?>
							</label>

                        <span class="filters-ui-posts-filter<?php echo( empty( $selected_tax ) ? ' hidden' : '' ); ?>">
                            <?php echo $months; ?>
                        </span>
						</p>
					<?php
					}

					$zebra = empty( $zebra );

					foreach ( self::$filters_taxonomies as $taxonomy ) {
						$selected_tax = filters_var_raw( 'filter_' . $taxonomy->name, 'get', '' );

						if ( !empty( $selected_tax ) && function_exists( 'icl_object_id' ) ) {
							$selected_tax = icl_object_id( $selected_tax, $taxonomy->name, true );
						}

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
							<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( empty( $selected_tax ) ? '' : ' hidden' ); ?>">+</span>
							<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( empty( $selected_tax ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

							<label for="taxonomy_<?php echo $taxonomy->name; ?>">
								<?php echo $taxonomy->labels->singular_name; ?>
							</label>

                        <span class="filters-ui-posts-filter<?php echo( empty( $selected_tax ) ? ' hidden' : '' ); ?>">
                            <?php wp_dropdown_categories( $dropdown_options ); ?>
                        </span>
						</p>
						<?php
						$zebra = empty( $zebra );
					}

					foreach ( self::$filters_filters as $filter ) {
						?>
						<p class="filters-ui-posts-filter-toggled filters-ui-posts-filter-<?php echo $filter . ( $zebra ? ' clear' : '' ); ?>">
							<?php
							if ( 'pods' == filters_var( 'from', self::$filters_fields[ $filter ] ) ) {
								if ( in_array( self::$filters_fields[ $filter ][ 'type' ], array(
									'date',
									'datetime',
									'time'
								) )
								) {
									$start = pods_var_raw( 'filter_' . $filter . '_start', 'get', pods_var_raw( 'filter_default', self::$filters_fields[ $filter ], '', null, true ), null, true );
									$end = pods_var_raw( 'filter_' . $filter . '_end', 'get', pods_var_raw( 'filter_ongoing_default', self::$filters_fields[ $filter ], '', null, true ), null, true );

									// override default value
									self::$filters_fields[ $filter ][ 'options' ][ 'default_value' ] = '';
									self::$filters_fields[ $filter ][ 'options' ][ self::$filters_fields[ $filter ][ 'type' ] . '_allow_empty' ] = 1;

									if ( !empty( $start ) && !in_array( $start, array(
											'0000-00-00',
											'0000-00-00 00:00:00',
											'00:00:00'
										) )
									) {
										$start = PodsForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $start, 'n/j/Y' );
									}

									if ( !empty( $end ) && !in_array( $end, array(
											'0000-00-00',
											'0000-00-00 00:00:00',
											'00:00:00'
										) )
									) {
										$end = PodsForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $end, 'n/j/Y' );
									}
									?>
									<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( ( empty( $start ) && empty( $end ) ) ? '' : ' hidden' ); ?>">+</span>
									<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( ( empty( $start ) && empty( $end ) ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'pods' ); ?></span>

									<label for="pods-form-ui-filter-<?php echo $filter; ?>_start">
										<?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>
									</label>

									<span class="filters-ui-posts-filter<?php echo( ( empty( $start ) && empty( $end ) ) ? ' hidden' : '' ); ?>">
									<?php echo PodsForm::field( 'filter_' . $filter . '_start', $start, self::$filters_fields[ $filter ][ 'type' ], self::$filters_fields[ $filter ] ); ?>

										<label for="pods-form-ui-filter-<?php echo $filter; ?>_end">to</label>
										<?php echo PodsForm::field( 'filter_' . $filter . '_end', $end, self::$filters_fields[ $filter ][ 'type' ], self::$filters_fields[ $filter ] ); ?>
								</span>
								<?php
								}
								elseif ( 'pick' == self::$filters_fields[ $filter ][ 'type' ] ) {
									$value = pods_var_raw( 'filter_' . $filter, 'get', '' );

									if ( strlen( $value ) < 1 ) {
										$value = pods_var_raw( 'filter_default', self::$filters_fields[ $filter ] );
									}

									// override default value
									self::$filters_fields[ $filter ][ 'options' ][ 'default_value' ] = '';

									self::$filters_fields[ $filter ][ 'options' ][ 'pick_format_type' ] = 'single';
									self::$filters_fields[ $filter ][ 'options' ][ 'pick_format_single' ] = 'dropdown';

									self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ] = pods_var_raw( 'ui_input_helper', pods_var_raw( 'options', self::$filters_fields[ $filter ], array(), null, true ), '', null, true );
									self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ] = pods_var_raw( 'ui_input_helper', self::$filters_fields[ $filter ][ 'options' ], self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ], null, true );

									$options = array_merge( self::$filters_fields[ $filter ], self::$filters_fields[ $filter ][ 'options' ] );
									?>
									<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( empty( $value ) ? '' : ' hidden' ); ?>">+</span>
									<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( empty( $value ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'pods' ); ?></span>

									<label for="pods-form-ui-filter-<?php echo $filter; ?>">
										<?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>
									</label>

									<span class="filters-ui-posts-filter<?php echo( strlen( $value ) < 1 ? ' hidden' : '' ); ?>">
									<?php echo PodsForm::field( 'filter_' . $filter, $value, 'pick', $options ); ?>
								</span>
								<?php
								}
								elseif ( 'boolean' == self::$filters_fields[ $filter ][ 'type' ] ) {
									$value = pods_var_raw( 'filter_' . $filter, 'get', '' );

									if ( strlen( $value ) < 1 ) {
										$value = pods_var_raw( 'filter_default', self::$filters_fields[ $filter ] );
									}

									// override default value
									self::$filters_fields[ $filter ][ 'options' ][ 'default_value' ] = '';

									self::$filters_fields[ $filter ][ 'options' ][ 'pick_format_type' ] = 'single';
									self::$filters_fields[ $filter ][ 'options' ][ 'pick_format_single' ] = 'dropdown';

									self::$filters_fields[ $filter ][ 'options' ][ 'pick_object' ] = 'custom-simple';
									self::$filters_fields[ $filter ][ 'options' ][ 'pick_custom' ] = array(
										'1' => pods_var_raw( 'boolean_yes_label', self::$filters_fields[ $filter ][ 'options' ], __( 'Yes', 'pods' ), null, true ),
										'0' => pods_var_raw( 'boolean_no_label', self::$filters_fields[ $filter ][ 'options' ], __( 'No', 'pods' ), null, true )
									);

									self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ] = pods_var_raw( 'ui_input_helper', pods_var_raw( 'options', self::$filters_fields[ $filter ], array(), null, true ), '', null, true );
									self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ] = pods_var_raw( 'ui_input_helper', self::$filters_fields[ $filter ][ 'options' ], self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ], null, true );

									$options = array_merge( self::$filters_fields[ $filter ], self::$filters_fields[ $filter ][ 'options' ] );
									?>
									<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( empty( $value ) ? '' : ' hidden' ); ?>">+</span>
									<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( empty( $value ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'pods' ); ?></span>

									<label for="pods-form-ui-filter-<?php echo $filter; ?>">
										<?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>
									</label>

									<span class="filters-ui-posts-filter<?php echo( strlen( $value ) < 1 ? ' hidden' : '' ); ?>">
									<?php echo PodsForm::field( 'filter_' . $filter, $value, 'pick', $options ); ?>
								</span>
								<?php
								}
								else {
									$value = pods_var_raw( 'filter_' . $filter, 'get' );

									if ( strlen( $value ) < 1 ) {
										$value = pods_var_raw( 'filter_default', self::$filters_fields[ $filter ] );
									}

									$options = array(
										'input_helper' => pods_var_raw( 'ui_input_helper', pods_var_raw( 'options', self::$filters_fields[ $filter ], array(), null, true ), '', null, true )
									);

									if ( empty( $options[ 'input_helper' ] ) && isset( self::$filters_fields[ $filter ][ 'options' ] ) && isset( self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ] ) ) {
										$options[ 'input_helper' ] = self::$filters_fields[ $filter ][ 'options' ][ 'input_helper' ];
									}
									?>
									<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( empty( $value ) ? '' : ' hidden' ); ?>">+</span>
									<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( empty( $value ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'pods' ); ?></span>

									<label for="pods-form-ui-filter-<?php echo $filter; ?>">
										<?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>
									</label>

									<span class="filters-ui-posts-filter<?php echo( empty( $value ) ? ' hidden' : '' ); ?>">
									<?php echo PodsForm::field( 'filter_' . $filter, $value, 'text', $options ); ?>
								</span>
								<?php
								}
							}
							elseif ( in_array( self::$filters_fields[ $filter ][ 'type' ], array(
								'date',
								'datetime',
								'time'
							) )
							) {
								$start = filters_var_raw( 'filter_' . $filter . '_start', 'get', filters_var_raw( 'filter_default', self::$filters_fields[ $filter ], '', null, true ), null, true );
								$end = filters_var_raw( 'filter_' . $filter . '_end', 'get', filters_var_raw( 'filter_ongoing_default', self::$filters_fields[ $filter ], '', null, true ), null, true );

								if ( !empty( $start ) && !in_array( $start, array(
										'0000-00-00',
										'0000-00-00 00:00:00',
										'00:00:00'
									) )
								) {
									$start = FiltersForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $start, 'n/j/Y' );
								}

								if ( !empty( $end ) && !in_array( $end, array(
										'0000-00-00',
										'0000-00-00 00:00:00',
										'00:00:00'
									) )
								) {
									$end = FiltersForm::field_method( self::$filters_fields[ $filter ][ 'type' ], 'convert_date', $end, 'n/j/Y' );
								}
								?>
								<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( ( empty( $start ) && empty( $end ) ) ? '' : ' hidden' ); ?>">+</span>
								<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( ( empty( $start ) && empty( $end ) ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

								<label for="filters-ui-form-ui-filter-<?php echo $filter; ?>_start">
									<?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>
								</label>

								<span class="filters-ui-posts-filter<?php echo( ( empty( $start ) && empty( $end ) ) ? ' hidden' : '' ); ?>">
                                <?php echo FiltersForm::field( 'filter_' . $filter . '_start', $start, self::$filters_fields[ $filter ][ 'type' ], self::$filters_fields[ $filter ] ); ?>

									<label for="filters-ui-form-ui-filter-<?php echo $filter; ?>_end">to</label>
									<?php echo FiltersForm::field( 'filter_' . $filter . '_end', $end, self::$filters_fields[ $filter ][ 'type' ], self::$filters_fields[ $filter ] ); ?>
                            </span>
							<?php
							}
							else {
								$value = filters_var_raw( 'filter_' . $filter, 'get', filters_var( 'filter_default', self::$filters_fields[ $filter ], '', null, true ), null, true );
								?>
								<span class="filters-ui-posts-filter-toggle toggle-on<?php echo( empty( $value ) ? '' : ' hidden' ); ?>">+</span>
								<span class="filters-ui-posts-filter-toggle toggle-off<?php echo( empty( $value ) ? ' hidden' : '' ); ?>"><?php _e( 'Clear', 'filters' ); ?></span>

								<label for="filters-ui-form-ui-filter-<?php echo $filter; ?>">
									<?php echo self::$filters_fields[ $filter ][ 'label' ]; ?>
								</label>

								<span class="filters-ui-posts-filter<?php echo( empty( $value ) ? ' hidden' : '' ); ?>">
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

					<p class="filters-ui-posts-filter-toggled filters-ui-posts-filter-s<?php echo( $zebra ? ' clear' : '' ); ?>">
						<label for="filters-ui-form-ui-s"><?php _e( 'Search Text', 'filters' ); ?></label>
						<?php echo FiltersForm::field( 's', filters_var_raw( 's', 'get' ), 'text' ); ?>
					</p>

					<?php $zebra = empty( $zebra ); ?>
				</div>

				<p class="submit<?php echo( $zebra ? ' clear' : '' ); ?>">
					<input type="submit" value="<?php esc_attr_e( 'Search', 'filters' ); ?> <?php echo self::$filters_post_type_object->label; ?>" class="button button-primary" />
				</p>
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