# DFM-Transients
Transient management utilities for WP CMS

This WordPress plugin provides utilities to expand upon the built in WordPress Transients API. The focus of this library is to make it easier to get & set transients, as well as introduce asynchronous update abilities for regenerating data to be stored in transients.

## Sample Code for registering a transient
Below is a sample of what it would look like to register an actual transient. The pattern is similar to what you would do for registering a post type, or a taxonomy. The first parameter passed to `dfm_register_transient()` is the name of the transient, and the second is the array of arguments for registering the transient. For a full list of arguments you can pass to this function, and what they do, view the list below.
```php
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
```php
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
```php
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
## Retrieve the transient data
You can use the `dfm_get_transient()` function to retrieve the data for a transient. The first parameter passed to this function is the name of the transient you are trying to retrieve (should be the same name that you registered the transient with). The second parameter is the "modifier" for the transient. You can read more about modifiers below. The third parameter is the object_id (if you are using post_meta, term_meta, or user_meta as the cache type).
```php
$result = dfm_get_transient( 'sample_transient' );
```
```php
$post_transient = dfm_get_transient( 'sample_transient', '', $post_id );
```

## Arguments for registering a transient
1. **key** (string) - The unique name of the transient that will be used to set the storage key. By default, this will use the transient name (passed as the first parameter to `dfm_register_transient` . If this needs to be overridden for some reason, use this argument. *Default: transient name*
2. **hash_key** (bool) - Set to true if you want to MD5 hash your storage key. If you are using *_meta for the storage engine, the prefix will not be hashed. *Default: False*
3. **cache_type** (string) - The cache engine you would like to use for this transient. Defines where in the database where the actual transient should be stored. *Default: transient*. Options are:
	- transient - Stored as a normal transient in the options table
	- post_meta - Stored as post meta
	- term_meta - Stored as term meta
	- user_meta - stored as user meta
4. **callback** (string|array|callable) - The callback for the transient, this is the heart and soul of the framework. Here you can point to a callback function to be run to re-populate the data stored in the transient. The function can return any data that can be stored in a native transient or option. The function is passed the `$modifier` for the transient. For a transient using a `*_meta` storage engine, the `$modifier` will be the object ID the meta is attached to. Otherwise it will be any piece of data that makes a global transient unique (more info on this below). Good to note is that this callback argument **must** be used. You will not be able to register a transient without it.
5. **async_updates** (bool) - Set to true for your transients to update asynchronously. This only applies to updates that come from actions registered through the `update_hooks` argument. For async updates on transient expiration see `soft_expiration` *Default: False*
6. **update_hooks** (array|bool|string) - Defines which hooks we should hook into to regenerate the transient data. You can pass a single hook to this argument as a string if you just want to regenerate the data whenever a certain hook fires. You can also pass an array of hooks if you want the transient data to be regenerated for multiple hooks. When passing as an array, you can also pass a callback that decides if the data regeneration should actually run on this hook. This is helpful when using generic hooks like `updated_post_meta`. For example, the `updated_post_meta` hook runs whenever any piece of post meta is updated, but you may only want to update your transient data when a certain piece of post meta is updated. The callback is passed all of the arguments from the hook as an array using `func_get_args()`, so you can compare against the arguments. The callback should return `false` if the regeneration should not run, and should return the `$modifier` to be passed to the regeneration callback if it should run. You can also return an array of modifiers from your callback, so you can regenerate data for multiple transients within the same transient group. Each of the hooks can have their own callback, if you have multiple. It will look something like this: `array( 'my_hook' => 'callback' );`. Essentially in the array of update hooks, the key is the name of the hook to fire on, and the value is the name of your callback function. *Default: False*
7. **expiration** (bool|int) - When the transient should expire. This works exactly how it works with normal transients, so you can pass something like `HOUR_IN_SECONDS` here. *Default: False*
8. **soft_expiration** (bool) - Whether or not the data should soft expire or not. If this is set to true, it will check to see if the data has expired when retrieving it. If it is expired, it will spawn a background process to update the transient data. *Default: False*

## Transient Modifier
The Transient modifier (second parameter passed to the `dfm_get_transient` function) is used to store variations of the same type of transient. It will append the $modifier to the end of the transient key. This way you could store and retrieve different variations of the same transient that are mostly the same without registering a whole new transient. You can use the modifier to change the data saved to the transient by using it to alter your logic in your callback (the modifier is passed as the first argument to your callback function).

The transient modifier, (second parameter passed to the `dfm_get_transient` function) is used in a variety of different ways throughout this library. For a transient stored in metadata, it will be used as the object ID the transient is attached to. It will be used when using the `get_metadata()` and `save_metadata()` functions, so it is crucial that it is passed for transients stored in metadata. For global transients, it can be used to store variations of the same type of transient. It will append the `$modifier` to the end of the transient key. This way you could store and retrieve different variations of the same transient that are mostly the same without registering a whole new transient. You can use the modifier to change the data saved to the transient by using it to alter your logic in your callback (the modifier is passed as the only argument to your callback function).

## Debugging
To help with debugging, you can set a constant in your codebase called `DFM_TRANSIENTS_HOT_RELOAD` and set it to `true` to enable "hot reload" mode. This will essentially make it so that transient data will be regenerated every time it is called. This is handy if you are working on adding a transient, and want it to keep regenerating while you are working on it. This saves the need from manually deleting it from your database, or setting an extremely short timeout. **NOTE:** This constant should only ever be used on a development environment. Using this on production could cause serious performance issues depending on the data you are storing in your transients.

## Retries
Since version 1.1.0 there is a retry facilitation system for DFM Transients. This is helpful if you are storing data from an external API, and want to serve stale data if the API is down. To use this feature, all you have to do is return `false` or a `wp_error` object in your transient callback if your remote request failed. This will then store the stale expired data back into the transient, and will use an expiration timeout that increases exponentially every time it fails to fetch the data. Essentially it stores a `failed` value in the cache for each transient, and adds one to the value every time the retry method runs. It then mulitplies this number by its self to figure out how many minutes it should set the expiration to. For example, if the fetch has failed 5 times, it will set the timeout to 25 minutes, and will retry again after that.

## CLI Commands
DFM Transients comes with a full list of CLI commands to control transients from the command line. Below are a few sample commands to get you started.

List all registered transients:
```bash
$ wp dfm-transients list

