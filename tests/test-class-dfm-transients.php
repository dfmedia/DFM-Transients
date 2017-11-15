<?php


class Test_Class_DFM_Transients extends WP_UnitTestCase {

	public function testNonExistentTransient() {

		$this->setExpectedException( 'Exception', 'You are trying to retrieve a transient that doesn\'t exist' );
		$this->expectException( new DFM_Transients( 'NonExistentTransient', '' ) );

	}

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

	public function testNotOwnsLock() {

		$transient_name = 'testNotOwnsLock';
		$this->register_transient( $transient_name );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$this->assertEquals( false, $transient_obj->owns_lock( 'test' ) );

	}

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

	public function testShouldExpire() {

		$method = $this->reflect_method( 'should_expire' );

		$transient_name = 'TestTransientExpiration';
		$this->register_transient( $transient_name, [ 'expiration' => HOUR_IN_SECONDS ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertTrue( $actual );

	}

	public function testShouldNotExpire() {

		$method = $this->reflect_method( 'should_expire' );

		$transient_name = 'TestTransientNotExpiration';
		$this->register_transient( $transient_name, [ 'expiration' => false ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertFalse( $actual );

	}

	public function testShouldSoftExpire() {

		$method = $this->reflect_method( 'should_soft_expire' );

		$transient_name = 'TestSoftExpireTransient';
		$this->register_transient( $transient_name, [ 'soft_expiration' => true ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertTrue( $actual );

	}

	public function testShouldNotSoftExpire() {

		$method = $this->reflect_method( 'should_soft_expire' );

		$transient_name = 'TestNotSoftExpireTransient';
		$this->register_transient( $transient_name, [ 'soft_expiration' => false ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj );

		$this->assertFalse( $actual );

	}

	public function testTransientIsExpired() {

		$method = $this->reflect_method( 'is_expired' );

		$transient_name = 'TestExpiredTransient';
		$this->register_transient( $transient_name, [ 'expiration' => 1 ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj, [ 'expiration' => time() - 1, 'data' => 'test' ] );

		$this->assertTrue( $actual );

	}

	public function testTransientNotExpired() {

		$method = $this->reflect_method( 'is_expired' );

		$transient_name = 'TestNotExpiredTransient';
		$this->register_transient( $transient_name, [ 'expiration' => DAY_IN_SECONDS ] );

		$transient_obj = new DFM_Transients( $transient_name, '' );

		$actual = $method->invoke( $transient_obj, [ 'expiration' => time(), 'data' => 'test' ] );

		$this->assertFalse( $actual );

	}

	private function reflect_method( $method_name ) {

		$method = new ReflectionMethod( 'DFM_Transients', $method_name );
		$method->setAccessible( true );

		return $method;

	}

	private function register_transient( $name, $args = [] ) {

		$defaults = [
			'callback' => '__return_false',
		];

		dfm_register_transient( $name, wp_parse_args( $args, $defaults ) );

	}
}
