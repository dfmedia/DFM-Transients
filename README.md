# DFM-Transients
Transient management utilities for WP CMS

This WordPress plugin provides utilities to expand upon the built in WordPress Transients API.

## Sample Code for registering a transient
```
function dfm_register_sample_transient() {

  $transient_args = array(
    'cache_type' => 'transient',
    'callback' => 'dfm_transient_callback',
    'expiration' => DAY_IN_SECONDS,
    'soft_expiration' => true,
    'update_hooks' => array(
      'updated_post_meta' => 'dfm_transient_meta_update_cb',
    ),
  );

   dfm_register_transient( 'sample_transient', $transient_args );

}

add_action( 'after_setup_theme', 'dfm_register_sample_transient' );
```
## Sample transient callback
This callback function would pair with the transient registered above.
```
dfm_transient_callback( $modifier ) {
  $args = array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 5,
    'tax_query' => array(
      array(
        'taxonomy' => 'category',
        'terms' => $modifier,
      ),
    ),
  );
  $posts = new WP_Query( $args );
  return $posts;
}
```
## Sample update callback
This function would pair with the transient registered above. It decides if we should actually run the callback to regenerate the transient data on this hook.
```
dfm_transient_meta_update_cb( $args ) {
  // Matches $meta_key value (3rd arg passed to hook)
  if ( 'my_meta_key' === $args[2] ) {
    // Returns post ID
    return $args[1]
  } else {
    // If this callback returns false, we will not regenerate the transient data.
    return false;
}
```
