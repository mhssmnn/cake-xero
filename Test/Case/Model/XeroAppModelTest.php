<?php
App::uses('XeroAppModel', 'Xero.Model');

/**
 * XeroAppModel Test Case
 *
 */
class XeroAppModelTestCase extends CakeTestCase {
/**
 * Fixtures
 *
 * @var array
 */
	//public $fixtures = array('plugin.xero.xero_app_model');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->XeroAppModel = ClassRegistry::init('Xero.XeroAppModel');
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->XeroAppModel);

		parent::tearDown();
	}
}
