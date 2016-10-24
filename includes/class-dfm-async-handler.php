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
		 * DFM_Async_Handler constructor.
		 *
		 * @param string $transient Name of the transient
		 * @param string $modifier Unique modifier for the transient
		 */
		function __construct( $transient, $modifier ) {

			$this->transient_name = $transient;
			$this->modifier = $modifier;
			// Spawn the event on shutdown so we are less likely to run into timeouts, or block other processes
			add_action( 'shutdown', array( $this, 'spawn_event' ) );

		}

		public function spawn_event() {

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
				),
			);

			$url = admin_url( 'admin-post.php' );
			wp_remote_post( $url, $request_args );

		}

	}

}
