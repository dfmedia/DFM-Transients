<?php

if ( ! class_exists( 'DFM_Transient_Admin' ) ) {

	/**
	 * Class DFM_Transient_Admin
	 * Handles all of the admin UI
	 */
	class DFM_Transient_Admin {

		/**
		 * Stores the capability for viewing the backend UI
		 *
		 * @var string $cap
		 * @access private
		 */
		private $cap = '';

		/**
		 * Stores the bool value for if the site is using an object cache or not
		 *
		 * @todo: If this is true, "cache" should be the default dashboard view
		 * @var bool $has_obj_cache
		 * @access private
		 */
		private $has_obj_cache = false;

		/**
		 * Contains instance of list view class
		 *
		 * @var object $list_obj
		 * @access private
		 */
		private $list_obj;

		/**
		 * DFM_Transient_Admin constructor.
		 */
		function __construct() {
			$this->load_dependencies();
			add_action( 'init', array( $this, 'setup' ) );
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
		}

		/**
		 * Loads file dependencies
		 *
		 * @access private
		 * @return void
		 */
		private function load_dependencies() {
			require_once( plugin_dir_path( __FILE__ ) . 'class-dfm-transient-meta-ui.php' );
			require_once( plugin_dir_path( __FILE__ ) . 'class-dfm-transient-control-ui.php' );
		}

		/**
		 * Sets up some static variables at an execution point where the data can be retrieved & filtered
		 *
		 * @access public
		 * @return void
		 */
		public function setup() {
			$this->cap = apply_filters( 'dfm_transient_capability', 'manage_options' );
			$this->has_obj_cache = wp_using_ext_object_cache();
		}

		/**
		 * Adds the menu item for the transient control dashboard
		 *
		 * @access public
		 * @return void
		 */
		public function add_menu() {

			$hook = add_submenu_page(
				'tools.php',
				__( 'Transient Control', 'transient-control' ),
				__( 'Transient Control' ),
				$this->cap,
				'transient-control',
				array( $this, 'admin_screen' )
			);

			add_action( 'load-' . $hook, array( $this, 'screen_options' ) );

		}

		/**
		 * Creates the UI for the transient control admin dashboard
		 *
		 * @access public
		 * @return void
		 */
		public function admin_screen() {

			echo '<div class="wrap"><h2>' . esc_html__( 'Transient Control', 'dfm-transients' ) . '</h2>';
			echo '<form method="POST">';
			echo '<div class="button-holder">';
			$this->toggle_button( 'database', __( 'Database', 'dfm-transients' ) );
			$this->toggle_button( 'cache', __( 'Cache', 'dfm-transients' ) );
			echo '</div>';
			$this->list_obj->prepare_items();
			$this->list_obj->display();
			echo '</form>';
			echo '</div>';

		}

		/**
		 * Adds screen options for the transient UI dashboard
		 *
		 * @access public
		 * @return void
		 */
		public function screen_options() {

			$args = array(
				'label' => 'Transients Per Page',
				'default' => 10,
				'option' => 'transients_per_page',
			);

			add_screen_option( 'per_page', $args );

			// Class must be instantiated here.
			$this->list_obj = new DFM_Transient_Control_Ui();

		}

		/**
		 * Generates the HTML for the transient type toggle buttons
		 *
		 * @param string $type
		 * @param string $text
		 * @return void
		 * @access private
		 */
		private function toggle_button( $type, $text ) {

			$current_type = ( isset( $_GET['transient_type'] ) ) ? $_GET['transient_type'] : 'database';
			$classes = array( 'button' );

			if ( $current_type === $type ) {
				$classes[] = 'active';
				$classes[] = 'button-primary';
			}

			$url = add_query_arg( array( 'page' => 'transient-control', 'transient_type' => $type ), admin_url( 'tools.php' ) );

			echo '<a class="' . esc_attr( implode( $classes, ' ' ) ) . '" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';

		}
	}

	new DFM_Transient_Admin();

}
