<?php
if ( ! class_exists( 'DFM_Async_Handler' ) ) {

	/**
	 * Class DFM_Async_Handler
	 * Spawn's the event to create a remote request to kick off the async data processing
	 */
	class DFM_Async_Handler {

		/**
		 * Name of the transient
		 *
		 * @var string
		 * @access private
		 */
		private $transient_name;

		/**
		 * Unique modifier for the transient
		 *
		 * @var string
		 * @access private
		 */
		private $modifier;

		/**
		 * Lock key for matching the update locking lock.
		 *
		 * @var string
		 * @access private
		 */
		private $lock_key;

		/**
		 * DFM_Async_Handler constructor.
		 *
		 * @param string $transient Name of the transient
		 * @param string $modifier Unique modifier for the transient
		 * @param string $lock_key Key for matching the update locking
		 */
		function __construct( $transient, $modifier, $lock_key = '' ) {

			$this->transient_name = $transient;
			$this->modifier       = $modifier;
			$this->lock_key       = $lock_key;
			// Spawn the event on shutdown so we are less likely to run into timeouts, or block other processes
			add_action( 'shutdown', array( $this, 'spawn_event' ) );

		}

		/**
		 * Sends off a request to process and update the transient data asynchronously
		 *
		 * @return array|void|WP_Error
		 * @access public
		 */
		public function spawn_event() {

			// Prevents infinite loops if we are debugging transients in the init hook, or another hook that would run
			// when handling the async post data
			$is_async_action = ( isset( $_POST['async_action'] ) ) ? true : false;

			if ( true === $is_async_action ) {
				return;
			}

			$nonce = new DFM_Async_Nonce( $this->transient_name );
			$nonce = $nonce->create();

			$request_args = array(
				'timeout'  => 0.01,
				'blocking' => false, // don't wait for a response
				'body'     => array(
					'transient_name' => $this->transient_name,
					'modifier'       => $this->modifier,
					'action'         => 'dfm_' . $this->transient_name,
					'_nonce'         => $nonce,
					'async_action'   => true,
					'lock_key'       => $this->lock_key,
				),
			);

			$url = admin_url( 'admin-post.php' );
			return wp_safe_remote_post( $url, $request_args );

		}

	}

}
