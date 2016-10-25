<?php

/**
 * dfm_register_transient
 *
 * Handles the registration of the transient
 *
 * @access public
 * @return mixed|WP_Error|void
 * @param string $transient
 * @param array $args {
 * 		Optional. Array of arguments to pass when registering.
 *
 * 		@type string        $key             Name of the key to use for the key => value storage
 * 		@type bool          $hash_key        Whether or not we should hash the key for storage
 * 		@type string        $cache_type      Which storage engine we should use. Options are: transient, post_meta, user_meta, or term_meta
 * 		@type bool|callable $callback        Provide callback for updating / populating the data to be stored
 * 		@type string        $parent_cache    Name of the parent cache that should also be updated when this cache is updated
 * 		@type bool          $async_updates   Whether or not we should update this cache asynchronously or not
 * 		@type bool|array    $update_hooks    Name of the hook where the update function should fire {
 *
 * 			Array of hook names that we should update our transients on. Should look like the following:
 * 			'hook_name' => 'callback'
 * 			All of the arguments from the action are passed to the callback as an array in the order they are passed.
 * 			If the criteria to clear the transient data is not met, your callback should return false, otherwise it
 * 			should return the unique "modifier" for the transient.
 *
 * 		}
 * 		@type string        $expiration      Unix timestamp of when the data should expire
 * 		@type bool          $soft_expiration Whether or not we should do a soft expiration on the data
 * }
 */
function dfm_register_transient( $transient, $args = array() ) {

	global $dfm_transients;

	if ( ! is_array( $dfm_transients ) ) {
		$dfm_transients = array();
	}

	if ( empty( $args['callback'] ) ) {
		return new WP_Error( 'transient-callback-required', __( 'You must add a callback when registering a transient.', 'dfm-transients' ) );
	}

	$default_args = array(
		'key'             => $transient,
		'hash_key'        => false,
		'cache_type'      => 'transient',
		'callback'        => false,
		'async_updates'   => false,
		'update_hooks'    => false,
		'expiration'      => false,
		'soft_expiration' => false,
	);

	$args = wp_parse_args( $args, $default_args );

	// Type set to object to stay consistent with other WP globally registered objects such as taxonomies.
	$dfm_transients[ $transient ] = (object) $args;

}

/**
 * dfm_get_transient
 *
 * Retrieves the data from the transient
 *
 * @param string $transient The name of the transient that we would like to retrieve.
 * @param string|int $modifier The unique modifier for the transient. This is also used for term ID, post ID, or user ID when
 * 		  the storage engine is post_meta, term_meta, or user_meta
 *
 * @return mixed|WP_Error|array|string
 */
function dfm_get_transient( $transient, $modifier ) {
	$transients = new DFM_Transients( $transient, $modifier );
	return $transients->get();
}
