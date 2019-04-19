<?php
class Test_Class_DFM_Transient_Scheduler extends WP_UnitTestCase {

	protected $server;
	protected $endpoint;

	public function setUp() {

		parent::setup();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server;
		$this->server   = $wp_rest_server;
		$this->endpoint = '/' . DFM_Transient_Scheduler::API_NAMESPACE . '/' . DFM_Transient_Scheduler::ENDPOINT_RUN . '/(?P<transient>[\w|-]+)';
		do_action( 'rest_api_init' );

	}

	public function tearDown() {
		parent::tearDown();
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
	 * Test that our endpoint actually exists.
	 */
	public function testEndpointRegistered() {

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->register_rest_endpoint();

		$this->assertTrue( isset( $this->server->get_routes()[ $this->endpoint ] ) );
		$this->assertTrue( isset( $this->server->get_routes()[ '/' . DFM_Transient_Scheduler::API_NAMESPACE ] ) );

	}

	/**
	 * Test the endpoint returns an error when trying to be accessed
	 */
	public function testInvalidRequest() {

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->register_rest_endpoint();

		$request = new WP_REST_Request( 'GET', '/' . DFM_Transient_Scheduler::API_NAMESPACE . '/' . DFM_Transient_Scheduler::ENDPOINT_RUN . '/test' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 404, $response->data['data']['status'] );

	}

	/**
	 * Test that processing doesn't happen if auth fails
	 */
	public function testAuthFailure() {

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->register_rest_endpoint();

		$request = new WP_REST_Request( 'PUT', '/' . DFM_Transient_Scheduler::API_NAMESPACE . '/' . DFM_Transient_Scheduler::ENDPOINT_RUN . '/test' );
		$request->set_body( wp_json_encode( [
			'secret' => 'test',
		] ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 'no-secret', $response->data['code'] );

	}

	/**
	 * Test that the request executes properly
	 */
	public function testSuccessfulRequest() {

		$transient_name = 'testSuccessfulRequest';

		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
			'async' => true,
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->register_rest_endpoint();

		$request = new WP_REST_Request( 'PUT', '/' . DFM_Transient_Scheduler::API_NAMESPACE . '/' . DFM_Transient_Scheduler::ENDPOINT_RUN . '/' . $transient_name );
		$request->set_body( wp_json_encode( [
			'secret' => DFM_TRANSIENTS_SECRET,
			'modifiers' => 'test',
			'lock_key' => '',
		] ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( $transient_name . ' transient updated', $response->data );
		$this->assertEquals( 200, $response->status );

		$expected = 'modifier: test';
		$actual = get_transient( $transient_name . '_test' );
		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Test that transients with multiple modifiers process properly
	 */
	public function testSuccessfulRequestWithMultipleModifiers() {

		$transient_name = 'testSuccessfulRequestWithMultipleModifiers';

		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
			'async' => true,
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->register_rest_endpoint();

		$request = new WP_REST_Request( 'PUT', '/' . DFM_Transient_Scheduler::API_NAMESPACE . '/' . DFM_Transient_Scheduler::ENDPOINT_RUN . '/' . $transient_name );
		$request->set_body( wp_json_encode( [
			'secret' => DFM_TRANSIENTS_SECRET,
			'modifiers' => [ 'foo', 'bar' ],
			'lock_key' => '',
		] ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( $transient_name . ' transient updated', $response->data );
		$this->assertEquals( 200, $response->status );

		$expected = 'modifier: foo';
		$actual = get_transient( $transient_name . '_foo' );
		$this->assertEquals( $expected, $actual );

		$expected = 'modifier: bar';
		$actual = get_transient( $transient_name . '_bar' );
		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Test that updates get spawned appropriately
	 */
	public function testUpdatesSpawnedCorrectly() {

		$transient_name = 'testUpdatesSpawnedCorrectly';

		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
			'update_hooks' => [
				'my_test_update_hook' => function() {
					return [ 'foo', 'bar' ];
				}
			],
			'async' => true,
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->get_transients();

		do_action( 'my_test_update_hook' );

		$scheduler_obj->execute_async_updates();

		global $wp_filter;
		$this->assertEquals( 3, count( $wp_filter['shutdown'][10] ) );

	}

	/**
	 * Test that hooks get added corrected
	 */
	public function testHooksGetScheduled() {

		$transient_name = 'testHooksGetScheduled';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
			'update_hooks' => 'my_test_hook',
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->get_transients();

		$this->assertTrue( has_action( 'my_test_hook' ) );

	}

	/**
	 * Test that multiple update hooks registered to a transient get added properly
	 */
	public function testMultipleHooksGetScheduled() {

		$transient_name = 'testMultipleHooksGetScheduled';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
			'update_hooks' => [
				'foo' => '__return_true',
				'bar' => '__return_true',
			]
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->get_transients();

		$this->assertTrue( has_action( 'foo' ) );
		$this->assertTrue( has_action( 'bar' ) );

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

		$scheduler_obj = new DFM_Transient_Scheduler();

		$scheduler_obj->run_update( $transient_name, '1', '' );

		$expected = 'modifier: 1';
		$actual = get_transient( $transient_name . '_1' );
		$this->assertEquals( $expected, $actual );

	}

	/**
	 * If no transient name is passed in the headers from the async handler, halt execution
	 * @expectedException Exception
	 */
	public function testUnsuccessfulUpdateWithoutName() {

		$transient_name = 'testUnsuccessfulUpdateWithoutName';
		dfm_register_transient( $transient_name, [
			'callback' => function( $modifier ) {
				return 'modifier: ' . $modifier;
			},
		] );

		$scheduler_obj = new DFM_Transient_Scheduler();
		$response = $scheduler_obj->run_update( '', '1', '' );
		$this->expectException( $response );
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
		$scheduler_obj = new DFM_Transient_Scheduler();
		$scheduler_obj->run_update( $transient_name, '1', '' );
		$this->assertFalse( get_transient( $transient_name . '_1' ) );

	}

}
