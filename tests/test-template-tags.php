<?php

class Test_Template_Tags extends WP_UnitTestCase {

	public function testSuccessfulRegisterTransient() {

		$transient_1_name = 'testTransient1';
		$transient_1_args = [
			'hash_key' => false,
			'cache_type' => 'transient',
			'callback' => '__return_true',
			'async_updates' => false,
			'update_hooks' => [],
			'expiration' => false,
			'soft_expiration' => false,
		];
		dfm_register_transient( $transient_1_name, $transient_1_args );
		$transient_1_expected_args = array_merge( [ 'key' => $transient_1_name ], $transient_1_args );

		$transient_2_name = 'testTransient2';
		$transient_2_args = [
			'key' => 'testTransient2Key',
			'hash_key' => false,
			'cache_type' => 'term_meta',
			'callback' => '__return_true',
			'async_updates' => true,
			'update_hooks' => [ 'update_post' ],
			'expiration' => HOUR_IN_SECONDS,
			'soft_expiration' => true,
		];
		$transient_2_expected_args = $transient_2_args;
		dfm_register_transient( $transient_2_name, $transient_2_args );

		$transient_3_name = 'testTransient3';
		$transient_3_args = [
			'callback' => '__return_true',
		];
		$transient_3_expected_args = [
			'key' => $transient_3_name,
			'hash_key' => false,
			'cache_type' => 'transient',
			'callback' => '__return_true',
			'async_updates' => false,
			'update_hooks' => false,
			'expiration' => false,
			'soft_expiration' => false,
		];
		dfm_register_transient( $transient_3_name, $transient_3_args );

		global $dfm_transients;
		$this->assertEquals( (object) $transient_1_expected_args, $dfm_transients[ $transient_1_name ] );
		$this->assertEquals( (object) $transient_2_expected_args, $dfm_transients[ $transient_2_name ] );
		$this->assertEquals( (object) $transient_3_expected_args, $dfm_transients[ $transient_3_name ] );

	}

	public function testUnsuccessfulRegistration() {

		$transient_name = 'testTransientWithoutCallback';

		$this->setExpectedException( 'Exception', 'You must add a callback when registering a transient' );
		$this->expectException( dfm_register_transient( $transient_name, [] ) );

	}

	public function testGetTransient() {

		$transient_name = 'testTransientGet';
		$transient_value = 'test value';
		$r = set_transient( $transient_name, $transient_value );

		$this->assertTrue( $r );

		dfm_register_transient( $transient_name, [
			'cache_type' => 'transient',
			'callback' => function() {
				return 'test value';
			},
		] );

		$actual = dfm_get_transient( $transient_name );

		$this->assertEquals( $transient_value, $actual );

	}

	public function testGetTransientWithNoInitialValue() {

		$transient_name = 'testTransientGetWithoutValue';
		$expected = 'test value';

		dfm_register_transient( $transient_name, [
			'cache_type' => 'transient',
			'callback' => function() {
				return 'test value';
			},
		] );

		$actual = dfm_get_transient( $transient_name );

		$this->assertEquals( $expected, $actual );

	}

	public function testGetTransientExpired() {

		$transient_name = 'testTransientGetExpired';

		dfm_register_transient( $transient_name, [
			'cache_type' => 'transient',
			'expiration' => 2,
			'callback' => function() {
				return 'test value again';
			},
		] );

		dfm_set_transient( $transient_name, 'test value' );

		$actual = dfm_get_transient( $transient_name );

		$this->assertEquals( 'test value', $actual );

		// Wait for transient to expire
		sleep( 2 );

		$actual = dfm_get_transient( $transient_name );
		$this->assertEquals( 'test value', $actual );

		// Allow new data to be saved
		sleep( 1 );
		$actual = dfm_get_transient( $transient_name );
		$this->assertEquals( 'test value again', $actual );

	}

	public function testGetTransientSoftExpired() {

		$transient_name = 'testTransientGetSoftExpired';

		dfm_register_transient( $transient_name, [
			'cache_type' => 'transient',
			'soft_expiration' => true,
			'expiration' => 2,
			'callback' => function() {
				return 'test value again';
			},
		] );

		dfm_set_transient( $transient_name, 'test value' );

		$transient_object = new DFM_Transients( $transient_name, '' );
		$actual = $transient_object->get();

		$this->assertEquals( 'test value', $actual );

		apply_filters( 'dfm_transient_is_expired', '__return_true' );

		$actual = dfm_get_transient( $transient_name );
		$this->assertEquals( 'test value', $actual );

		$data_handler = new DFM_Async_Handler( $transient_name, '', $transient_object->lock_key );
		$data_handler->spawn_event();

		$actual = dfm_get_transient( $transient_name );
		$this->assertEquals( 'test value again', $actual );

	}

	public function testGetTransientPostMeta() {

		$transient_name = 'testTransientGetFromPostMeta';
		$expected = 'test value';

		$post_id = $this->factory->post->create();

		update_post_meta( $post_id, 'dfm_transient_' . $transient_name, $expected );

		dfm_register_transient( $transient_name, [ 'cache_type' => 'post_meta', 'callback' => '__return_true' ] );
		$actual = dfm_get_transient( $transient_name, $post_id );

		$this->assertEquals( $expected, $actual );

	}

}
