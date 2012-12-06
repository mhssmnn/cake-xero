<?php
App::uses('XeroAppModel', 'Xero.Model');
App::uses('Contact', 'Model');

/**
 * TestXeroAppModel Model
 *
 */
class TestXeroAppModel extends XeroAppModel {

	public function parseConditions($organisation_id, &$conditions) {
		return parent::_parseConditions($organisation_id, $conditions);
	}

	public function saveAsLocalModel($organisation_id, $entities) {
		return parent::_saveAsLocalModel($organisation_id, $entities);
	}

	public function setDatasourceCredentialsFromOrganisationId($id) {
		return parent::_setDatasourceCredentialsFromOrganisationId($id);
	}

}

class TestXeroContactModel extends XeroAppModel {
	public $localModel = 'Contact';
}


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
		$this->AppModel = new TestAppModel;
		$this->Model = new TestXeroContactModel;
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->AppModel);
		unset($this->Model);

		parent::tearDown();
	}

	public function testParseConditions() {

	}

	/**
	 * Make sure the correct exceptions are thrown.
	 * @expectedException XeroBadParametersException
	 */
	public function testUpdateExceptions() {
		$this->AppModel->update(123);
		$this->AppModel->update('string');
		$this->AppModel->update(array('a' => true));
	}

	public function testUpdate() {
		$this->assertFalse($this->AppModel->update(null), 'Should return false if empty organisation is passed');

		$xam = $this->Model;
		$model = array(
			'Organisation' => array(
				'id' => 'test_user'
			));
		$entities = $xam->update($model);
		$this->assertEqual($xam->localModel, 'Contact', "Localmodel is set to " . $xaml->localModel);
		$this->assertEqual($xam->endpoint, 'Contacts');
		var_dump($entities); die;
	}

	public function testSaveAsLocalModelSuccess() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation_id = 'test_user';
		$entities = array(
			array('Contact' => array(
				'id' => 'test_contact_1',
				'name' => 'Test Contact1'
			)),
			array('Contact' => array(
				'id' => 'test_contact_2',
				'name' => 'Test Contact2'
			)),
		);
		$expected = array(
			array('Contact' => array(
				'id' => 'test_contact_1',
				'name' => 'Test Contact1',
				'user_id' => $organisation_id
			)),
			array('Contact' => array(
				'id' => 'test_contact_2',
				'name' => 'Test Contact2',
				'user_id' => $organisation_id
			)),
		);

		$MockLocalModel = $this->getMock('Contact', array('saveAll'), array('','','test'));
		$MockLocalModel->expects($this->exactly(2))->method('saveAll')->with($expected);

		$this->Model->localModel = get_class($MockLocalModel);

		$this->Model->saveAsLocalModel($organisation_id, $entities);
	}

	public function testSetDatasourceCredentialsFromOrganisationId() {
		$Datasource =& $this->AppModel->getDatasource();
		$Datasource->credentials = null;

		$XeroCredential = ClassRegistry::init('Xero.XeroCredential');
		$credentials = $XeroCredential->find('first');

		$this->AppModel->setDatasourceCredentialsFromOrganisationId($credentials['XeroCredentials']['organisation_id']);
		$this->assertEqual($Datasource->credentials(), $credentials);
	}

}
