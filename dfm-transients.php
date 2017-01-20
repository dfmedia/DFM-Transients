<?php
/**
* Plugin Name: Transient Control
* Plugin URI: https://github.com/dfmedia/DFM-Transients
* Description: Better control for transients
* Version: 1.0.1
* Author: Ryan Kanner, Digital First Media
* License: MIT
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dfm-async-nonce.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dfm-async-handler.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dfm-transient-hook.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dfm-transient-scheduler.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dfm-transients.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/template-tags.php' );

// Admin only files
if ( is_admin() ) {
	require_once( plugin_dir_path( __FILE__ ) . 'includes/admin/class-dfm-transient-admin.php' );
}
