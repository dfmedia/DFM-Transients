<?php

if ( ! class_exists( 'DFM_Transient_Scheduler' ) ) :

	/**
	 * Class DFM_Transient_Scheduler
	 * Attaches hooks to run incoming post requests.
	 */
	class DFM_Transient_Scheduler {

		/**
		 * Namespace for the custom endpoint
		 */
		const API_NAMESPACE = 'dfm-transients/v1';

		/**
		 * Endpoint to run the regeneration on
		 */
		const ENDPOINT_RUN = 'regenerate';

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
			add_action( 'wp_loaded', array( $this, 'get_transients' ), 9999 );
			add_action( 'rest_api_init', [ $this, 'register_rest_endpoint' ] );
			add_action( 'shutdown', [ $this, 'execute_async_updates' ] );
		}

		/**
		 * Retrieves all the transients from the global variable, adds hooks for them, and stores the ID into a variable.
		 *
		 * @access public
		 * @return void
		 */
		public function get_transients() {

			global $dfm_transients;

			// Bail if there are no transients registered.
			if ( empty( $dfm_transients ) || ! is_array( $dfm_transients ) ) {
				return;
			}

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

		}

		/**
		 * Registers the custom endpoint. Callback on rest_api_init
		 *
		 * @access public
		 * @return void
		 */
		public function register_rest_endpoint() {
			register_rest_route(
				self::API_NAMESPACE, '/' . self::ENDPOINT_RUN . '/(?P<transient>[\w|-]+)', [
					'methods'             => 'PUT',
					'callback'            => [ $this, 'rest_handler' ],
					'permission_callback' => [ $this, 'check_rest_permissions' ],
					'show_in_index'       => false,
				]
			);
		}


		/**
		 * Check that the endpoint is actually accessible
		 *
		 * @param WP_REST_Request $request The incoming request object
		 *
		 * @return bool|WP_Error
		 */
		public function check_rest_permissions( $request ) {

			$body = json_decode( $request->get_body(), true );

			if (
				! defined( 'DFM_TRANSIENTS_SECRET' ) ||
				! isset( $body['secret'] ) ||
				! hash_equals( DFM_TRANSIENTS_SECRET, $body['secret'] )
			) {
				return new \WP_Error( 'no-secret', __( 'Secret must be defined and passed to the request for the async processor to run', 'dfm-transients' ) );
			}

			return true;

		}

		/**
		 * Handle the update for async data processing
		 *
		 * @param WP_REST_Request $request The incoming request object
		 *
		 * @return WP_REST_Response
		 * @access public
		 */
		public function rest_handler( $request ) {

			$transient_name = ( isset( $request['transient'] ) ) ? $request['transient'] : '';
			$body = json_decode( $request->get_body(), true );
			$modifiers = ( ! empty( $body['modifiers'] ) ) ? $body['modifiers'] : '';
			$key = ( ! empty( $body['lock_key'] ) ) ? sanitize_text_field( $body['lock_key'] ) : '';

			/**
			 * Make the request as non-blocking as possible
			 */
			if ( function_exists( 'fastcgi_finish_request' ) && version_compare( phpversion(), '7.0.16', '>=' ) ) {
				fastcgi_finish_request();
			}

			if ( is_array( $modifiers ) ) {
				foreach ( $modifiers as $modifier ) {
					$result = self::run_update( $transient_name, $modifier, $key );
				}
			} else {
				$result = self::run_update( $transient_name, $modifiers, $key );
			}

			if ( is_wp_error( $result ) ) {
				return rest_ensure_response( $result );
			}

			return rest_ensure_response( sprintf( __( '%s transient updated', 'wp-queue-tasks' ), $transient_name ) );

		}

		/**
		 * Executes the async updates for all of the transients that need to be updated
		 *
		 * @access public
		 * @return void
		 */
		public function execute_async_updates() {
			if ( ! empty( DFM_Transient_Hook::get_updates() ) && is_array( DFM_Transient_Hook::get_updates() ) ) {
				foreach ( DFM_Transient_Hook::get_updates() as $transient_name => $modifiers ) {
					new DFM_Async_Handler( $transient_name, $modifiers );
				}
			}
		}

		/**
		 * Runs the updates for transient callbacks
		 *
		 * @param string $transient The name of the transient to update
		 * @param string|array|int $modifier The modifier(s) to update for the transient
		 * @param string $key The lock key to verify the thread owns the current process
		 *
		 * @return WP_Error|bool
		 * @throws Exception
		 */
		public static function run_update( $transient, $modifier, $key ) {

			$transient_obj = new DFM_Transients( $transient, $modifier );

			// Bail if another process is already trying to update this transient.
			if ( $transient_obj->is_locked() && ! $transient_obj->owns_lock( $key ) ) {
				return new WP_Error( 'transient-update-failed', __( 'The current transient is locked from updates', 'dfm-transients' ) );
			}

			if ( ! $transient_obj->is_locked() ) {
				$transient_obj->lock_update();
			}

			try {
				$data = call_user_func( $transient_obj->transient_object->callback, $modifier );
			} catch ( \Throwable $error ) {

				/**
				 * Hook that fires if the update fails
				 *
				 * @param Throwable $error The error returned from the callback
				 * @param DFM_Transients $transient_obj The transient object
				 * @param string $key The key for the transient lock
				 */
				do_action( 'dfm_transients_update_failed', $error, $transient_obj, $key );
				return new WP_Error( 'transient-update-failed', __( 'The callback to update this transient returned with an error' ) );
			}

			$transient_obj->set( $data );
			$transient_obj->unlock_update();
			return true;

		}

	}

endif;

new DFM_Transient_Scheduler();
