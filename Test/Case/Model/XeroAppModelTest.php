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

class TestXeroContact extends TestXeroAppModel {
	public $localModel = 'TestContact';
}

class TestContact extends AppModel {
	public $data = array();

	public function saveAll($data) {
		$this->data[] = $data;
		if (!empty($data) && isset($data['TestContact']['return'])) {
			return $data['TestContact']['return'];
		}
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
	public $fixtures = array('plugin.xero.xero_credential');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->XeroAppModel = new TestXeroAppModel;
		$this->XeroContact = new TestXeroContact;
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->XeroAppModel);
		unset($this->Model);

		parent::tearDown();
	}

	public function testParseConditions() {
		$organisation_id = 'test_user';

		$this->XeroAppModel->XeroRequest = $this->getMock('XeroRequest', array('lastSuccess'), array('','','test'));
		$this->XeroAppModel->XeroRequest->expects($this->any())->method('lastSuccess')->will($this->returnValue('2012-12-12 12:12:12'));

		$conditions = array();
		$this->XeroAppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array(), 'Should parse empty array');
		
		$conditions = array('modified_after' => '2012-01-01 00:00:00');
		$this->XeroAppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('modified_after' => '2012-01-01 00:00:00'), 'Should not change modified after literal date');
		
		$conditions = array('modified_after' => 'last_update');
		$this->XeroAppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('modified_after' => '2012-12-12 12:12:12'), 'Should retrieve last updated date from db');
		
		$conditions = array('id' => 'test_id');
		$this->XeroAppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('id' => 'test_id'), 'String ID should be returned as passed');

		$conditions = array('id' => 'test_id,test_ids');
		$this->XeroAppModel->parseConditions($organisation_id, $conditions);
		$this->assertEqual($conditions, array('id' => array('test_id', 'test_ids')), 'Multiple IDs should be returned as array');

		$conditions = array('id' => array('test_id','test_ids'));
		$this->XeroAppModel->parseConditions($organisation_id, $conditions);
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

		$this->XeroContact->saveAsLocalModel($organisation_id, $entities);
		$this->assertEquals($this->XeroContact->{$this->XeroContact->localModel}->data, $expected);
	}

	/**
	 * Make sure the correct exceptions are thrown.
	 * @expectedException XeroIOException
	 */
	public function testSaveAsLocalModelFailure() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation_id = 'test_user';
		$entities = array(array('TestContact' => array('id' => 'test_contact', 'name' => 'Test Contact', 'return' => false)));

		$this->XeroContact->saveAsLocalModel($organisation_id, $entities);
	}

	public function testSetDatasourceCredentialsFromOrganisationId() {
		$Datasource =& $this->XeroAppModel->getDatasource();
		$Datasource->credentials = null;

		$XeroCredential = ClassRegistry::init('Xero.XeroCredential');
		$credentials = $XeroCredential->find('first');

		$this->XeroAppModel->setDatasourceCredentialsFromOrganisationId($credentials['XeroCredential']['organisation_id']);
		$this->assertEquals($Datasource->credentials(), $credentials);
	}

	public function testUpdateAll() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation = array('Organisation' => array('id' => 'test_user'));
		$expected = array(
			array('Contact' => array('id' => 1)),
			array('Contact' => array('id' => 2)),
			array('Contact' => array('id' => 3))
		);

		$MockContactModel = $this->getMock('TestXeroContact', array('find', '_saveAsLocalModel'));
		$MockContactModel->expects($this->once())->method('find')->with('all', array('conditions' => array()))->will($this->returnValue($expected));
		$MockContactModel->expects($this->once())->method('_saveAsLocalModel')->with('test_user', $expected);
		$result = $MockContactModel->update($organisation);
		$this->assertEquals(array_values($result), array('1', '2', '3'));
	}

	public function testUpdateSpecific() {
		Configure::write('Xero.Organisation.Model', 'User');
		$organisation = array('Organisation' => array('id' => 'test_user'));
		$expected = array(
			array('Contact' => array('id' => 1)),
			array('Contact' => array('id' => 2)),
			array('Contact' => array('id' => 3))
		);

		$MockContactModel = $this->getMock('TestXeroContact', array('find', '_saveAsLocalModel'));
		$MockContactModel->expects($this->at(0))->method('find')->with('first', array('conditions' => array('id' => '1')))->will($this->returnValue($expected[0]));
		$MockContactModel->expects($this->at(1))->method('find')->with('first', array('conditions' => array('id' => '2')))->will($this->returnValue($expected[1]));
		$MockContactModel->expects($this->at(2))->method('find')->with('first', array('conditions' => array('id' => '3')))->will($this->returnValue($expected[2]));
		$MockContactModel->expects($this->once())->method('_saveAsLocalModel')->with('test_user', $this->anything());
		
		$result = $MockContactModel->update($organisation, array('id' => '1,2,3'));
		$this->assertEquals(array_values($result), array('1', '2', '3'));
	}

	/**
	 * Make sure the correct exceptions are thrown.
	 * @expectedException XeroBadParametersException
	 */
	public function testUpdateExceptions() {
		$this->XeroAppModel->update(123);
		$this->XeroAppModel->update('string');
		$this->XeroAppModel->update(array('a' => true));
	}
}
