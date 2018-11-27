<?php

if ( ! class_exists( 'DFM_Transient_Utils' ) ) {

	/**
	 * Class DFM_Transient_Utils
	 * Helper methods for the DFM_Transients plugin
	 */
	class DFM_Transient_Utils {

		/**
		 * Return the *_metadata() cache type translated from the registered `cache_type`.
		 *
		 * @param string $cache_type Name of the cache type to translate to the friendlier version
		 *
		 * @return string|WP_Error
		 */
		public static function get_meta_type( $cache_type ) {

			switch ( $cache_type ) {
				case 'post_meta' :
					$meta_type = 'post';
					break;
				case 'term_meta' :
					$meta_type = 'term';
					break;
				case 'user_meta';
					$meta_type = 'user';
					break;
				default:
					return new WP_Error( 'invalid-cache-type', __( 'When registering your transient, you used an invalid cache type. Valid options are transient, post_meta, term_meta.', 'dfm-transients' ) );
			}

			return $meta_type;

		}
	}
}
