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
		private static $prefix = 'dfm_transient_';

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
		public $modifier = '';

		/**
		 * Stored the object ID where the transient data is stored
		 *
		 * @var int|null $object_id
		 */
		public $object_id = 0;

		/**
		 * Modifier added for backwards compatibility
		 *
		 * @var string $bc_modifier
		 * @access private
		 */
		private $bc_modifier = '';

		/**
		 * The storage key for the transient
		 *
		 * @var string
		 * @access public
		 */
		public $key = '';

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
		 * @access public
		 */
		public $lock_key = '';

		/**
		 * Flag for if we are attempting a retry
		 *
		 * @var $doing_retry bool
		 * @access private
		 */
		private $doing_retry = false;

		/**
		 * Stores the array of meta storage options, mostly for in_array checks
		 *
		 * @var $meta_types array
		 * @access private
		 */
		private $meta_types = [ 'post_meta', 'term_meta', 'user_meta' ];

		/**
		 * DFM_Transients constructor.
		 *
		 * @param string     $transient Name of the transient
		 * @param string|int $modifier  Unique modifier for the transient
		 * @param null|int   $object_id The ID of the object the transient data is related to
		 *
		 * @throws Exception
		 */
		function __construct( $transient, $modifier, $object_id = null ) {

			global $dfm_transients;

			if ( empty( $dfm_transients[ $transient ] ) ) {
				throw new Exception( __( 'You are trying to retrieve a transient that doesn\'t exist', 'dfm-transient' ) );
			}

			$this->transient        = $transient;
			$this->modifier         = $modifier;
			$this->object_id        = $object_id;
			$this->transient_object = $dfm_transients[ $this->transient ];
			$this->lock_key         = uniqid( 'dfm_lt_' );
			self::$prefix           = apply_filters( 'dfm_transient_prefix', 'dfm_transient_', $this->transient, $this->modifier, $this->object_id );

			/**
			 * For backwards compatibility, use the modifier value as the object ID if no ID is supplied, but it's an object type cache.
			 */
			if ( 'transient' !== $this->transient_object->cache_type && empty( $object_id ) ) {
				$this->object_id = absint( $modifier );
				$this->bc_modifier = $modifier;
				$this->modifier = '';
			}

			/**
			 * Generate the cache key after we've applied the backward compat fix above ^
			 */
			$this->key = $this->cache_key();

		}

		/**
		 * Method to retrieve a transient from the cache or DB.
		 *
		 * @return mixed|WP_Error|string|array
		 * @access public
		 */
		public function get() {

			if ( 'transient' === $this->transient_object->cache_type ) {
				$this->get_from_transient();
			} else {
				$meta_type = DFM_Transient_Utils::get_meta_type( $this->transient_object->cache_type );
				if ( is_wp_error( $meta_type ) ) {
					return $meta_type;
				} else {
					$this->get_from_meta( $meta_type );
				}
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
		 * @param string|array $data The data to add to the transient
		 * @access public
		 * @return mixed|WP_Error|Void
		 * @throws Exception
		 */
		public function set( $data ) {

			if ( false === $data || is_wp_error( $data ) ) {
				$this->facilitate_retry();
				return;
			}

			if ( 'transient' === $this->transient_object->cache_type ) {
				$this->save_to_transient( $data );
			} else {
				$meta_type = DFM_Transient_Utils::get_meta_type( $this->transient_object->cache_type );
				if ( is_wp_error( $meta_type ) ) {
					return $meta_type;
				} else {
					$this->save_to_metadata( $data, $meta_type );
				}
			}

		}

		/**
		 * This method handles the deletion of a transient
		 *
		 * @return mixed|void|WP_Error
		 * @access public
		 * @throws Exception
		 */
		public function delete() {

			if ( 'transient' === $this->transient_object->cache_type ) {
				$this->delete_from_transient();
			} else {
				$meta_type = DFM_Transient_Utils::get_meta_type( $this->transient_object->cache_type );
				if ( is_wp_error( $meta_type ) ) {
					return $meta_type;
				} else {
					$this->delete_from_metadata( $meta_type );
				}
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

			return $this->get_transient_data( $data );

		}

		/**
		 * Handles retrieving data from post_meta or term_meta
		 *
		 * @param string $type The object type the meta is attached to
		 * @return mixed string|array
		 * @access private
		 */
		private function get_from_meta( $type ) {

			$data = get_metadata( $type, $this->object_id, $this->key, true );

			if ( empty( $data ) ) {
				$data_exists = metadata_exists( $type, $this->modifier, $this->key );
				$data = ( false === $data_exists ) ? false : $data;
			}

			return $this->get_transient_data( $data );

		}

		/**
		 * Handles the low level retrieval and rehydrating of data if necessary for all cache types
		 *
		 * @param mixed $data The data beind returned from the get_transient or get_metadata functions
		 * @return bool|mixed
		 * @access private
		 */
		private function get_transient_data( $data ) {

			// Check to see if we set a backwards compatible modifier
			if ( 'transient' !== $this->transient_object->cache_type && ! empty( $this->bc_modifier ) ) {
				$modifier = $this->bc_modifier;
			} else {
				$modifier = $this->modifier;
			}

			if ( false === $data || ( defined( 'DFM_TRANSIENTS_HOT_RELOAD' ) && true === DFM_TRANSIENTS_HOT_RELOAD ) ) {

				if ( true === $this->doing_retry() ) {
					return false;
				}
				$data = call_user_func( $this->transient_object->callback, $modifier, $this->object_id );
				$this->set( $data );
			} elseif ( $this->is_expired( $data ) && ! $this->is_locked() ) {
				$this->lock_update();
				if ( $this->should_soft_expire() ) {
					new DFM_Async_Handler( $this->transient, $modifier, $this->object_id, $this->lock_key );
				} else {
					$data = call_user_func( $this->transient_object->callback, $modifier, $this->object_id );
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
		 * @param string|array $data The data to save to the transient
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
					$data       = array(
						'data'       => $data,
						'expiration' => time() + (int) $this->transient_object->expiration,
					);
				}
			}

			set_transient( $this->key, $data, $expiration );

		}

		/**
		 * Handles saving data for meta storage engine
		 *
		 * @param string|array $data The data to save
		 * @param string       $type The object type the meta is connected to
		 *
		 * @access private
		 * @return void
		 */
		private function save_to_metadata( $data, $type ) {

			if ( $this->should_expire() ) {
				$data = array(
					'data'       => $data,
					'expiration' => time() + (int) $this->transient_object->expiration,
				);
			}

			$r = update_metadata( $type, $this->object_id, $this->key, $data );

			if ( ! empty( $this->modifier ) ) {
				$this->add_meta_map( $type, $this->transient_object->key );
			}

		}

		/**
		 * Deletes a transient stored in the default transient storage engine
		 *
		 * @access private
		 * @uses delete_transient()
		 * @return void
		 */
		private function delete_from_transient() {
			delete_transient( $this->key );
		}

		/**
		 * Deletes a transient stored in metadata
		 *
		 * @param string $type The object type related to the metadata
		 * @uses delete_metadata()
		 * @return void
		 * @access private
		 */
		private function delete_from_metadata( $type ) {
			if ( ! empty( $this->modifier ) ) {
				$meta_map = $this->get_meta_map( $type, $this->object_id, $this->key );
				if ( ! empty( $meta_map ) && is_array( $meta_map ) ) {
					foreach ( $meta_map as $key ) {
						delete_metadata( $type, $this->object_id, $key );
					}
				}
			}
			delete_metadata( $type, $this->object_id, $this->key );
		}

		private function add_meta_map( $type, $transient_key ) {

			$map = get_metadata( $type, $this->object_id, self::meta_map_key( $transient_key ), true );
			if ( ! empty( $map ) ) {
				$map[ $this->modifier ] = $this->key;
			} else {
				$map = [ $this->modifier => $this->key ];
			}

			update_metadata( $type, $this->object_id, $this->meta_map_key( $transient_key ), array_unique( $map ) );

		}

		public static function get_meta_map( $type, $object_id, $transient_key ) {

			if ( is_wp_error( $type ) ) {
				return;
			}

			$map = get_metadata( $type, $object_id, self::meta_map_key( $transient_key ), true );
			if ( empty( $map ) ) {
				$map = [];
			}
			return $map;
		}

		public static function meta_map_key( $transient_key ) {
			return self::$prefix . $transient_key . '_map';
		}

		/**
		 * If a callback function fails to return the correct data, this will store the stale data back into the
		 * transient, and then set the expiration of the data at an exponential scale, so we are not constantly
		 * retrying to get the data (if an API is down or something).
		 *
		 * @access private
		 * @return void
		 * @throws Exception
		 */
		private function facilitate_retry() {

			// Set flag while doing a retry to prevent infinite loops.
			$this->doing_retry = true;

			// Retrieve the stale data.
			$current_data = $this->get();

			// If there is nothing already stored for the transient, bail.
			if ( false === $current_data ) {
				return;
			}

			// Store the expiration set when registering the transient. Our timeout should not exceed this number.
			$max_expiration = $this->transient_object->expiration;

			// Retrieve the cache fail amount from the cache
			$failed_num = wp_cache_get( $this->key . '_failed', 'dfm_transients_retry' );

			// Default to 1 failure if there's nothing set, or it's set to zero. This is so it doesn't mess with
			// the `pow` func.
			if ( false === $failed_num || 0 === $failed_num ) {
				$failures = 1;
			} else {
				$failures = $failed_num + 1;
			}

			// Generate the new expiration time. This essentially just multiplies the amount of failures by itself, and
			// then multiplies it by one minute to get the expiration, so if it is retrying it for the 5th time, it will
			// do 5*5 (which is 25) so it will set the retry to 25 minutes.
			$new_expiration = ( pow( $failures, 2 ) * MINUTE_IN_SECONDS );

			// Only set the new expiration if it's less than the original registered expiration.
			if ( $new_expiration < $max_expiration ) {
				$this->transient_object->expiration = absint( $new_expiration );
			}

			// Save the stale data with the new expiration
			$this->set( $current_data );

			// Add 1 to the the failures in the cache.
			wp_cache_set( $this->key . '_failed', $failures, 'dfm_transients_retry', DAY_IN_SECONDS );

		}

		/**
		 * Hashes storage key
		 *
		 * @param string $key Name of the key to hash
		 * @return string
		 * @access private
		 */
		private function hash_key( $key ) {

			$hashed_key = md5( $key );

			if ( in_array( $this->transient_object->cache_type, $this->meta_types, true ) ) {
				/**
				 * If the storage type is *_meta then prepend the prefix after we hash so we can
				 * still find it for debugging
				 */
				$hashed_key = self::$prefix . $hashed_key;
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

			if ( ! empty( $this->modifier ) ) {
				// Add the unique modifier to the key for regular transients
				$key = $key . '_' . $this->modifier;
			}

			if ( $this->should_hash() ) {
				return $this->hash_key( $key );
			}

			if ( in_array( $this->transient_object->cache_type, $this->meta_types, true ) ) {
				// Add the prefix to transients stored in meta only so they can be identified
				$key = self::$prefix . $key;
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
			return (bool) $this->transient_object->hash_key;
		}

		/**
		 * Whether or not the transient data should expire
		 *
		 * @return bool
		 * @access private
		 */
		private function should_expire() {
			return (bool) $this->transient_object->expiration;
		}

		/**
		 * Whether or not the transient should expire softly
		 *
		 * @return bool
		 * @access private
		 */
		private function should_soft_expire() {
			return (bool) $this->transient_object->soft_expiration;
		}

		/**
		 * Whether or not the transient data has expired
		 *
		 * @param array|string $data The data to check if it's expired
		 * @access private
		 * @return bool
		 */
		protected function is_expired( $data ) {
			if ( ! empty( $this->transient_object->expiration ) && is_array( $data ) && $data['expiration'] < time() ) {
				return true;
			}
			return false;
		}

		/**
		 * Whether or not a retry is occurring
		 *
		 * @access private
		 * @return bool
		 */
		private function doing_retry() {
			return (bool) $this->doing_retry;
		}

	}

endif;
