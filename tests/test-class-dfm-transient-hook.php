<?php
class Test_Class_DFM_Transient_Hook extends WP_UnitTestCase {

	/**
	 * Test to make sure the hooks get setup correctly
	 */
	public function testHookAddition() {

		$transient_object = [
			'cache_type' => 'transient',
			'callback' => '__return_false',
			'key' => 'test',
		];

		$hook_obj = new DFM_Transient_Hook( 'testName', (object) $transient_object, 'testHook', true );
		$this->assertTrue( has_filter( 'testHook' ) );
	}

	/**
	 * Test to make sure a non-async update gets updated correctly
	 */
	public function testNonAsyncHandlerUpdate() {

		$transient_name = 'testNonAsyncHandlerUpdate';
		dfm_register_transient( $transient_name, [
			'expiration' => HOUR_IN_SECONDS,
			'callback' => function() {
				return 'test data';
			},
		] );

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		$hook_obj = new DFM_Transient_Hook( $transient_name, $transient_obj, 'testHook', false );

		$hook_obj->spawn();

		$this->assertEquals( 'test data', get_transient( $transient_name ) );

	}

	/**
	 * Tests to see that an update will fail if the update is locked by a different process
	 */
	public function testUpdateFailureBecauseOfLock() {

		$transient_name = 'testUpdateFailureBecauseOfLock';
		dfm_register_transient( $transient_name, [
			'callback' => function() {
				return 'test data';
			},
		] );

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		/**
		 * Lock the update manually
		 */
		set_transient( 'dfm_lt_' . $transient_name, '1234' );

		$hook_obj = new DFM_Transient_Hook( $transient_name, $transient_obj, 'testHook', false );
		$hook_obj->spawn();

		$this->assertFalse( get_transient( $transient_name ) );

	}

