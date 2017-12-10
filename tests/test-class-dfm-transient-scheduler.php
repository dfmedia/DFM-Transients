<?php
class Test_Class_DFM_Transient_Scheduler extends WP_UnitTestCase {

	/**
	 * Test to make sure the hooks get setup properly
	 */
	public function testSetupHooks() {

		$transient_name = 'testSetupHooks';
		dfm_register_transient( $transient_name, [
			'callback' => '__return_true',
			'update_hooks' => [ 'test_hook', 'another_test_hook' ],
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->get_transients();

		$this->assertTrue( has_filter( 'admin_post_nopriv_dfm_' . $transient_name ) );

	}

	/**
	 * Don't setup any hooks if no transients are registered
	 */
	public function testSetupHooksNotRun() {

		global $dfm_transients;
		$dfm_transients = [];

		$scheduler_obj = $this->getMockBuilder( 'DFM_Transient_Scheduler' )
			->setMethods( [ 'post_processing_hooks' ] )
			->getMock();

		/**
		 * Asserts that the post_processing_hooks method never gets called if no transients are
		 * registered
		 */
		$scheduler_obj->expects( $this->never() )
			->method( 'post_processing_hooks' );

		$actual = $scheduler_obj->get_transients();
		$this->assertNull( $actual );

	}

	/**
	 * Test to make sure the update happens successfully
	 */
	public function testUpdateSuccessful() {

		$transient_name = 'testSetupHooks';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
			'async_updates' => true,
		] );

		$nonce_obj = new DFM_Async_Nonce( $transient_name );

		$_POST['transient_name'] = $transient_name;
		$_POST['modifier'] = '1';
		$_POST['_nonce'] = $nonce_obj->create();

		$scheduler_obj = new DFM_Transient_Scheduler();

		$scheduler_obj->run_update();

		$expected = 'modifier: 1';
		$actual = get_transient( $transient_name . '_1' );
		$this->assertEquals( $expected, $actual );

	}

	/**
	 * If no transient name is passed in the headers from the async handler, halt execution
	 */
	public function testUnsuccessfulUpdateWithoutName() {

		$transient_name = 'testUnsuccessfulUpdateWithoutName';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
		] );

		$nonce_obj = new DFM_Async_Nonce( $transient_name );

		$_POST['transient_name'] = ''; //This should kill the request
		$_POST['modifier'] = '1';
		$_POST['_nonce'] = $nonce_obj->create();

		$scheduler_obj = new DFM_Transient_Scheduler();

		$scheduler_obj->run_update();

		$this->assertFalse( get_transient( $transient_name . '_1' ) );

	}

	/**
	 * If the incorrect nonce is passed to the method from the handler, halt execution
	 */
	public function testUnsuccessfulUpdateWithoutNonce() {

		$transient_name = 'testUnsuccessfulUpdateWithoutNonce';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
		] );

		$nonce_obj = new DFM_Async_Nonce( $transient_name . '_test' );

		$_POST['transient_name'] = $transient_name; //This should kill the request
		$_POST['modifier'] = '1';
		$_POST['_nonce'] = $nonce_obj->create();

		$scheduler_obj = new DFM_Transient_Scheduler();

		$scheduler_obj->run_update();

		$this->assertFalse( get_transient( $transient_name . '_1' ) );

	}

	/**
	 * If the transient update process is locked by another process, halt execution
	 */
	public function testUnsuccessfulUpdateWithoutLock() {

		$transient_name = 'testUnsuccessfulUpdateWithoutLock';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
		] );

		/**
		 * Manually lock the transient from updating
		 */
		set_transient( 'dfm_lt_' . $transient_name . '_1', 'somevalue' );

		$nonce_obj = new DFM_Async_Nonce( $transient_name );

		$_POST['transient_name'] = $transient_name; //This should kill the request
		$_POST['modifier'] = '1';
		$_POST['_nonce'] = $nonce_obj->create();

		$scheduler_obj = new DFM_Transient_Scheduler();

		$scheduler_obj->run_update();

		$this->assertFalse( get_transient( $transient_name . '_1' ) );

	}

}
