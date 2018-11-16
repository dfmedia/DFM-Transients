<?php

if ( ! class_exists( 'DFM_Transient_Utils' ) ) {
	class DFM_Transient_Utils {

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
