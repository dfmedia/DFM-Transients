<?php

if ( class_exists( 'WP_CLI' ) ) {

	class DFM_Transients_CLI extends WP_CLI {

		private $supported_props;

		/**
		 * Retrieves information about a specific DFM Transient
		 *
		 * ## OPTIONS
		 * <transient_name>
		 * : Name of the transient you want to retrieve information about
		 *
		 * [<modifiers>...]
		 * : List of modifiers to get data about
		 *
		 * [--format]
		 * : Render the output in a particular format
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - ids
		 *   - json
		 *   - count
		 *   - yaml
		 * ---
		 *
		 * [--limit]
		 * : The amount of transients to retrieve. Pass -1 for no limit.
		 * ---
		 * default: 100
		 * ---
		 *
		 * [--fields]
		 * : The fields you would like to return
		 *
		 * ## EXAMPLES
		 *
		 *     $wp dfm-transients get dfm_current_standout_count
		 *     +----------+------+
		 *     | modifier | data |
		 *     +----------+------+
		 *     |          | 0    |
		 *     +----------+------+
		 *
		 *     $wp dfm-transients get term_posts all
		 *     +----------+------------------------------------------------------------------------------------------------------------------+
		 *     | modifier | data                                                                                                             |
		 *     +----------+------------------------------------------------------------------------------------------------------------------+
		 *     | 7681     | [2187069,2189051,2188653,2188525,2188689,2188302,2188435,2188561,2188519,2188486,2188180,2188052,2188378,2187293 |
		 *     |          | ,2187831,2185712,2186525,2186643,2186373,2186221]                                                                |
		 *     | 4687     | [2188121,2187831,2187084,2185789,2185208,2185126,2185123,2185003,2183357,2183326,2183276,2183089,2183081,2183060 |
		 *     |          | ,2182991,2181531,2180932,2180486,2177749,2179168]                                                                |
		 *     | 1797     | [2186592,2177875,2170239,2162981,2155404,2148740]                                                                |
		 *     | 8732     | [2188653,2185461,2183384,2182193,2178326,2175819,2174603,2170396,2168493,2167170,2163516,2160893,2158586,2156073 |
		 *     |          | ,2154094,2151996,2149292,2147779,2147211,2147178]                                                                |
		 *     | 8624     | [2181074,2173277,2157496,2149817,2138319]                                                                        |
		 *     +----------+------------------------------------------------------------------------------------------------------------------+
		 *
		 *     $wp dfm-transients get term_posts all --fields=modifier --format=ids
		 *     97 7681 4687 1797 8732 8624 98 9372 7682 48 94 75 66 30 15 7629 40 53 59 36
		 *
		 * ## AVAILABLE FIELDS
		 * * modifier
		 * * data
		 *
		 * @param array $args
		 * @param $assoc_args
		 */
		public function get( $args, $assoc_args ) {

			$transient_name = array_shift( $args );
			$modifiers = $args;

			$this->supported_props = array( 'modifier', 'data' );

			$options = wp_parse_args( $assoc_args, array(
					'format' => '',
					'limit'  => 100,
				)
			);

			if ( empty( $transient_name ) ) {
				parent::error( 'A transient name must be passed' );
			}

			$transient_obj  = new DFM_Transients( $transient_name, '' );
			$transient_type = $transient_obj->transient_object->cache_type;
			$transient_key  = $transient_obj->key;

			switch ( $options['format'] ) {
				case 'ids':
					$data = $this->get_all_modifiers( $transient_key, $transient_type, false, $options['limit'] );
					break;
				case 'count':
					$data = $this->get_all_modifiers( $transient_key, $transient_type, true );
					$data = ( ! empty( $data ) && is_array( $data ) ) ? $data[0] : 0;
					break;
				default:
					if ( 'all' === $modifiers[0] ) {
						$modifier_keys = $this->get_all_modifiers( $transient_key, $transient_type, false, $options['limit'] );
					} elseif ( ! empty( $modifiers ) ) {
						$modifier_keys = $modifiers;
					} else {
						$data = array(
							array(
								'modifier' => '',
								'data'     => dfm_get_transient( $transient_name ),
							),
						);
					}

					if ( ! isset( $data ) ) {

						$data = array();

						if ( isset( $modifier_keys ) && ! empty( $modifier_keys ) && is_array( $modifier_keys ) ) {
							foreach ( $modifier_keys as $modifier_key ) {
								$transient_obj->modifier = absint( $modifier_key );
								$data[] = array(
									'modifier' => $modifier_key,
									'data'     => $transient_obj->get(),
								);
							}
						}
					}
					break;
			}

			if ( 'count' !== $options['format'] ) {
				$this->format_output( $data, $assoc_args );
				parent::line();
			} else {
				parent::success( sprintf( '%d transients found', $data ) );
			}

		}

		/**
		 * Sets data for a particular DFM Transient
		 *
		 * ## OPTIONS
		 * <transient_name>
		 * : Name of the transient you would like to set data for
		 *
		 * [<modifiers>...]
		 * : List of modifiers you want to update the transient data for
		 *
		 * [--data=<data>]
		 * : The new data you want to store in the transient
		 *
		 * ## EXAMPLES
		 *
		 *     $wp dfm-transients set my_transient --data="test"
		 *     Successfully updated the my_transient transient
		 *
		 *     $wp dfm-transients set term_posts $(wp dfm-transients get term_posts --fields=modifier --format=ids) --data="test"
		 *     Updating Transients  100% [=============================================] 0:00 / 0:00
		 *     Success: Successfully updated 20 transients
		 *
		 * @param $args
		 * @param $assoc_args
		 */
		public function set( $args, $assoc_args ) {

			$transient_name = array_shift( $args );
			$modifiers = $args;

			if ( empty( $transient_name ) ) {
				parent::error( 'A transient name must be passed' );
			}

			$data = \WP_CLI\Utils\get_flag_value( $assoc_args, 'data', '' );

			if ( ! empty( $modifiers ) && is_array( $modifiers ) ) {

				if ( 10 < count( $modifiers ) ) {
					$progress = \WP_CLI\Utils\make_progress_bar( 'Updating Transients', count( $modifiers ) );
				}

				foreach ( $modifiers as $modifier ) {
					dfm_set_transient( $transient_name, $data, absint( $modifier ) );
					if ( isset( $progress ) ) {
						$progress->tick();
					}
				}

				if ( isset( $progress ) ) {
					$progress->finish();
				}

				parent::success( sprintf( 'Successfully updated %d transients', count( $modifiers ) ) );

			} else {
				dfm_set_transient( $transient_name, $data, '' );
				parent::success( sprintf( 'Successfully updated the %s transient', $transient_name ) );
			}

		}

		/**
		 * Deletes some particular DFM Transients
		 *
		 * ## OPTIONS
		 * <transient_name>
		 * : Name of the transient you would like to delete data for
		 *
		 * [<modifiers>...]
		 * : List of modifiers you want to delete the transients for
		 *
		 * ## EXAMPLES
		 *
		 *     $wp dfm-transients delete my_transient
		 *     Success: Successfully deleted transient: my_transient
		 *
		 *     $wp dfm-transients delete term_posts $(wp dfm-transients get term_posts --fields=modifier --format=ids)
		 *     Deleting transients  100% [=============================================] 0:00 / 0:00
		 *     Success: Successfully deleted 20 transients
		 *
		 * @param array $args
		 * @param array $assoc_args
		 */
		public function delete( $args, $assoc_args ) {

			$transient_name = array_shift( $args );
			$modifiers = $args;

			if ( empty( $transient_name ) ) {
				parent::error( 'A transient name must be passed' );
			}

			if ( empty( $modifiers ) ) {
				dfm_delete_transient( $transient_name );
				parent::success( sprintf( 'Successfully deleted transient: %s', $transient_name ) );
			} else {

				if ( count( $modifiers ) > 10 ) {
					$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting transients', count( $modifiers ) );
				}

				if ( is_array( $modifiers ) ) {

					foreach ( $modifiers as $modifier ) {

						dfm_delete_transient( $transient_name, $modifier );

						if ( isset( $progress ) ) {
							$progress->tick();
						}

					}

					if ( isset( $progress ) ) {
						$progress->finish();
					}

				}

				parent::success( sprintf( 'Successfully deleted %d transients', count( $modifiers ) ) );

			}

		}

		/**
		 * Lists all of the registered transients through DFM Transients
		 *
		 * ## OPTIONS
		 * [<transient_names>...]
		 * : Optionally pass the names of the transients you want to get information about
		 *
		 * [--fields]
		 * : Fields to return
		 * ---
		 * default: all
		 * options:
		 *   - key
		 *   - hash_key
		 *   - cache_type
		 *   - async_updates
		 *   - expiration
		 *   - soft_expiration
		 * ---
		 *
		 * [--format]
		 * : Render the output in a particular format
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - ids
		 *   - json
		 *   - yaml
		 * ---
		 *
		 * [--<field>=<value>]
		 * : One or more fields to filter the list with
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp dfm-transients list
		 *     +----------------------------+----------+------------+---------------+------------+-----------------+
		 *     | key                        | hash_key | cache_type | async_updates | expiration | soft_expiration |
		 *     +----------------------------+----------+------------+---------------+------------+-----------------+
		 *     | dfm_instagram_api_feed     |          | transient  |               | 3600       | 1               |
		 *     | dfm_current_standout_count |          | transient  |               | 3600       | 1               |
		 *     | author_featured_articles   |          | post_meta  | 1             |            |                 |
		 *     | nav_menu                   |          | transient  | 1             |            |                 |
		 *     +----------------------------+----------+------------+---------------+------------+-----------------+
		 *
		 *     $ wp dfm-transients list dfm_current_standout_count author_featured_articles --fields=key,cache_type,async_updates
		 *     +----------------------------+------------+---------------+
		 *     | key                        | cache_type | async_updates |
		 *     +----------------------------+------------+---------------+
		 *     | dfm_current_standout_count | transient  |               |
		 *     | author_featured_articles   | post_meta  | 1             |
		 *     +----------------------------+------------+---------------+
		 *
		 *     $wp dfm-transients list --async_updates=1 --fields=key,cache_type,async_updates
		 *     +--------------------------+------------+---------------+
		 *     | key                      | cache_type | async_updates |
		 *     +--------------------------+------------+---------------+
		 *     | author_featured_articles | post_meta  | 1             |
		 *     | nav_menu                 | transient  | 1             |
		 *     | author_list_query        | transient  | 1             |
		 *     | term_posts               | term_meta  | 1             |
		 *     +--------------------------+------------+---------------+
		 *
		 * @param $args
		 * @param $assoc_args
		 */
		public function list( $args, $assoc_args ) {

			global $dfm_transients;

			$transients = $dfm_transients;

			$this->supported_props  = array( 'key', 'hash_key', 'cache_type', 'async_updates', 'expiration', 'soft_expiration' );

			if ( empty( $dfm_transients ) ) {
				parent::error( 'Looks like you don\'t have any transients registered' );
			}

			$transient_names = ( isset( $args ) ) ? $args : array();

			if ( ! empty( $transient_names ) ) {
				$transients = array_intersect_key( $transients, array_flip( $transient_names ) );
			}

			if ( ! empty( $assoc_args ) ) {
				$filter_args = array_intersect_key( $assoc_args, array_flip( $this->supported_props ) );
				$transients = wp_list_filter( $transients, $filter_args );
			}

			if ( empty( $transients ) ) {
				parent::error( 'No transients found with this criteria' );
			}

			$this->format_output( $transients, $assoc_args );

		}

		/**
		 * Method to retrieve modifier ID's or the count of modifiers
		 *
		 * @param string   $meta_key Name of the meta key to look for
		 * @param string   $type     Meta type so we know which table to search in
		 * @param bool     $count    Whether or not we should return the total count
		 * @param bool|int $limit    Whether or not we should limit results, and if so what that limit is
		 *
		 * @return array|bool
		 */
		private function get_all_modifiers( $meta_key, $type, $count = false, $limit = false ) {

			global $wpdb;

			$object_type = substr( $type, 0, 4 );

			$table = _get_meta_table( $object_type );

			$select = ( true === $count ) ? 'count(*)' : $object_type . '_id';

			$limit = ( false !== $limit && '-1' !== $limit ) ? 'LIMIT ' . absint( $limit ) : '';

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

			return $modifiers;

		}

		/**
		 * Handles the formatting of output
		 *
		 * @param array $transients The data to display
		 * @param array $assoc_args Args so we know how to display it
		 */
		private function format_output( $transients, $assoc_args ) {

			if ( ! empty( $assoc_args['fields'] ) ) {
				if ( is_string( $assoc_args['fields'] ) ) {
					$fields = explode( ',', $assoc_args['fields'] );
				} else {
					$fields = $assoc_args['fields'];
				}
				$fields = array_intersect( $fields, $this->supported_props );
			} else {
				$fields = $this->supported_props;
			}

			$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
			$formatter->display_items( $transients );

		}

	}

	WP_CLI::add_command( 'dfm-transients', 'DFM_Transients_CLI' );

}
