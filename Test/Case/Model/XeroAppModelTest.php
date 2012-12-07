<?php
App::uses('XeroRequest', 'Xero.Model');
App::uses('XeroAppModel', 'Xero.Model');
App::uses('Contact', 'Model');

/**
 * TestXeroAppModel Model
 *
 */
class TestXeroAppModel extends XeroAppModel {

	public function parseConditions($organisation_id, &$conditions) {
		parent::_parseConditions($organisation_id, $conditions);
	}

	public function saveAsLocalModel($organisation_id, $entities) {
		return parent::_saveAsLocalModel($organisation_id, $entities);
	}

	public function setDatasourceCredentialsFromOrganisationId($id) {
		return parent::_setDatasourceCredentialsFromOrganisationId($id);
	}

}

class TestXeroContactModel extends TestXeroAppModel {
	public $localModel = 'TestContact';
}

class TestContact extends AppModel {
	public function saveAll($data) {
		$this->data = $data;
		return true;
	}
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
		$this->AppModel = new TestXeroAppModel;
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
		$organisation_id = 'test_user';

		$this->AppModel->XeroRequest = $this->getMock('XeroRequest', array('lastSuccess'), array('','','test'));
		$this->AppModel->XeroRequest->expects($this->any())->method('lastSuccess')->will($this->returnValue('2012-12-12 12:12:12'));

		$conditions = array();
		$this->AppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array(), 'Should parse empty array');
		
		$conditions = array('modified_after' => '2012-01-01 00:00:00');
		$this->AppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('modified_after' => '2012-01-01 00:00:00'), 'Should not change modified after literal date');
		
		$conditions = array('modified_after' => 'last_update');
		$this->AppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('modified_after' => '2012-12-12 12:12:12'), 'Should retrieve last updated date from db');
		
		$conditions = array('id' => 'test_id');
		$this->AppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('id' => 'test_id'), 'String ID should be returned as passed');

		$conditions = array('id' => 'test_id,test_ids');
		$this->AppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('id' => array('test_id', 'test_ids')), 'Multiple IDs should be returned as array');

		$conditions = array('id' => array('test_id','test_ids'));
		$this->AppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('id' => array('test_id','test_ids')), 'ID array should be returned as array');
	}

	public function testSaveAsLocalModelSuccess() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation_id = 'test_user';
		$entities = array(
			array('TestContact' => array(
				'id' => 'test_contact_1',
				'name' => 'Test Contact1'
			)),
			array('TestContact' => array(
				'id' => 'test_contact_2',
				'name' => 'Test Contact2'
			)),
		);
		$expected = array(
			array('TestContact' => array(
				'id' => 'test_contact_1',
				'name' => 'Test Contact1',
				'user_id' => $organisation_id
			)),
			array('TestContact' => array(
				'id' => 'test_contact_2',
				'name' => 'Test Contact2',
				'user_id' => $organisation_id
			)),
		);

		$this->Model->saveAsLocalModel($organisation_id, $entities);
		$this->assertEquals($this->Model->{$this->Model->localModel}->data, $expected);
	}

	public function testSetDatasourceCredentialsFromOrganisationId() {
		$Datasource =& $this->AppModel->getDatasource();
		$Datasource->credentials = null;

		$XeroCredential = ClassRegistry::init('Xero.XeroCredential');
		$credentials = $XeroCredential->find('first');

		$this->AppModel->setDatasourceCredentialsFromOrganisationId($credentials['XeroCredentials']['organisation_id']);
		$this->assertEquals($Datasource->credentials(), $credentials);
	}

	public function testUpdateAll() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation = array('Organisation' => array('id' => 'test_user'));
		$MockContactModel = $this->getMock('TestXeroContactModel', array('find', 'saveAsLocalModelSuccess'));
		$MockContactModel->expects($this->once())->method('find')->with('all', array('conditions' => array()))->will($this->returnValue(array()));
		$MockContactModel->expects($this->once())->method('_saveAsLocalModelSuccess')->with('test_user', array());
		$result = $MockContactModel->update($organisation);
		$this->assertEquals($result, array());
	}

	public function testUpdateSpecific() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation = array('Organisation' => array('id' => 'test_user'));

		$MockContactModel = $this->getMock('TestXeroContactModel', array('find', 'saveAsLocalModelSuccess'));
		$MockContactModel->expects($this->at(0))->method('find')->with('first', array('conditions' => array('id' => '1')))->will($this->returnValue(array()));
		$MockContactModel->expects($this->at(1))->method('find')->with('first', array('conditions' => array('id' => '2')))->will($this->returnValue(array()));
		$MockContactModel->expects($this->at(2))->method('find')->with('first', array('conditions' => array('id' => '3')))->will($this->returnValue(array()));
		$MockContactModel->expects($this->once())->method('_saveAsLocalModelSuccess')->with('test_user', array());
		
		$result = $MockContactModel->update($organisation, array('id' => '1,2,3'));
		$this->assertEquals(array_values($result), array('1', '2', '3'));
	}
}
