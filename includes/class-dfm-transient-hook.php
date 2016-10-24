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
		private $transient;

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
		 * DFM_Transient_Hook constructor.
		 *
		 * @param string $transient
		 * @param string $hook
		 * @param string $callback
		 */
		public function __construct( $transient, $hook, $callback = '' ) {

			$this->transient = $transient;
			$this->hook      = $hook;
			$this->callback  = $callback;

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
		 * Spawn a process to regenerate the transient data
		 *
		 * @return void
		 * @access public
		 */
		public function spawn() {

			$modifier = '';

			if ( ! empty( $this->callback ) ) {

				// Grab the args from the hook we are using
				$hook_args = func_get_args();

				// Call the callback for this hook, and pass the args to it.
				// This callback decides if we should actually run the process to update the transient data based on the
				// args passed to it. The callback should return false if we don't want to run the action, and should
				// return the transient modifier if we do want to run it.
				$modifier  = call_user_func( $this->callback, $hook_args );
				if ( false === $modifier ) {
					return;
				}
			}

			new DFM_Async_Handler( $this->transient, $modifier );

		}

	}

}
