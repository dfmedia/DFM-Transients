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
		private $modifiers;

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
		 * @param string       $transient Name of the transient
		 * @param string|array $modifiers Unique modifier for the transient
		 * @param string       $lock_key  Key for matching the update locking
		 */
		function __construct( $transient, $modifiers, $lock_key = '' ) {

			$this->transient_name = $transient;
			$this->modifiers      = $modifiers;
			$this->lock_key       = $lock_key;
			// Spawn the event on shutdown so we are less likely to run into timeouts, or block other processes

			if ( ! defined( 'DOING_CRON' ) && ! defined( 'REST_REQUEST' ) ) {
				if ( 'shutdown' === current_action() ) {
					$this->spawn_event();
				} else {
					add_action( 'shutdown', array( $this, 'spawn_event' ) );
				}
			}

		}

		/**
		 * Sends off a request to process and update the transient data asynchronously
		 *
		 * @return array|WP_Error
		 * @access public
		 */
		public function spawn_event() {

			if ( ! defined( 'DFM_TRANSIENTS_SECRET' ) ) {
				return new \WP_Error( 'no-secret', __( 'You need to define the DFM_TRANSIENTS_SECRET constant in order to use this feature', 'dfm-transients' ) );
			}

			$request_args = array(
				'timeout'  => 0.01,
				'blocking' => false, // don't wait for a response
				'method'   => 'PUT',
				'body'     => wp_json_encode( array(
					'secret'         => DFM_TRANSIENTS_SECRET,
					'modifiers'      => $this->modifiers,
					'lock_key'       => $this->lock_key,
				) ),
			);

			$url = get_rest_url( null, DFM_Transient_Scheduler::API_NAMESPACE . '/' . DFM_Transient_Scheduler::ENDPOINT_RUN . '/' . $this->transient_name );
			return wp_safe_remote_post( $url, $request_args );

		}

	}

}
