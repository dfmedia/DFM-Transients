<?php

if ( ! class_exists( 'DFM_Async_Nonce' ) ) {
	/**
	 * Class DFM_Async_Nonce
	 * Generates and verify's nonce's for async data processing
	 *
	 * A lot of this is based off of this: https://github.com/techcrunch/wp-async-task
	 * which is based off of default WordPress transients, but aren't attached to a user.
	 */
	class DFM_Async_Nonce {

		/**
		 * Stores the name of the transient
		 *
		 * @var string
		 * @access private
		 */
		private $transient = '';

		/**
		 * Name of the action for the async action hook
		 *
		 * @var string
		 * @access private
		 */
		private $action = '';

		/**
		 * DFM_Async_Nonce constructor.
		 *
		 * @param string $transient Name of the transient
		 * @access private
		 */
		function __construct( $transient ) {
			$this->transient = $transient;
			$this->action = 'dfm_' . $this->transient;
		}

		/**
		 * Creates a nonce for the async action
		 *
		 * @return string $nonce
		 * @access public
		 */
		public function create() {
			$i = wp_nonce_tick();
			return substr( wp_hash( $i . $this->action . get_class( $this ), 'nonce' ), -12, 10 );
		}

		/**
		 * Verifies the nonce for the async action
		 *
		 * @param string $nonce Nonce to verify against
		 * @return bool
		 * @access public
		 */
		public function verify( $nonce ) {

			$i = wp_nonce_tick();

			// Nonce generated 0-12 hours ago
			if ( substr( wp_hash( $i . $this->action . get_class( $this ), 'nonce' ), -12, 10 ) === $nonce ) {
				return true;
			}

			// Nonce generated 12-24 hours ago
			if ( substr( wp_hash( ( $i - 1 ) . $this->action . get_class( $this ), 'nonce' ), -12, 10 ) === $nonce ) {
				return true;
			}

			return false;

		}

	}

}
