<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Dfm_Transients
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/dfm-transients.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

if ( ! defined( 'DFM_TRANSIENTS_SECRET' ) ) {
	define( 'DFM_TRANSIENTS_SECRET', 'pizzapizzapizza' );
}

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
