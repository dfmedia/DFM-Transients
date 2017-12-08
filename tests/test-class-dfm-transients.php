<?php


class Test_Class_DFM_Transients extends WP_UnitTestCase {

	/**
	 * Test that an exception gets thrown for trying to retrieve a transient that doesn't exist
	 */
	public function testNonExistentTransient() {

		$this->setExpectedException( 'Exception', 'You are trying to retrieve a transient that doesn\'t exist' );
		$this->expectException( new DFM_Transients( 'NonExistentTransient', '' ) );

	}

	/**
	 * Tests that the update locking feature works as expected
	 */
	public function testLockUpdate() {

		$transient_name = 'testLockUpdate';

		$this->register_transient( $transient_name );
		$transient_obj = new DFM_Transients( $transient_name, '' );

		$expected = $transient_obj->lock_key;

		$transient_obj->lock_update();

		$actual = get_transient( 'dfm_lt_' . $transient_obj->key );

		$this->assertEquals( $expected, $actual );
		$this->assertEquals( true, $transient_obj->is_locked() );
		$this->assertEquals( true, $transient_obj->owns_lock( $actual ) );

		$transient_obj->unlock_update();
		$expected = false;
		$actual = get_transient( 'dfm_lt_' . $transient_obj->key );;

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Test that a random string cannot be passed to indicate lock ownership
	 */
	public function testNotOwnsLock() {

		$transient_name = 'testNotOwnsLock';
		$this->register_transient( $transient_name );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$this->assertEquals( false, $transient_obj->owns_lock( 'test' ) );

	}

	/**
	 * Tests that the transient key gets generated correctly with and without a modifier
	 */
	public function testTransientKey() {

		$method = $this->reflect_method( 'cache_key' );

		$transient_name = 'testTransientKey';
		$this->register_transient( $transient_name );

		$transient_obj = new DFM_Transients( $transient_name, '' );
		$this->assertEquals( $transient_name, $method->invoke( $transient_obj ) );

		$modifier = 'test';
		$transient_obj_with_modifier = new DFM_Transients( $transient_name, $modifier );
		$this->assertEquals( $transient_name . '_' . $modifier, $method->invoke( $transient_obj_with_modifier ) );

	}

	/**
	 * Tests the retry method
	 */
	public function testTransientRetryMethod() {

		$transient_name = 'testTransientRetryMethod';
		$this->register_transient( $transient_name, [
			'callback' => function() {
				return 'test value';
			},
			'expiration' => HOUR_IN_SECONDS,
			'soft_expiration' => true,
		] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $transient_obj->get();
		$expected = 'test value';
		$this->assertEquals( $expected, $actual );

		$transient_obj->set( false );
		$failed_num = wp_cache_get( $transient_obj->key . '_failed', 'dfm_transients_retry' );
		$this->assertEquals( 1, $failed_num );

		$this->assertEquals( $expected, $transient_obj->get() );

		$transient_obj->set( new WP_Error( 'sample-error', 'something happened' ) );
		$this->assertEquals( $expected, $transient_obj->get() );
		$failed_num = wp_cache_get( $transient_obj->key . '_failed', 'dfm_transients_retry' );
		$this->assertEquals( 2, $failed_num );

		$transient_data = get_transient( $transient_obj->key );
		$this->assertEquals( ( 1 * MINUTE_IN_SECONDS ) + time(), $transient_data['expiration'] );

	}

	/**
	 * Tests to make sure the transient key for meta transients gets generated correctly
	 */
	public function testMetaTransientKey() {

		$method = $this->reflect_method( 'cache_key' );

		$transient_name = 'testMetaTransientKey';
		$this->register_transient( $transient_name, [ 'cache_type' => 'post_meta' ] );
		$this->register_transient( $transient_name . 'Hashed', [ 'cache_type' => 'post_meta', 'hash_key' => true ] );

		$transient_obj = new DFM_Transients( $transient_name, 1 );
		$this->assertEquals( 'dfm_transient_' . $transient_name, $method->invoke( $transient_obj ) );

		$transient_obj_hashed = new DFM_Transients( $transient_name . 'Hashed', 1 );
		$this->assertEquals( 'dfm_transient_' . md5( $transient_name . 'Hashed' ), $method->invoke( $transient_obj_hashed ) );

	}

	/**
	 * Tests to make sure key hashing is working correctly
	 */
	public function testShouldHashKey() {

		$should_hash_method = $this->reflect_method( 'should_hash' );
		$get_key_method = $this->reflect_method( 'cache_key' );

		$transient_name = 'TestTransientHashKey';
		$this->register_transient( $transient_name, [ 'hash_key' => true ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$should_hash_actual = $should_hash_method->invoke( $transient_obj );

		$this->assertTrue( $should_hash_actual );
		$this->assertEquals( md5( $transient_name ), $get_key_method->invoke( $transient_obj ) );

		$transient_obj_modifier = new DFM_Transients( $transient_name, 'test_modifier' );
		$this->assertEquals( md5( $transient_name . '_test_modifier' ), $get_key_method->invoke( $transient_obj_modifier ) );

	}

	/**
	 * Tests to make sure key hashing is not happening outside of the scope it is supposed to
	 */
	public function testShouldNotHashKey() {

		$should_hash_method = $this->reflect_method( 'should_hash' );
		$get_key_method = $this->reflect_method( 'cache_key' );

		$transient_name = 'TestTransientNotHashKey';
		$this->register_transient( $transient_name, [ 'hash_key' => false ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$should_hash_actual = $should_hash_method->invoke( $transient_obj );

		$this->assertFalse( $should_hash_actual );
		$this->assertEquals( $transient_name, $get_key_method->invoke( $transient_obj ) );

		$transient_obj_modifier = new DFM_Transients( $transient_name, 'test_modifier' );
		$this->assertEquals( $transient_name . '_test_modifier', $get_key_method->invoke( $transient_obj_modifier ) );

	}

	/**
	 * Tests to make sure the should_expire method is working properly
	 */
	public function testShouldExpire() {

		$method = $this->reflect_method( 'should_expire' );

		$transient_name = 'TestTransientExpiration';
		$this->register_transient( $transient_name, [ 'expiration' => HOUR_IN_SECONDS ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertTrue( $actual );

	}

	/**
	 * Tests to make sure the should_expire method is not overreaching it's intended scope
	 */
	public function testShouldNotExpire() {

		$method = $this->reflect_method( 'should_expire' );

		$transient_name = 'TestTransientNotExpiration';
		$this->register_transient( $transient_name, [ 'expiration' => false ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertFalse( $actual );

	}

	/**
	 * Tests to make sure the should_soft_expire method is working as intended
	 */
	public function testShouldSoftExpire() {

		$method = $this->reflect_method( 'should_soft_expire' );

		$transient_name = 'TestSoftExpireTransient';
		$this->register_transient( $transient_name, [ 'soft_expiration' => true ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertTrue( $actual );

	}

	/**
	 * Tests to make sure the should_soft_expire method is not overreaching it's intended scope
	 */
	public function testShouldNotSoftExpire() {

		$method = $this->reflect_method( 'should_soft_expire' );

		$transient_name = 'TestNotSoftExpireTransient';
		$this->register_transient( $transient_name, [ 'soft_expiration' => false ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertFalse( $actual );

	}

	/**
	 * Test to make sure an actual expired transient registers as expired with the is_expired method
	 */
	public function testTransientIsExpired() {

		$method = $this->reflect_method( 'is_expired' );

		$transient_name = 'TestExpiredTransient';
		$this->register_transient( $transient_name, [ 'expiration' => 1 ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj, [ 'expiration' => time() - 1, 'data' => 'test' ] );

		$this->assertTrue( $actual );

	}

	/**
	 * Test to make sure an actual non-expired transient does not register as expired in the
	 * is_expired method
	 */
	public function testTransientNotExpired() {

		$method = $this->reflect_method( 'is_expired' );

		$transient_name = 'TestNotExpiredTransient';
		$this->register_transient( $transient_name, [ 'expiration' => DAY_IN_SECONDS ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj, [ 'expiration' => time(), 'data' => 'test' ] );

		$this->assertFalse( $actual );

	}

	/**
	 * Test to retrieve a transient that has a modifier
	 */
	public function testGetTransientWithModifier() {

		$transient_name = 'testGetTransientWithModifier';
		$this->register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return $modifier . ' test value';
			},
			'cache_type' => 'transient',
		] );

		$transient_obj = new DFM_Transients( $transient_name, 'something' );
		$key = $transient_obj->key;

		$actual = $transient_obj->get();
		$expected = 'something test value';

		$this->assertEquals( $actual, $expected );

		$transient_obj->set( 'another test' );
		$this->assertEquals( 'another test', $transient_obj->get() );

		$transient_obj->delete();
		$this->assertFalse( get_transient( $key ) );
		$this->assertEquals( $expected, $transient_obj->get() );

	}

	/**
	 * Test to make sure retrieving a transient with a soft expiration works correctly
	 */
	public function testGetTransientWithSoftExpire() {

		$transient_name = 'testGetTransientWithSoftExpire';
		$this->register_transient( $transient_name, [
			'soft_expiration' => true,
			'expiration' => DAY_IN_SECONDS,
			'callback' => function() {
				return 'some string';
			},
		] );

		$transient_obj = new DFM_Transients( $transient_name, '' );
		$key = $transient_obj->key;

		$expected = 'some string';
		$actual = $transient_obj->get();
		$this->assertEquals( $expected, $actual );
		$this->assertArrayHasKey( 'data', get_transient( $key ) );
		$this->assertArrayHasKey( 'expiration', get_transient( $key ) );

		$transient_obj->set( 'some other string' );
		$this->assertEquals( 'some other string', $transient_obj->get() );

		$transient_obj->delete();
		$this->assertEquals( $expected, $transient_obj->get() );

	}

	/**
	 * Test to make sure getting transient data from term meta works correctly
	 */
	public function testGetTransientFromTermMeta() {

		$term_arr = wp_insert_term( 'Test Term', 'category' );
		$term_id = (int) $term_arr['term_id'];

		$transient_name = 'testGetTransientFromTermMeta';
		$this->register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'term id: ' . $modifier;
			},
			'cache_type' => 'term_meta',
		] );

		$transient_obj = new DFM_Transients( $transient_name, $term_id );
		$key = $transient_obj->key;

		$actual = $transient_obj->get();
		$expected = 'term id: ' . $term_id;

		$this->assertEquals( $expected, $actual );

		$transient_obj->set( 'some test value' );
		$this->assertEquals( 'some test value', $transient_obj->get() );

		$transient_obj->delete();
		$this->assertEquals( '', get_term_meta( $term_id, $key, true ) );
		$this->assertEquals( $expected, $transient_obj->get() );
		$this->assertEquals( $expected, get_term_meta( $term_id, $key, true ) );

	}

	/**
	 * Test to make sure getting transient data from post meta works correctly
	 */
	public function testGetTransientFromPostMeta() {

		$post_id = $this->factory->post->create();
		$transient_name = 'testGetTransientFromPostMeta';

		$this->register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'post id: ' . $modifier;
			},
			'cache_type' => 'post_meta',
		] );

		$transient_obj = new DFM_Transients( $transient_name, $post_id );
		$key = $transient_obj->key;

		$actual = $transient_obj->get();
		$expected = 'post id: ' . $post_id;
		$this->assertEquals( $expected, $actual );

		$transient_obj->set( 'some test value' );
		$this->assertEquals( 'some test value', $transient_obj->get() );

		$transient_obj->delete();
		$this->assertEquals( '', get_post_meta( $post_id, $key, true ) );
		$this->assertEquals( $expected, $transient_obj->get() );
		$this->assertEquals( $expected, get_post_meta( $post_id, $key, true ) );

	}

	/**
	 * Test to make sure getting transient data from meta with an expiration works correctly
	 */
	public function testGetTransientFromMetaWithExpiration() {

		$post_id = $this->factory->post->create();
		$transient_name = 'testGetTransientFromMetaWithExpiration';

		$this->register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'post id: ' . $modifier;
			},
			'cache_type' => 'post_meta',
			'expiration' => DAY_IN_SECONDS,
			'soft_expiration' => true,
			'hash_key' => true,
		] );

		$transient_obj = new DFM_Transients( $transient_name, $post_id );
		$key = $transient_obj->key;

		$actual = $transient_obj->get();
		$expected = 'post id: ' . $post_id;
		$this->assertEquals( $expected, $actual );

		$transient_obj->set( 'some test value' );
		$this->assertEquals( 'some test value', $transient_obj->get() );
		$this->assertArrayHasKey( 'data', get_post_meta( $post_id, $key, true ) );
		$this->assertArrayHasKey( 'expiration', get_post_meta( $post_id, $key, true ) );

		$transient_obj->delete();
		$this->assertEquals( '', get_post_meta( $post_id, $key, true ) );
		$this->assertEquals( $expected, $transient_obj->get() );
		$this->assertEquals( $expected, get_post_meta( $post_id, $key, true )['data'] );

	}

	/**
	 * Test to make sure getting transient data from user meta works correctly
	 */
	public function testGetTransientFromUserMeta() {

		$user_id = $this->factory->user->create();
		$transient_name = 'testGetTransientFromUserMeta';

		$this->register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'user id: ' . $modifier;
			},
			'cache_type' => 'user_meta',
		] );

		$transient_obj = new DFM_Transients( $transient_name, $user_id );
		$key = $transient_obj->key;

		$actual = $transient_obj->get();
		$expected = 'user id: ' . $user_id;
		$this->assertEquals( $actual, $expected );

		$transient_obj->set( 'some test value' );
		$this->assertEquals( 'some test value', $transient_obj->get() );

		$transient_obj->delete();
		$this->assertEquals( '', get_user_meta( $user_id, $key, true ) );
		$this->assertEquals( $expected, $transient_obj->get() );
		$this->assertEquals( $expected, get_user_meta( $user_id, $key, true ) );

	}

	/**
	 * Test for retrieving a transient while the code is doing a retry
	 */
	public function testGetTransientDataDuringRetry() {

		$transient_name = 'testGetTransientDataDuringRetry';
		$this->register_transient( $transient_name );

		$transient_obj = $this->getMockBuilder( 'DFM_Transients' )
			->setConstructorArgs( [ $transient_name, '' ] )
			->setMethods( [ 'doing_retry' ] )
			->getMock();

		$transient_obj->expects( $this->any() )
			->method( 'doing_retry' )
			->will( $this->returnValue( true ) );

		$actual = $transient_obj->get();
		$this->assertFalse( $actual );

	}

	/**
	 * Test to make sure getting transient data from a transient cache with a soft expiration
	 * works correctly
	 */
	public function testUpdateForSoftExpiredData() {

		$transient_name = 'testUpdateForSoftExpiredData';
		$this->register_transient( $transient_name, [
			'soft_expiration' => true,
			'expiration' => HOUR_IN_SECONDS,
			'callback' => function() {
				return 'test data';
			}
		] );

		$transient_obj = $this->getMockBuilder( 'DFM_Transients' )
			->setConstructorArgs( [ $transient_name, '' ] )
			->setMethods( [ 'is_expired' ] )
			->getMock();

		$transient_obj->expects( $this->any() )
			->method( 'is_expired' )
			->will( $this->returnValue( true ) );

		$expected = 'some string';
		$transient_obj->set( $expected );
		$actual = $transient_obj->get();
		$this->assertEquals( $expected, $actual );

		/**
		 * Removing this core hook so we can better tell if our async handler is actually getting
		 * added to the shutdown hook
		 */
		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
		$this->assertTrue( has_filter( 'shutdown' ) );

	}

	/**
	 * Tests to make sure data with a hard expiration gets regenerated properly
	 */
	public function testUpdateForHardExpiredData() {

		$transient_name = 'testUpdateForHardExpiredData';
		$this->register_transient( $transient_name, [
			'expiration' => HOUR_IN_SECONDS,
			'callback' => function() {
				return 'test data';
			}
		] );

		$transient_obj = $this->getMockBuilder( 'DFM_Transients' )
			->setConstructorArgs( [ $transient_name, '' ] )
			->setMethods( [ 'is_expired' ] )
			->getMock();

		$transient_obj->expects( $this->any() )
			->method( 'is_expired' )
			->will( $this->returnValue( true ) );

		$expected = 'test data';
		$transient_obj->set( 'some other data' );
		$actual = $transient_obj->get();

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Tests to make sure if we try to get data from a transient that was registered with a
	 * non-existent type it throws an error
	 */
	public function testGetTransientFromNonExistentType() {

		$transient_name = 'testGetTransientFromNonExistentType';
		$this->register_transient( $transient_name, [ 'cache_type' => 'none' ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$this->assertTrue( is_wp_error( $transient_obj->get() ) );

		$this->setExpectedException( 'Exception', 'When registering your transient, you used an invalid cache type. Valid options are transient, post_meta, term_meta.' );
		$this->expectException( $transient_obj->set( 'test' ) );


	}

	/**
	 * Tests to make sure if we try to get delete data from a transient that was registered with a
	 * non-existent type it throws an error
	 */
	public function testDeleteTransientFromNonExistentType() {

		$transient_name = 'testDeleteTransientFromNonExistentType';
		$this->register_transient( $transient_name, [ 'cache_type' => 'none' ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$this->setExpectedException( 'Exception', 'When registering your transient, you used an invalid cache type. Valid options are transient, post_meta, term_meta.' );
		$this->expectException( $transient_obj->delete() );

	}

	/**
	 * Helper method to reflect a method within a class
	 *
	 * @param string $method_name Name of the method you want to reflect
	 * @return ReflectionMethod
	 */
	private function reflect_method( $method_name ) {

		$method = new ReflectionMethod( 'DFM_Transients', $method_name );
		$method->setAccessible( true );

		return $method;

	}

	/**
	 * Helper function to register a transient
	 *
	 * @param string $name Name of the transient to register
	 * @param array  $args Arguments to register for the transient
	 */
	private function register_transient( $name, $args = [] ) {

		$defaults = [
			'callback' => '__return_false',
		];

		dfm_register_transient( $name, wp_parse_args( $args, $defaults ) );

	}

}