+----------------------------+----------+------------+---------------+------------+-----------------+
| key                        | hash_key | cache_type | async_updates | expiration | soft_expiration |
+----------------------------+----------+------------+---------------+------------+-----------------+
| dfm_instagram_api_feed     |          | transient  |               | 3600       | 1               |
| dfm_current_standout_count |          | transient  |               | 3600       | 1               |
| author_featured_articles   |          | post_meta  | 1             |            |                 |
| nav_menu                   |          | transient  | 1             |            |                 |
+----------------------------+----------+------------+---------------+------------+-----------------+
```
You can also get a list of information on just a few registered transients, and return only the info you need with the `--fields` flag
```bash
$ wp dfm-transients list dfm_current_standout_count author_featured_articles --fields=key,cache_type,async_updates

+----------------------------+------------+---------------+
| key                        | cache_type | async_updates |
+----------------------------+------------+---------------+
| dfm_current_standout_count | transient  |               |
| author_featured_articles   | post_meta  | 1             |
+----------------------------+------------+---------------+
```
If you want a list of registered transients that all use async_updated you can run something like the following:
```bash
$wp dfm-transients list --async_updates=1 --fields=key,cache_type,async_updates

+--------------------------+------------+---------------+
| key                      | cache_type | async_updates |
+--------------------------+------------+---------------+
| author_featured_articles | post_meta  | 1             |
| nav_menu                 | transient  | 1             |
| author_list_query        | transient  | 1             |
| term_posts               | term_meta  | 1             |
+--------------------------+------------+---------------+
```
You can also retrieve the data from your transients from the command line:
```bash
$wp dfm-transients get term_posts all

+----------+------------------------------------------------------------------------------------------------------------------+
| modifier | data                                                                                                             |
+----------+------------------------------------------------------------------------------------------------------------------+
| 7681     | [2187069,2189051,2188653,2188525,2188689,2188302,2188435,2188561,2188519,2188486,2188180,2188052,2188378,2187293 |
|          | ,2187831,2185712,2186525,2186643,2186373,2186221]                                                                |
| 4687     | [2188121,2187831,2187084,2185789,2185208,2185126,2185123,2185003,2183357,2183326,2183276,2183089,2183081,2183060 |
|          | ,2182991,2181531,2180932,2180486,2177749,2179168]                                                                |
| 1797     | [2186592,2177875,2170239,2162981,2155404,2148740]                                                                |
| 8732     | [2188653,2185461,2183384,2182193,2178326,2175819,2174603,2170396,2168493,2167170,2163516,2160893,2158586,2156073 |
|          | ,2154094,2151996,2149292,2147779,2147211,2147178]                                                                |
+----------+------------------------------------------------------------------------------------------------------------------+
```
You can also retrieve only the modifiers for the transients, which becomes useful with the delete and set commands
```bash
$wp dfm-transients get term_posts all --fields=modifier --format=ids

97 7681 4687 1797 8732 8624 98 9372 7682 48 94 75 66 30 15 7629 40 53 59 36
```
For modifying the data stored in a transient, you can use the `set` command like so:
```bash
$wp dfm-transients set my_transient --data="test"
Successfully updated the my_transient transient
```
You can also combine this with the `get` command to update multiple transients within a group at once
```bash
$wp dfm-transients set term_posts $(wp dfm-transients get term_posts --fields=modifier --format=ids) --data="test"
Updating Transients  100% [=============================================] 0:00 / 0:00
Success: Successfully updated 20 transients
```
Similar to the `set` command, you can use the `delete` command to delete the record for the transient. This is useful for when you want to force the system to regenerate the data stored in a particular transient.
```bash
$wp dfm-transients delete my_transient
Success: Successfully deleted transient: my_transient
```
You can also combine this command with the `get` command to delete multiple transients at the same time
```bash
$wp dfm-transients delete term_posts $(wp dfm-transients get term_posts --fields=modifier --format=ids)
Deleting transients  100% [=============================================] 0:00 / 0:00
Success: Successfully deleted 20 transients
```

## Contributing
To contribute to this repo, please fork it and submit a pull request. If there is a larger feature you would like to see, or something you would like to discuss, please open an issue.
## Copyright
© Media News Group 2016
## Attribution
Props to the following projects for giving me ideas / code.
- https://github.com/techcrunch/wp-async-task
- https://github.com/markjaquith/WP-TLC-Transients
- https://github.com/pippinsplugins/Transients-Manager

## License
This library is licensed under the [MIT](http://opensource.org/licenses/MIT) license. See LICENSE.md for more details.
