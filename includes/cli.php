<?php

if ( class_exists( 'WP_CLI' ) ) {

	class DFM_Transients_CLI extends WP_CLI {

		/**
		 * @param $args
		 * @param $assoc_args
		 */
		public function get( $args, $assoc_args ) {

			$transient_name = ( isset( $args[0] ) ) ? $args[0] : '';
			$modifier = ( isset( $args[1] ) ) ? $args[1] : '';

			if ( empty( $transient_name ) ) {
				parent::error( 'A transient name must be passed' );
			}

			$multiple = false;

			if ( 'all' === $modifier || ! empty( $args[2] ) ) {
				$multiple = true;
				$transient_obj = new DFM_Transients( $transient_name, '' );
				if ( 'all' === $modifier ) {

					if ( 'transient' === $transient_obj->transient_object->cache_type ) {
						WP_CLI::error( 'Cannot retrieve multiple transients when the modifier isn\'t explicitly passed for transients stored as normal transients since there is a fairly high likelyhood that the transients are only stored in the cache, and not the database.' );
					}

					$modifier_count = $this->get_all_modifiers( $transient_obj->key, $transient_obj->transient_object->cache_type, true );
					$modifier_data = $this->get_all_modifiers( $transient_obj->key, $transient_obj->transient_object->cache_type, false, 100 );

					if ( ! empty( $modifier_count ) && 100 < $modifier_count[0] ) {
						WP_CLI::success( sprintf( '%d modifiers found. Only showing the first 100 below', $modifier_count[0] ) );
					}

				} else {
					$modifier_data = array_slice( $args, 1, 999 ); //@TODO make this way less ghetto...
				}
			} else {
				$transient_data = dfm_get_transient( $transient_name, $modifier );
			}

			if ( false === $multiple && isset( $transient_data ) ) {

				$data = array( array( 'modifier' => $modifier, 'data' => $transient_data ) );

			} else {

				$data = array();

				if ( ! empty( $modifier_data ) && is_array( $modifier_data ) && isset( $transient_obj ) ) {
					foreach ( $modifier_data as $modifier ) {
						$transient_obj->modifier = $modifier;
						$data[] = array( 'modifier' => $modifier, 'data' => $transient_obj->get() );
					}
				}

			}

			WP_CLI\Utils\format_items( 'table', $data, array( 'modifier', 'data' ) );

		}

		public function set( $args, $assoc_args ) {

		}

		public function delete( $args, $assoc_args ) {

			$transient_name = ( isset( $args[0] ) ) ? $args[0] : '';
			$modifier = ( isset( $args[1] ) ) ? $args[1] : '';

			if ( empty( $transient_name ) ) {
				parent::error( 'A transient name must be passed' );
			}

			$multiple = false;

		}

		public function list( $args, $assoc_args ) {

			global $dfm_transients;

			$transients = $dfm_transients;

			if ( empty( $dfm_transients ) ) {
				parent::error( 'Looks like you don\'t have any transients registered' );
			}

			$transient_names = ( isset( $args ) ) ? $args : array();

			if ( ! empty( $transient_names ) ) {
				$transients = array_intersect_key( $transients, array_flip( $transient_names ) );
			}

			$supported_props = array( 'key', 'hash_key', 'cache_type', 'async_updates', 'expiration', 'soft_expiration' );

			if ( ! empty( $assoc_args ) ) {
				$filter_args = array_intersect_key( $assoc_args, array_flip( $supported_props ) );
				$transients = wp_list_filter( $transients, $filter_args );
			}

			if ( empty( $transients ) ) {
				parent::error( 'No transients found with this criteria' );
			}

			if ( ! empty( $assoc_args['fields'] ) ) {
				if ( is_string( $assoc_args['fields'] ) ) {
					$fields = explode( ',', $assoc_args );
				} else {
					$fields = $assoc_args['fields'];
				}
				$fields = array_intersect( $fields, $supported_props );
			} else {
				$fields = $supported_props;
			}

			$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
			$formatter->display_items( $transients );

		}

		private function get_all_modifiers( $meta_key, $type, $count = false, $limit = false ) {

			global $wpdb;

			$object_type = substr( $type, 0, 4 );

			$table = _get_meta_table( $object_type );

			$select = ( true === $count ) ? 'count(*)' : $object_type . '_id';

			$limit = ( false !== $limit ) ? 'LIMIT ' . absint( $limit ) : '';

			if ( false === $table ) {
				return false;
			}

			$modifiers = $wpdb->get_col( $wpdb->prepare( "
				SELECT $select
				FROM $table
				WHERE meta_key='%s'
				$limit
			",
				$meta_key
			) );

			//print_r( $meta_key );
			return $modifiers;

		}

	}

	WP_CLI::add_command( 'dfm-transients', 'DFM_Transients_CLI' );

}
