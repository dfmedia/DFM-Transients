<?php

if ( ! class_exists( 'DFM_Transient_Meta_UI' ) ) {
	/**
	 * Class DFM_Transient_Meta_Ui
	 * Adds UI for tracking transient data that's stored in metadata
	 */
	class DFM_Transient_Meta_UI {

		/**
		 * Stores the context of the screen we are on in the admin.
		 *
		 * @var string $context
		 * @access private
		 */
		private $context = '';

		/**
		 * Stores the transient prefix for metadata transients
		 *
		 * @var string $prefix
		 * @access private
		 */
		private $prefix = '';

		/**
		 * Stores the capability to check against when allowing a user to view the UI
		 *
		 * @var string $cap
		 * @access private
		 */
		private $cap = '';

		/**
		 * DFM_Transient_Meta_Ui constructor.
		 */
		function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
			add_action( 'current_screen', array( $this, 'current_context' ) );
			add_action( 'init', array( $this, 'run' ) );
			add_action( 'show_user_profile', array( $this, 'render_transient_list' ), 999, 1 );
		}

		/**
		 * Sets up filtered data at an execution point where it can actually be filtered.
		 *
		 * @access public
		 * @return void
		 */
		public function run() {
			$this->prefix = apply_filters( 'dfm_transient_prefix', 'dfm_transient_' );
			$this->cap = apply_filters( 'dfm_transient_capability', 'manage_options' );
			$taxonomies = get_taxonomies( array( 'public' => true ) );
			if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {
					add_action( 'edit_' . $taxonomy . '_form_fields', array( $this, 'render_transient_list' ), 999, 1 );
				}
			}
		}

		/**
		 * Sets the current screen context
		 *
		 * @access public
		 * @return void
		 */
		public function current_context() {
			$current_screen = get_current_screen();
			$this->context = $current_screen->base;
		}

		/**
		 * Registers the metabox for post-like objects
		 *
		 * @access public
		 * @return void
		 */
		public function meta_box() {
			if ( current_user_can( $this->cap ) ) {
				add_meta_box( 'dfm_transient_ui', 'Transients' , array( $this, 'render_transient_list' ), array( 'post', 'page' ) );
			}
		}

		/**
		 * Renders the metabox
		 *
		 * @param object $object The term or user object passed from the hook.
		 */
		public function render_transient_list( $object ) {

			if ( ! current_user_can( $this->cap ) ) {
				return;
			}

			$transients = $this->get_transients( $object );

			if ( ! empty( $transients ) ) {

				echo '<table class="widefat striped">';
				echo '<thead>';
					echo '<td>' . esc_html__( 'Transient Key', 'dfm-transients' ) . '</td>';
					echo '<td>' . esc_html__( 'Value', 'dfm-transients' ) . '</td>';
					echo '<td>' . esc_html__( 'Expiration', 'dfm-transients' ) . '</td>';
				echo '</thead>';
				foreach ( $transients as $transient_key => $transient_value ) {
					$data = maybe_unserialize( $transient_value[0] );
					echo '<tr>';
						echo '<td>' . esc_html( $transient_key ) . '</td>';
					if ( is_array( $data ) && array_key_exists( 'data', $data ) && ! empty( $data['expiration'] ) ) {
						echo '<td><textarea cols="50" disabled>' . esc_html( $data['data'] ) . '</textarea></td>';
						echo '<td>' . esc_html( date( 'm-d-y H:i:s', $data['expiration'] ) ) . '</td>';
					} else {
						echo '<td><textarea cols="50" disabled>' . esc_html( $data ) . '</textarea></td>';
						echo '<td></td>';
					}
					echo '</tr>';
				}
				echo '</table>';

			}
		}

		/**
		 * Retrieves the list of transients stored in the objects metadata
		 *
		 * @param object $object
		 * @access private
		 * @return array
		 */
		private function get_transients( $object ) {

			$transients = array();

			if ( 'post' === $this->context ) {
				$object_id = get_the_ID();
				$type = 'post';
			} elseif ( 'term' === $this->context ) {
				$object_id = $object->term_id;
				$type = 'term';
			} elseif ( 'profile' === $this->context ) {
				$object_id = $object->ID;
				$type = 'user';
			}

			if ( isset( $type, $object_id ) ) {
				$meta = get_metadata( $type, $object_id );

				$prefix_str_length = strlen( $this->prefix );

				foreach ( $meta as $meta_key => $value ) {
					if ( substr( $meta_key, 0, $prefix_str_length ) === $this->prefix ) {
						$transients[ $meta_key ] = $value;
					}
				}
			}

			return $transients;

		}

	}

}

new DFM_Transient_Meta_UI();
