<?php
class Test_Class_DFM_Async_Nonce extends WP_UnitTestCase {

	/**
	 * Tests that the nonce actually gets created
	 */
	public function testNonceCreation() {
		$nonce_obj = new DFM_Async_Nonce( 'test' );
		$actual = $nonce_obj->create();
		$this->assertTrue( is_string( $actual ) );
		$this->assertEquals( 10, strlen( $actual ) );
	}

	/**
	 * Tests that nonces are actually unique
	 */
	public function testNonceUnique() {
		$first_obj = new DFM_Async_Nonce( 'test' );
		$second_obj = new DFM_Async_Nonce( 'another test' );
		$first = $first_obj->create();
		$second = $second_obj->create();
		$this->assertNotEquals( $first, $second );
	}

	/**
	 * Tests that the verification of the nonce works correctly
	 */
	public function testShouldVerifyNonce() {
		$nonce_obj = new DFM_Async_Nonce( 'testing' );
		$nonce = $nonce_obj->create();
		$this->assertTrue( $nonce_obj->verify( $nonce ) );
	}

	/**
	 * Tests that the any random string doesn't get verified by the nonce check
	 */
	public function testShouldNotVerifyNonce() {
		$nonce_obj = new DFM_Async_Nonce( 'another_test' );
		$this->assertFalse( $nonce_obj->verify( 'some random string' ) );
	}

	/**
	 * Tests that an older nonce (older than 12 hours) still will get verified
	 */
	public function testShouldVerifyOldNonce() {
		$nonce_obj = new DFM_Async_Nonce( 'some_test' );
		$i = wp_nonce_tick() - 1;
		$nonce = substr( wp_hash( $i . 'dfm_some_test' . get_class( $nonce_obj ), 'nonce' ), -12, 10 );
		$this->assertTrue( $nonce_obj->verify( $nonce ) );
	}

	/**
	 * Tests that a nonce older than 24 hours does not get verified
	 */
	public function testShouldNotVerifyOldNonce() {
		$nonce_obj = new DFM_Async_Nonce( 'some_other_test' );
		$i = wp_nonce_tick() - 2;
		$nonce = substr( wp_hash( $i . 'dfm_some_test' . get_class( $nonce_obj ), 'nonce' ), -12, 10 );
		$this->assertFalse( $nonce_obj->verify( $nonce ) );
	}

}
