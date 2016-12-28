<?php
/**
 * Handles all of the getting and setting of transients.
 */
if ( ! class_exists( 'DFM_Transients' ) ) :

	class DFM_Transients {

		/**
		 * Transient key prefix
		 *
		 * @var string
		 * @access private
		 */
		private $prefix = 'dfm_transient_';

		/**
		 * Name of the transient
		 *
		 * @var string
		 * @access private
		 */
		private $transient;

		/**
		 * Transient Object
		 *
		 * @var string
		 * @access public
		 */
		public $transient_object;

		/**
		 * Unique modifier. In the cases of the post_meta and term_meta storage engine; the post ID or term ID.
		 *
		 * @var string
		 * @access private
		 */
		private $modifier = '';

		/**
		 * The storage key for the transient
		 *
		 * @var string
		 * @access private
		 */
		private $key = '';

		/**
		 * Lock stored in transient.
		 *
		 * @var string
		 * @access private
		 */
		private $lock = '';

		/**
		 * Stores the key for the update lock
		 *
		 * @var string
		 * @access private
		 */
		private $lock_key = '';

		/**
		 * DFM_Transients constructor.
		 *
		 * @param string $transient
		 * @param string|int $modifier
		 */
		function __construct( $transient, $modifier ) {

			global $dfm_transients;
			$this->transient = $transient;
			$this->modifier = $modifier;
			$this->transient_object = $dfm_transients[ $this->transient ];
			$this->key = $this->cache_key();
			$this->lock_key = uniqid( 'dfm_lt_' );
			$this->prefix = apply_filters( 'dfm_transient_prefix', 'dfm_transient_' );

		}

		/**
		 * Method to retrieve a transient from the cache or DB.
		 *
		 * @return mixed|WP_Error|string|array
		 * @access public
		 */
		public function get() {

			if ( ! isset( $this->transient_object ) ) {
				return new WP_Error( 'invalid-transient', __( 'You are trying to retrieve a transient that doesn\'t exist', 'dfm-transients' ) );
			}

			switch ( $this->transient_object->cache_type ) {
				case 'transient':
					return $this->get_from_transient();
					break;
				case 'post_meta':
					return $this->get_from_meta( 'post' );
					break;
				case 'term_meta':
					return $this->get_from_meta( 'term' );
					break;
				case 'user_meta':
					return $this->get_from_meta( 'user' );
					break;
				default:
					return new WP_Error( 'invalid-cache-type', __( 'When registering your transient, you used an invalid cache type. Valid options are transient, post_meta, term_meta.', 'dfm-transients' ) );
			}

		}

		/**
		 * This method handles storing transient data to the database or object cache
		 *
		 * @param string|array $data
		 * @access public
		 */
		public function set( $data ) {

			if ( ! isset( $this->transient_object ) ) {
				new WP_Error( 'invalid-transient', __( 'You are trying to set data to a transient that doesn\'t exist', 'dfm-transients' ) );
				return;
			}

			if ( false === $data || is_wp_error( $data ) ) {
				return;
			}

			switch ( $this->transient_object->cache_type ) {
				case 'transient':
					$this->save_to_transient( $data );
					break;
				case 'post_meta':
					$this->save_to_metadata( $data, 'post' );
					break;
				case 'term_meta':
					$this->save_to_metadata( $data, 'term' );
					break;
				case 'user_meta':
					$this->save_to_metadata( $data, 'user' );
					break;
				default:
					new WP_Error( 'invalid-cache-type', __( 'When registering your transient, you used an invalid cache type. Valid options are transient, post_meta, term_meta.', 'dfm-transients' ) );
			}

		}

		/**
		 * @return WP_Error|null
		 */
		public function delete() {

			if ( ! isset( $this->transient_object ) ) {
				return new WP_Error( 'invalid-transient', __( 'You are trying to retrieve a transient that doesn\'t exist', 'dfm-transients' ) );
			}

			switch( $this->transient_object->cache_type ) {
				case 'transient':
					$this->delete_from_transient();
					break;
				case 'post_meta':
					$this->delete_from_metadata( 'post' );
					break;
				case 'term_meta':
					$this->delete_from_metadata( 'term' );
					break;
				case 'user_meta':
					$this->delete_from_metadata( 'user' );
					break;
				default:
					new WP_Error( 'invalid-cache-type', __( 'When registering your transient, you used an invalid cache type. Valid options are transient, post_meta, term_meta.', 'dfm-transients' ) );
			}

		}

		/**
		 * Locks the ability to update the transient data. This will prevent race conditions.
		 *
		 * @return void
		 * @access public
		 */
		public function lock_update() {
			set_transient( 'dfm_lt_' . $this->key , $this->lock_key, 400 );
		}

		/**
		 * Unlocks the ability to update the transient data.
		 *
		 * @return void
		 * @access public
		 */
		public function unlock_update() {
			delete_transient( 'dfm_lt_' . $this->key );
		}

		/**
		 * Returns true or false for if the transient is locked for updates.
		 *
		 * @return bool
		 * @access public
		 */
		public function is_locked() {
			$this->lock = get_transient( 'dfm_lt_' . $this->key );
			return (bool) $this->lock;
		}

		/**
		 * Returns true or false if the method that is trying to update the transient owns the lock for it.
		 *
		 * @param string $lock_key The key to compare
		 * @return bool
		 * @access public
		 */
		public function owns_lock( $lock_key ) {
			if ( empty( $this->lock ) ) {
				$this->lock = get_transient( 'dfm_lt_' . $this->key );
			}
			if ( $this->lock === $lock_key ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Handles retrieving data from a transient
		 *
		 * @return mixed|string|array
		 * @access private
		 */
		private function get_from_transient() {

			$data = get_transient( $this->key );

			if ( false === $data ) {
				$data = call_user_func( $this->transient_object->callback, $this->modifier );
				$this->set( $data );
			} elseif ( $this->is_expired( $data ) && ! $this->is_locked() ) {
				$this->lock_update();
				if ( $this->should_soft_expire() ) {
					new DFM_Async_Handler( $this->transient, $this->modifier, $this->lock_key );
				} else {
					$data = call_user_func( $this->transient_object->callback, $this->modifier );
					$this->set( $data );
					$this->unlock_update();
				}
			}

			if ( $this->should_expire() && $this->should_soft_expire() && is_array( $data ) && array_key_exists( 'data', $data ) ) {
				$data = $data['data'];
			}

			return $data;

		}

		/**
		 * Handles retrieving data from post_meta or term_meta
		 *
		 * @param string $type
		 * @return mixed string|array
		 * @access private
		 */
		private function get_from_meta( $type ) {

			$data = get_metadata( $type, $this->modifier, $this->key, true );
			
			$data_exists = true;
			
			if ( empty( $data ) ) {
				$data_exists = metadata_exists( $type, $this->modifier, $this->key );
			}

			if ( false === $data_exists ) {
				$data = call_user_func( $this->transient_object->callback, $this->modifier );
				$this->set( $data );
			} elseif ( $this->is_expired( $data ) && ! $this->is_locked() ) {
				$this->lock_update();
				if ( $this->should_soft_expire() ) {
					new DFM_Async_Handler( $this->transient, $this->modifier, $this->lock_key );
				} else {
					$data = call_user_func( $this->transient_object->callback, $this->modifier );
					$this->set( $data );
					$this->unlock_update();
				}
			}

			if ( $this->should_expire() && is_array( $data ) && array_key_exists( 'data', $data ) ) {
				$data = $data['data'];
			}

			return $data;

		}

		/**
		 * Handles saving of data for a transient storage type
		 *
		 * @param string|array $data
		 * @return void
		 * @access private
		 */
		private function save_to_transient( $data ) {

			$expiration = 0;

			if ( $this->should_expire() ) {
				$expiration = $this->transient_object->expiration;
				if ( $this->should_soft_expire() ) {
					// Set expiration to a year if we aren't using an object cache, so the transient
					// isn't autoloaded from the database
					$expiration = ( true === wp_using_ext_object_cache() ) ? 0 : YEAR_IN_SECONDS;
					$data = array(
						'data' => $data,
						'expiration' => time() + (int) $this->transient_object->expiration,
					);
				}
			}

			set_transient( $this->key, $data, $expiration );

		}

		/**
		 * Handles saving data for meta storage engine
		 *
		 * @param string|array $data
		 * @param string $type
		 */
		private function save_to_metadata( $data, $type ) {

			if ( $this->should_expire() ) {
				$data = array(
					'data' => $data,
					'expiration' => time() + (int) $this->transient_object->expiration,
				);
			}

			update_metadata( $type, $this->modifier, $this->key, $data );

		}

		private function delete_from_transient() {
			delete_transient( $this->key );
		}

		private function delete_from_metadata( $type ) {
			delete_metadata( $type, $this->modifier, $this->key );
		}

		/**
		 * Hashes storage key
		 *
		 * @param string $key
		 * @return string
		 * @access private
		 */
		private function hash_key( $key ) {

			$hashed_key = md5( $key . $this->modifier );

			if ( 'post_meta' === $this->transient_object->cache_type || 'term_meta' === $this->transient_object->cache_type ) {
				$hashed_key = $this->prefix . $hashed_key;
			}

			return $hashed_key;

		}

		/**
		 * Generates the cache key to be used for getting and setting transient data
		 *
		 * @return string
		 * @access private
		 */
		private function cache_key() {

			$key = $this->transient_object->key;

			if ( $this->should_hash() ) {
				$key = $this->hash_key( $key );
			}

			switch( $this->transient_object->cache_type ) {
				case 'post_meta':
				case 'term_meta':
				case 'user_meta':
					$key = $this->prefix . $key;
					break;
				case 'transient':
					if ( ! empty( $this->modifier ) ) {
						$key = $key . '_' . $this->modifier;
					}
					break;
			}

			return $key;

		}

		/**
		 * Whether or not we should hash the storage key
		 *
		 * @return bool
		 * @access private
		 */
		private function should_hash() {
			return $this->transient_object->hash_key;
		}

		/**
		 * Whether or not the transient data should expire
		 *
		 * @return bool
		 * @access private
		 */
		private function should_expire() {
			return $this->transient_object->expiration;
		}

		/**
		 * Whether or not the transient should expire softly
		 *
		 * @return bool
		 * @access private
		 */
		private function should_soft_expire() {
			return $this->transient_object->soft_expiration;
		}

		/**
		 * Whether or not the transient data has expired
		 *
		 * @param array|string $data
		 * @return bool
		 */
		private function is_expired( $data ) {
			if ( '' !== $this->transient_object->expiration && is_array( $data ) && $data['expiration'] < time() ) {
				return true;
			} else {
				return false;
			}
		}

	}

endif;
