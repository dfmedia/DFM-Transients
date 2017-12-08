<?php

class Test_Template_Tags extends WP_UnitTestCase {

	/**
	 * Test to make sure registration API works correctly
	 *
	 * @dataProvider providerRegisterTest
	 * @param string $transient_name Name of the transient
	 * @param array  $args           Arguments to pass to the registration
	 * @param        $expected_args  array The args we expect to get on the other side of the
	 *                               assertion
	 */
	public function testSuccessfulRegisterTransient( $transient_name, $args, $expected_args ) {

		dfm_register_transient( $transient_name, $args );

		global $dfm_transients;
		$this->assertEquals( (object) $expected_args, $dfm_transients[ $transient_name ] );

	}

	/**
	 * Test to make sure an error gets thrown when you try to register a transient without a name
	 */
	public function testUnsuccessfulRegistration() {

		$transient_name = 'testTransientWithoutCallback';

		$this->setExpectedException( 'Exception', 'You must add a callback when registering a transient' );
		$this->expectException( dfm_register_transient( $transient_name, [] ) );

	}

	/**
	 * Simple get transient test
	 */
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

		dfm_delete_transient( $transient_name );

		$this->assertFalse( get_transient( $transient_name ) );

	}

	/**
	 * Test to get a transient that has no initial value
	 */
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

		dfm_delete_transient( $transient_name );
		$this->assertFalse( get_transient( $transient_name ) );

	}

	/**
	 * Test to get a transient from post meta
	 */
	public function testGetTransientPostMeta() {

		$transient_name = 'testTransientGetFromPostMeta';
		$expected = 'test value';

		$post_id = $this->factory->post->create();

		update_post_meta( $post_id, 'dfm_transient_' . $transient_name, $expected );

		dfm_register_transient( $transient_name, [ 'cache_type' => 'post_meta', 'callback' => '__return_true' ] );
		$actual = dfm_get_transient( $transient_name, $post_id );

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Helper method for the register test data provider
	 *
	 * @return array
	 */
	public function providerRegisterTest() {
		return [
			[
				'testTransient1',
				[
					'hash_key' => false,
					'cache_type' => 'transient',
					'callback' => '__return_true',
					'async_updates' => false,
					'update_hooks' => [],
					'expiration' => false,
					'soft_expiration' => false,
				],
				[
					'key' => 'testTransient1',
					'hash_key' => false,
					'cache_type' => 'transient',
					'callback' => '__return_true',
					'async_updates' => false,
					'update_hooks' => [],
					'expiration' => false,
					'soft_expiration' => false,
				],
			],
			[
				'testTransient2',
				[
					'key' => 'testTransient2Key',
					'hash_key' => false,
					'cache_type' => 'term_meta',
					'callback' => '__return_true',
					'async_updates' => true,
					'update_hooks' => [ 'update_post' ],
					'expiration' => HOUR_IN_SECONDS,
					'soft_expiration' => true,
				],
				[
					'key' => 'testTransient2Key',
					'hash_key' => false,
					'cache_type' => 'term_meta',
					'callback' => '__return_true',
					'async_updates' => true,
					'update_hooks' => [ 'update_post' ],
					'expiration' => HOUR_IN_SECONDS,
					'soft_expiration' => true,
				],
			],
			[
				'testTransient3',
				[
					'callback' => '__return_true',
				],
				[
					'key' => 'testTransient3',
					'hash_key' => false,
					'cache_type' => 'transient',
					'callback' => '__return_true',
					'async_updates' => false,
					'update_hooks' => false,
					'expiration' => false,
					'soft_expiration' => false,
				],
			],
		];
	}

}