	/**
	 * Tests to see that a non async update with multiple modifiers gets updated correctly
	 */
	public function testNonAsyncUpdateMultipleModifiers() {

		$transient_name = 'testNonAsyncUpdateMultipleModifiers';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
		] );

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		$hook_obj = new DFM_Transient_Hook( $transient_name, $transient_obj, 'testHook', false, function() {
			return [ 1, 2, 3 ];
		} );

		$hook_obj->spawn();

		$this->assertEquals( 'modifier: 1', get_transient( $transient_name . '_1' ) );

	}

	/**
	 * Tests to make sure the update process gets short-circuited if the callback for the hook
	 * returns false
	 */
	public function testShortCircuitHookUpdate() {

		$transient_name = 'testShortCircuitHookUpdate';
		dfm_register_transient( $transient_name, [ 'callback' => '__return_true' ] );

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		$hook_obj = new DFM_Transient_Hook( $transient_name, $transient_obj, 'testHook', false, function() {
			return false;
		} );

		$hook_obj->spawn();

		$this->assertEquals( false, get_transient( $transient_name ) );

	}

	/**
	 * Tests to make sure an async update gets scheduled properly
	 */
	public function testAsyncHandlerUpdate() {

		$transient_object = [
			'cache_type' => 'transient',
			'callback' => '__return_false',
			'key' => 'test',
		];
		$hook_obj = new DFM_Transient_Hook( 'testTransient', (object) $transient_object, 'testHook', true );
		$hook_obj->spawn();

		global $wp_filter;
		$this->assertEquals( 1, count( $wp_filter['shutdown']->callbacks[10] ) );

	}

	/**
	 * Tests to make sure multiple async updated get scheduled properly
	 */
	public function testMultipleAsyncHandlerUpdates() {

		$transient_object = [
			'cache_type' => 'transient',
			'callback' => '__return_false',
			'key' => 'test',
		];
		$hook_obj = new DFM_Transient_Hook( 'testTransient', (object) $transient_object, 'testHook', true, function() {
			return [ 1, 2, 3 ];
		} );

		$hook_obj->spawn();

		global $wp_filter;
		$this->assertEquals( 3, count( $wp_filter['shutdown']->callbacks[10] ) );

	}

	/**
	 * Test that the hook handler returns the proper data to the callback for post object cache transients without modifiers
	 */
	public function testPostObjectCacheHandlerUpdate() {

		$transient_name = 'testPostObjectCacheHandlerUpdate';
		$post_id = $this->factory->post->create();
		$return_string = 'Post ID: %d, Modifier: %s';
		dfm_register_transient( $transient_name, [
			'cache_type' => 'post_meta',
			'callback' => function( $modifier, $post_id ) use ( $return_string ) {
				return sprintf( $return_string, $post_id, $modifier );
			}
		] );

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		$hook_obj = new DFM_Transient_Hook( $transient_name, (object) $transient_obj, $transient_name, false, function( $id ) {
			return $id[0];
		} );

		do_action( $transient_name, $post_id );

		$expected = sprintf( $return_string, $post_id, '' );
		$transient_obj = new DFM_Transients( $transient_name, '', $post_id );
		$actual = get_post_meta( $post_id, $transient_obj->key, true );
		$this->assertEquals( $expected, $actual );
		$this->assertEquals( [], DFM_Transients::get_meta_map( 'post', $post_id, $transient_name ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'dfm_transient_' . $transient_name . '_map' ) );

	}

	/**
	 * Test that all transients within a transient group are refreshed when just the object ID is returned from the hook callback
	 */
	public function testTermObjectCacheHandlerWithModifiers() {

		$transient_name = 'testTermObjectCacheHandlerWithModifiers';
		$term_id = $this->factory->category->create();
		$return_string = 'Term ID: %d, Modifier: %s';

		dfm_register_transient( $transient_name, [
			'cache_type' => 'term_meta',
			'callback' => function( $modifier, $term_id ) use ( $return_string ) {
				return sprintf( $return_string, $term_id, $modifier );
			},
			'hash_key' => true,
		] );

		$modifiers = [
			'rainbow',
			'apples',
			'unicorn',
		];

		/**
		 * Add some data to start out with, so we get a map.
		 */
		foreach ( $modifiers as $modifier ) {
			$transient_data = dfm_get_transient( $transient_name, $modifier, $term_id );
			$this->assertEquals( sprintf( $return_string, $term_id, $modifier ), $transient_data );
		}

		$transient_obj = new DFM_Transients( $transient_name, $modifiers[0], $term_id );
		$transient_obj->delete();

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		$hook_obj = new DFM_Transient_Hook( $transient_name, (object) $transient_obj, $transient_name, false, function( $term_id ) {
			return $term_id[0];
		} );

		do_action( $transient_name, $term_id );

		$transient_keys = DFM_Transients::get_meta_map( 'term', $term_id, $transient_obj->key );
		$this->assertEquals( 3, count( $transient_keys ) );

		foreach ( $transient_keys as $modifier => $key ) {
			$this->assertEquals( sprintf( $return_string, $term_id, $modifier ), get_term_meta( $term_id, $key, true ) );
		}

	}

	/**
	 * Test that all the transients on a user type cache are cleared only when the modifiers for the
	 * object ID are passed back in the callback, or if it returns an empty array for the ID.
	 */
	public function testUserObjectCacheHandlerWithModifiers() {

		$transient_name = 'testUserObjectCacheHandlerWithModifiers';
		$users = [
			$this->factory->user->create(),
			$this->factory->user->create(),
			$this->factory->user->create(),
			$this->factory->user->create(),
		];
		$return_string = 'User ID: %d, Modifier: %s';

		dfm_register_transient( $transient_name, [
			'cache_type' => 'user_meta',
			'callback' => function( $modifier, $user_id ) use ( $return_string ){
				return sprintf( $return_string, $user_id, $modifier );
			}
		] );

		$modifiers = [
			'blue',
			'purple',
			'test',
		];

		foreach ( $users as $user ) {
			foreach( $modifiers as $modifier ) {
				dfm_set_transient( $transient_name, 'test', $modifier, $user );
			}
		}

		global $dfm_transients;
		$transient_obj = $dfm_transients[ $transient_name ];

		$hook_obj = new DFM_Transient_Hook( $transient_name, (object) $transient_obj, $transient_name, false, function( $data ) {
			return $data[0];
		} );

		$hook_data = [
			$users[0] => $modifiers,
			$users[1] => $modifiers,
			$users[2] => array_slice( $modifiers, 0, 2 ),
			$users[3] => [],
		];

		do_action( $transient_name, $hook_data );

		$transient_keys = [];
		foreach ( $users as $user ) {
			$transient_keys[ $user ] = DFM_Transients::get_meta_map( 'user', $user, $transient_name );
		}

		foreach ( $transient_keys as $user_id => $keys ) {
			$hook_modifiers = $hook_data[ $user_id ];
			if ( is_array( $hook_modifiers ) && ! empty( $hook_modifiers ) ) {
				foreach ( $hook_modifiers as $modifier ) {
					$this->assertEquals( sprintf( $return_string, $user_id, $modifier ), get_user_meta( $user_id, $keys[ $modifier ], true ) );
				}
			} else {
				foreach ( $modifiers as $modifier ) {
					$this->assertEquals( sprintf( $return_string, $user_id, $modifier ), get_user_meta( $user_id, $keys[ $modifier ], true ) );
				}
			}
		}

		// Test to make sure the "test" modifier on the second to last user isn't updated since we didn't pass it to the hook in the $hook_data var
		$this->assertEquals( 'test', get_user_meta( $users[2], $transient_keys[ $users[2] ]['test'], true ) );

	}

}
