<?php

if ( ! class_exists( 'DFM_Transient_Hook' ) ) {

	/**
	 * Class DFM_Transient_Hook
	 * Processes data on specified hook when registering transient
	 */
	class DFM_Transient_Hook {

		/**
		 * Name of transient
		 *
		 * @var string
		 * @access private
		 */
		private $transient_name;

		/**
		 * The settings registered to the transient
		 *
		 * @var object $transient_obj
		 * @access private
		 */
		private $transient_obj;

		/**
		 * Name of hook we want to hook into
		 *
		 * @var string
		 * @access private
		 */
		private $hook;

		/**
		 * Callback to decide if we want to re-generate transient data in this hook
		 *
		 * @var string
		 * @access private
		 */
		private $callback;

		/**
		 * Whether or not the update should happen asynchronously
		 *
		 * @var bool $async
		 * @access private
		 *
		 */
		private $async = false;

		/**
		 * DFM_Transient_Hook constructor.
		 *
		 * @param string $transient_name Name of the transient to add the hook callback for
		 * @param object $transient_obj  Settings that were added when the transient was registered
		 * @param string $hook           Name of the hook
		 * @param bool   $async_update   Whether or not the update should happen asynchronously or not
		 * @param string $callback       The callable method to be added to the hook
		 */
		public function __construct( $transient_name, $transient_obj, $hook, $async_update, $callback = '' ) {

			$this->transient_name = $transient_name;
			$this->transient_obj  = $transient_obj;
			$this->hook           = $hook;
			$this->callback       = $callback;
			$this->async          = $async_update;

			$this->add_hook( $hook );

		}

		/**
		 * Dynamically add the hook for regenerating the transient
		 *
		 * @param string $hook
		 * @return void
		 * @access private
		 */
		private function add_hook( $hook ) {
			// Pass an arbitrarily high arg count to avoid errors.
			add_action( $hook, array( $this, 'spawn' ), 10, 20 );
		}

		/**
		 * Run an update in real time for the transient.
		 *
		 * @param string $modifier Name of the modifier
		 * @return void
		 * @access private
		 */
		private function run_update( $modifier, $object_id = null ) {

			if ( $this->async ) {
				new DFM_Async_Handler( $this->transient_name, $modifier, $object_id );
				return;
			}

			$transient_obj = new DFM_Transients( $this->transient_name, $modifier, $object_id );

			// Bail if another process is already trying to update this transient.
			if ( $transient_obj->is_locked() && ! $transient_obj->owns_lock( '' ) ) {
				return;
			}

			if ( ! $transient_obj->is_locked() ) {
				$transient_obj->lock_update();
			}

			$data = call_user_func( $transient_obj->transient_object->callback, $modifier, $object_id );

			$transient_obj->set( $data );

			$transient_obj->unlock_update();

		}

		/**
		 * Spawn a process to regenerate the transient data
		 *
		 * @return void
		 * @access public
		 */
		public function spawn() {

			$modifiers = '';

			if ( ! empty( $this->callback ) ) {

				// Grab the args from the hook we are using
				$hook_args = func_get_args();

				// Call the callback for this hook, and pass the args to it.
				// This callback decides if we should actually run the process to update the transient data based on the
				// args passed to it. The callback should return false if we don't want to run the action, and should
				// return the transient modifier if we do want to run it. You can also optionally return an array of
				// modifiers if you want to update multiple transients at once.
				$modifiers = call_user_func( $this->callback, $hook_args );
				if ( false === $modifiers ) {
					return;
				}
			}

			if ( is_array( $modifiers ) && ! empty( $modifiers ) ) {
				foreach ( $modifiers as $key => $modifier ) {
					if ( is_array( $modifier ) && ! empty( $modifier ) ) {
						foreach ( $modifier as $key_modifier ) {
							$this->dispatch_update( $key_modifier, $key );
						}
					} else {
						$this->dispatch_update( $modifier, $key );
					}
				}
			} else {
				$this->dispatch_update( $modifiers );
			}

		}

		private function dispatch_update( $modifier, $object_id = null ) {

			if ( 'transient' !== $this->transient_obj->cache_type ) {

				if ( empty( $object_id ) ) {
					$object_id = absint( $modifier );
					$translated_obj_id = true;
				}

				if ( empty( $modifier ) || isset( $translated_obj_id ) ) {
					$modifier_map = DFM_Transients::get_meta_map( DFM_Transient_Utils::get_meta_type( $this->transient_obj->cache_type ), $object_id, $this->transient_obj->key );
					if ( ! empty( $modifier_map ) && is_array( $modifier_map ) ) {
						foreach ( $modifier_map as $modifier => $modifier_key ) {
							$this->run_update( $modifier, $object_id );
						}
					} else {
						$this->run_update( '', $object_id );
					}
				} else {
					$this->run_update( $modifier, $object_id );
				}
			} else {
				$this->run_update( $modifier );
			}

		}

	}

}
