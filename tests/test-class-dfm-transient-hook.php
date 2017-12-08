<?php
class Test_Class_DFM_Transient_Hook extends WP_UnitTestCase {

	/**
	 * Test to make sure the hooks get setup correctly
	 */
	public function testHookAddition() {
		$hook_obj = new DFM_Transient_Hook( 'testName', 'testHook', true );
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

		$hook_obj = new DFM_Transient_Hook( $transient_name, 'testHook', false );

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

		/**
		 * Lock the update manually
		 */
		set_transient( 'dfm_lt_' . $transient_name, '1234' );

		$hook_obj = new DFM_Transient_Hook( $transient_name, 'testHook', false );
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

		$hook_obj = new DFM_Transient_Hook( $transient_name, 'testHook', false, function() {
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

		$hook_obj = new DFM_Transient_Hook( $transient_name, 'testHook', false, function() {
			return false;
		} );

		$hook_obj->spawn();

		$this->assertEquals( false, get_transient( $transient_name ) );

	}

	/**
	 * Tests to make sure an async update gets scheduled properly
	 */
	public function testAsyncHandlerUpdate() {

		$hook_obj = new DFM_Transient_Hook( 'testTransient', 'testHook', true );
		$hook_obj->spawn();

		global $wp_filter;
		$this->assertEquals( 1, count( $wp_filter['shutdown']->callbacks[10] ) );

	}

	/**
	 * Tests to make sure multiple async updated get scheduled properly
	 */
	public function testMultipleAsyncHandlerUpdates() {

		$hook_obj = new DFM_Transient_Hook( 'testTransient', 'testHook', true, function() {
			return [ 1, 2, 3 ];
		} );

		$hook_obj->spawn();

		global $wp_filter;
		$this->assertEquals( 3, count( $wp_filter['shutdown']->callbacks[10] ) );

	}

}
