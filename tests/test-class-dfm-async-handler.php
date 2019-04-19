<?php
class Test_Class_DFM_Async_Handler extends WP_UnitTestCase {

	/**
	 * Test that the request goes out as it should
	 */
	public function testAsyncRequestPosted() {

		$async_obj = new DFM_Async_Handler( 'testTransient', '' );
		$result = $async_obj->spawn_event();

		/**
		 * Not the best assertion, but the return value from wp_safe_remote_post is the response
		 * from the request, which doesn't really contain any useful data
		 */
		$this->assertFalse( is_wp_error( $result ) );

	}

}
