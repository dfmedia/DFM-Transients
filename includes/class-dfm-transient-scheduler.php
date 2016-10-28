<?php

if ( ! class_exists( 'DFM_Transient_Scheduler' ) ) {
	/**
	 * Class DFM_Transient_Scheduler
	 * Attaches hooks to run incoming post requests.
	 */
	class DFM_Transient_Scheduler {

		/**
		 * Stores the names of all the transient ID's
		 * @var array
		 * @access private
		 */
		private $transient_ids = array();

		/**
		 * DFM_Transient_Scheduler constructor.
		 *
		 * @access private
		 */
		function __construct() {
			// Adds a high priority to make sure all of the transients have been registered.
			add_action( 'after_setup_theme', array( $this, 'get_transients' ), 9999 );
		}

		/**
		 * Retrieves all the transients from the global variable, adds hooks for them, and stores the ID into a variable.
		 *
		 * @access public
		 * @return void
		 */
		public function get_transients() {

			global $dfm_transients;

			foreach ( $dfm_transients as $transient_id => $transient_args ) {

				// Store unique identifier to array
				$this->transient_ids[] = $transient_id;

				$async_update = $transient_args->async_updates;

				// If the transient has update hooks associated with it, build the hook for it to run.
				if ( false !== $transient_args->update_hooks ) {

					// If there are multiple hooks where this should fire, loop through all of them, and build a hook for each.
					if ( is_array( $transient_args->update_hooks ) ) {
						foreach ( $transient_args->update_hooks as $hook_name => $callback ) {
							new DFM_Transient_Hook( $transient_id, $hook_name, $async_update, $callback );
						}
					} else {
						new DFM_Transient_Hook( $transient_id, $transient_args->update_hooks, $async_update );
					}

				}

			}

			// Kick off generation of async processing hooks.
			$this->post_processing_hooks();

		}

		/**
		 * Build a hook for an async process for each of the transients to grab onto
		 *
		 * @return void
		 * @access public
		 */
		private function post_processing_hooks() {
			foreach ( $this->transient_ids as $transient_id ) {
				add_action( 'admin_post_nopriv_dfm_' . $transient_id, array( $this, 'run_update' ) );
			}
		}

		/**
		 * Handle the update for async data processing
		 *
		 * @return void
		 * @access public
		 */
		public function run_update() {

			$transient_name = empty( $_POST['transient_name'] ) ? false : $_POST['transient_name'];
			$modifier = empty( $_POST['modifier'] ) ? '' : $_POST['modifier'];
			$nonce = empty( $_POST['_nonce'] ) ? '' : $_POST['_nonce'];

			// Bail if a transient name wasn't passed for some reason
			if ( empty( $transient_name ) ) {
				die();
			}

			$verify_nonce = new DFM_Async_Nonce( $transient_name );

			// Bail if we couldn't verify the nonce as legit
			if ( false === $verify_nonce->verify( $nonce ) ) {
				die();
			}

			$transient_obj = new DFM_Transients( $transient_name, $modifier );

			// Bail if another process is already trying to update this transient.
			if ( $transient_obj->is_locked() && ! $transient_obj->owns_lock() ) {
				die();
			}

			if ( ! $transient_obj->is_locked() ) {
				$transient_obj->lock_update();
			}

			global $dfm_transients;

			$transient = $dfm_transients[ $transient_name ];

			$data = call_user_func( $transient->callback, $modifier );
			$transient_obj->set( $data );

			$transient_obj->unlock_update();

			die();

		}

	}

}

new DFM_Transient_Scheduler();
