<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	// the list table class *needs* to exist at this point, if it isn't for some reason, load it ourselves.
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class DFM_Transient_Control_Ui
 * Extends the built in list table class to create the custom UI for managing transients
 */
class DFM_Transient_Control_Ui extends WP_List_Table {

	/**
	 * Stores the raw transients from queries or the cache
	 *
	 * @var array $raw_transient_results
	 * @access private
	 */
	private $raw_transient_results = array();

	/**
	 * The type of transient cache (database|cache)
	 *
	 * @var string $cache_type
	 * @access private
	 */
	private $cache_type = '';

	/**
	 * DFM_Transient_Control_Ui constructor.
	 */
	public function __construct() {

		parent::__construct( array(
			'singular' => __( 'Transient', 'dfm-transients' ),
			'plural' => __( 'Transients', 'dfm-transients' ),
			'ajax' => false,
		) );

		$this->get_cache_type();

	}

	/**
	 * Figure out which transient cache type view we are on
	 *
	 * @access private
	 * @return void
	 */
	private function get_cache_type() {

		$type = ( isset( $_GET['transient_type'] ) ) ? $_GET['transient_type'] : 'database';
		$this->cache_type = $type;

	}

	/**
	 * Extension of the prepare_items method in the parent class
	 *
	 * @access public
	 * @return void
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		$per_page = $this->get_items_per_page( 'transients_per_page', 10 );
		$current_page = $this->get_pagenum();
		$total_items = $this->record_count();

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
		) );

		$this->items = $this->get_transients( $per_page, $current_page );

	}

	/**
	 * Figures out which get method to use based on our transient cache type
	 *
	 * @param int $per_page
	 * @param int $current_page
	 * @access public
	 * @return array|null|object
	 */
	public function get_transients( $per_page = 10, $current_page = 0 ) {

		if ( 'cache' === $this->cache_type ) {
			$transients = $this->get_cache_transients( $per_page, $current_page );
		} else {
			$transients = $this->get_db_transients( $per_page, $current_page );
		}
		return $transients;

	}

	/**
	 * Retrieves transients from the database
	 *
	 * @param int $per_page
	 * @param int $current_page
	 * @access private
	 * @return array|null|object
	 */
	private function get_db_transients( $per_page, $current_page ) {

		global $wpdb;

		$per_page = absint( $per_page );
		$current_page = absint( $current_page );

		$results = $wpdb->get_results( $wpdb->prepare(
			"
				SELECT *
				FROM $wpdb->options 
				WHERE option_name 
				LIKE %s 
				AND option_name 
				NOT LIKE %s
				ORDER BY option_id DESC
				LIMIT %d, %d;
			",
			'%\_transient\_%',
			'%\_transient\_timeout%',
			$current_page,
			$per_page
		), 'ARRAY_A' );

		$this->raw_transient_results = $results;
		return $results;

	}

	/**
	 * Retrieves transients from the object cache
	 *
	 * @param int $per_page
	 * @param int $current_page
	 * @access private
	 * @return array
	 */
	private function get_cache_transients( $per_page, $current_page ) {

		if ( empty( $this->raw_transient_results ) ) {
			global $wp_object_cache;
			$raw_keys = $wp_object_cache->cache['wp_:options:alloptions'];
			$transients = array();
			if ( ! empty( $raw_keys ) && is_array( $raw_keys ) ) {
				foreach ( $raw_keys as $transient_name => $value ) {
					if ( '_transient_' === substr( $transient_name, 0, 11 ) ) {
						$transients[] = array( 'option_name' => $transient_name, 'option_value' => $value );
					}
				}
			}
			$this->raw_transient_results = $transients;
		} else {
			$transients = $this->raw_transient_results;
		}

		if ( 0 !== $per_page && 0 !== $current_page ) {
			$transients = array_slice( $transients, ( ( $per_page * $current_page) - $per_page ), $per_page );
		}

		return $transients;

	}

	/**
	 * Calculates the total amount of records we have. This is needed for pagination to work
	 *
	 * @access public
	 * @return int
	 */
	public function record_count() {

		if ( 'cache' === $this->cache_type ) {
			if ( empty( $this->raw_transient_results ) ) {
				$transients = $this->get_cache_transients( 0, 0 );
			} else {
				$transients = $this->raw_transient_results;
			}
			$count = count( $transients );
		} else {
			global $wpdb;
			$count = $wpdb->get_var( 
				"
					SELECT count(option_id) 
					FROM $wpdb->options 
					WHERE option_name 
					LIKE '%\_transient\_%' 
					AND option_name 
					NOT LIKE '%\_transient\_timeout%'
				" 
			);
		}

		return (int) $count;

	}

	/**
	 * What to display if no items are found
	 *
	 * @access public
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No transients found', 'dfm-transients' );
	}

	/**
	 * Defines the columns we want to display in the list table
	 *
	 * @access public
	 * @return array $columns
	 */
	public function get_columns() {

		$columns = array(
			'option_name' => __( 'Transient Name', 'dfm-transients' ),
			'option_value' => __( 'Transient Value', 'dfm-transients' ),
		);

		return $columns;

	}

	/**
	 * Defines which columns in our list table should be sortable.
	 *
	 * @return array $sortable_columns
	 * @access public
	 */
	public function get_sortable_columns() {

		$sortable_columns = array(
			'option_name' => array( 'option_name', false ),
		);

		return $sortable_columns;

	}

	/**
	 * Handles the output for each of the list table columns
	 *
	 * @param array $item
	 * @param string $column_name
	 * @access public
	 * @return string
	 */
	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {
			case 'option_name':
				return '<strong>' . $item[ $column_name ] . '</strong>';
			case 'option_value':
				return '<textarea rows="5" cols="100" disabled>' . esc_html( $item[ $column_name ] ) . '</textarea>';
			default:
				return esc_html( $item[ $column_name ] );
		}

	}
}
