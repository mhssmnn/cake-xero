<?php
App::uses('ShellDispatcher', 'Console');
App::uses('XeroUpdateShell', 'Xero.Console/Command');


/**
* TestUserModel
*
* @package       xero
* @subpackage    xero.tests.cases.shells
*/

/**
* TestXeroUpdateShell
*
* @package       xero
* @subpackage    xero.tests.cases.shells
*/
class TestXeroUpdateShell extends XeroUpdateShell {

/**
 * output property
 *
 * @var string
 */
	public $output = '';

/**
 * out method
 *
 * @param $string
 * @return void
 */
	function out($string = null) {
		$this->output .= $string . "\n";
	}

/**
 * findOreganisaitons method
 *
 * @return first company to get round the CakeResque queueing
 */
	public function findOrganisations() {
		$orgs = parent::findOrganisations();
		return array_slice($orgs, 0, 1);
	}

}

class XeroUpdateShellTestCase extends CakeTestCase {
/**
 * autoFixtures property
 *
 * @var bool false
 */
	public $autoFixtures = true;

	public $fixtures = array('plugin.xero.xero_request', 'plugin.xero.xero_credential', 'core.user');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);
		$this->Shell = $this->getMock(
			'TestXeroUpdateShell',
			array('in', 'hr', 'createFile', 'error', 'err', '_stop', '_showInfo', 'dispatchShell', '_updateError'),
			array($out, $out, $in));

		// $this->Shell->expects($this->any())->method('_updateError')->

		$this->Shell->type = 'TestXeroUpdatePlugin';
		$this->Shell->path = TMP . 'tests' . DS;
		$this->Shell->connection = 'test';

		Configure::write('Xero.Organisation.Model', 'User');
		Configure::write('Xero.Organisation.Filter', '');

		$this->XeroRequest =& ClassRegistry::init('Xero.XeroRequest');
		$this->User =& ClassRegistry::init(array('class' => 'XeroOrganisationUser', 'alias' => 'User', 'table' => 'users'));
		$this->Shell->XeroOrganisation = $this->User;
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->XeroRequest);
		unset($this->Shell);
		unset($this->User);
		parent::tearDown();
	}

	public function testStartup() {

	}

	public function testAll() {

	}

	public function testContacts() {
		// $organisation = array('Organisation' => array('id' => '1', 'name' => 'FirstOrganisation'));
		// $this->Shell->_organisation = $organisation;
		// $this->Shell->XeroContact = $this->getMock('XeroContact', array('update'));

		// $this->Shell->XeroContact->expects($this->once())->method('update')
		// 												 ->with($organisation, array('id' => '', 'modified_after' => 'lastSuccess'));
		// $this->Shell->runCommand('contacts', array());
	}

	public function testInvoices() {

	}

	public function testCreditNotes() {

	}

	public function testEnqueueUpdate() {

	}

	// function testMainUpdateAll() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Company->contain(array('Owner'));
	// 	$companies = $this->Company->findAllByStatus('ACTIVE');

	// 	$this->Shell->expects($this->any())->method('updateParts')->with(true, true, true)
	// 							->will($this->returnValue(array('contacts' => array(), 'invoices' => array(), 'credit_notes' => array())));

	// 	$this->Shell->runCommand(null, array());
	// }

	// function testMainUpdateContacts() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Shell->expects($this->any())->method('updateParts')->with(true, false, false)
	// 							->will($this->returnValue(array('contacts' => array(), 'invoices' => false, 'credit_notes' => false)));

	// 	$this->Shell->runCommand(null, array('--contact', 'active_contact'));
	// }

	// function testMainUpdateInvoices() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Shell->expects($this->any())->method('updateParts')->with(false, true, false)
	// 							->will($this->returnValue(array('contacts' => false, 'invoices' => array(), 'credit_notes' => false)));

	// 	$this->Shell->runCommand(null, array('--invoice', 'chasing_invoice_overdue14'));
	// }

	// function testMainUpdateNoLineItems() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Shell->expects($this->any())->method('updateParts')->with(true, true, true)
	// 							->will($this->returnValue(array('contacts' => array(), 'invoices' => array(), 'credit_notes' => array())));

	// 	$this->Shell->runCommand(null, array('--no_line_items'));
	// }

	// function testMainUpdateInvoicesButNoLineItems() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Shell->expects($this->any())->method('updateParts')->with(false, true, false)
	// 							->will($this->returnValue(array('contacts' => array(), 'invoices' => array(), 'credit_notes' => array())));

	// 	$this->Shell->runCommand(null, array('--no_line_items', '--invoice', 'chasing_invoice_overdue14'));
	// }

	// function testMainUpdateCreditNotes() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Shell->expects($this->any())->method('updateParts')->with(false, false, true)
	// 							->will($this->returnValue(array('contacts' => false, 'invoices' => false, 'credit_notes' => array())));

	// 	$this->Shell->runCommand(null, array('--credit_note', 'valid_credit_note'));
	// }

	// function testMainUpdatePaidOnly() {
	// 	$this->_setupMainMethodMocks();

	// 	$this->Shell->XeroInvoice = $this->getMock('XeroInvoice', array('updatePaid'), array(null, null, 'test'));
	// 	$this->Shell->XeroInvoice->expects($this->atLeastOnce())->method('updatePaid')->will($this->returnValue(array('chasing_invoice_overdue14')));

	// 	$this->Shell->expects($this->never())->method('updateParts');

	// 	$this->Shell->runCommand(null, array('--paid_only'));
	// 	$this->Shell->runCommand(null, array('--paid_only', '--contact', 'active_contact'));
	// }

	// function _setupMainMethodMocks() {
	// 	$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
	// 	$in = $this->getMock('ConsoleInput', array(), array(), '', false);

	// 	$this->Shell = $this->getMock('TestXeroUpdateShell',
	// 		array('out', 'err', 'createFile', '_stop', 'clear', 'log', 'updateParts', 'updateError'),
	// 		array($out, $out, $in)
	// 	);

	// 	$this->Shell->initialize();
	// 	$this->Shell->loadTasks();

	// 	$this->Shell->expects($this->never())->method('updateError');

	// 	$this->Shell->XeroRequest = $this->getMock('XeroRequest', array('saveSuccess', 'saveFailure'), array(null, null, 'test'));
	// 	$this->Shell->XeroRequest->expects($this->any())->method('saveSuccess')->will($this->returnValue(true));
	// 	$this->Shell->XeroRequest->expects($this->any())->method('saveFailure')->will($this->returnValue(true));

	// 	$this->Shell->XeroConnect = $this->getMock('XeroConnectTask', array('connect'), array($out, $out, $in));
	// 	$this->Shell->XeroConnect->expects($this->any())->method('connect')->will($this->returnValue(true));
	// }

	// function testUpdateParts() {
	// 	$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
	// 	$in = $this->getMock('ConsoleInput', array(), array(), '', false);

	// 	$this->Shell = $this->getMock('TestXeroUpdateShell',
	// 		array('out', 'err', 'createFile', '_stop', 'clear', 'log', 'main', 'updateError'),
	// 		array($out, $out, $in)
	// 	);

	// 	$this->Shell->initialize();
	// 	$this->Shell->loadTasks();

	// 	$this->Shell->XeroContact = $this->getMock('XeroContact', array('update'), array(null, null, 'test'));
	// 	$this->Shell->XeroInvoice = $this->getMock('XeroInvoice', array('update'), array(null, null, 'test'));
	// 	$this->Shell->XeroLineItem = $this->getMock('XeroLineItem', array('update'), array(null, null, 'test'));
	// 	$this->Shell->XeroCreditNote = $this->getMock('XeroCreditNote', array('update'), array(null, null, 'test'));

	// 	$this->Shell->XeroContact->expects($this->once())->method('update')
	// 													 ->with($company_id)->will($this->returnValue($contacts));

	// 	$this->Shell->XeroInvoice->expects($this->once())->method('update')
	// 													 ->with($company_id)->will($this->returnValue($invoices));

	// 	$this->Shell->XeroLineItem->expects($this->once())->method('update')
	// 														->with($invoices)->will($this->returnValue($line_items));

	// 	$this->Shell->XeroCreditNote->expects($this->once())->method('update')
	// 															->with($company_id)->will($this->returnValue($credit_notes));
	// }

	// function testRenewToken() {
	// 	$this->Task->startup();
	// 	$this->Task->initialize();
	// 	$this->Task->XeroAuth =& new MockXeroAuthComponent();
	// 	$this->assertTrue(is_a($this->Task->XeroInvoice, 'XeroInvoice'));
	//
	// 	$expired_token = $this->Task->XeroCredential->findByCompanyId('4d815f83-0e78-45b1-be41-1b25788a128d');
	// 	$this->assertTrue(!empty($expired_token));
	//
	// 	$accessToken = array(
	// 		'oauth_token' => 'NWNJZTK1NDE4ZMVJNDLMMWFLZJIWOT',
	// 		'oauth_token_secret' => 'MDY5Y2EYMTYZMDVJNGJLOWFHNJM0ND',
	// 		'oauth_session_handle' => 'NZMXZDI4ZJU2ZTBJNDJJZJG2ZDI2ZJ',
	// 		'oauth_expires_in' => (60*30),
	// 	);
	// 	$this->Task->XeroAuth->setReturnValue('getOAuthAccessToken', $accessToken);
	// 	$this->Task->XeroAuth->expectOnce('getOAuthAccessToken');
	//
	// 	$this->Task->renew($expired_token['XeroCredential']);
	//
	// 	$r = $this->Task->XeroCredential->findByCompanyId('4d815f83-0e78-45b1-be41-1b25788a128d');
	//
	// 	$this->assertEqual($r['XeroCredential']['key'], $accessToken['oauth_token']);
	// 	$this->assertEqual($r['XeroCredential']['secret'], $accessToken['oauth_token_secret']);
	// 	$this->assertEqual($r['XeroCredential']['session_handle'], $accessToken['oauth_session_handle']);
	//
	// }
	//
	// function testUpdate() {
	// 	$this->Task->startup();
	// 	$this->Task->initialize();
	// 	$this->Task->XeroAuth =& new MockXeroAuthComponent();
	// 	$this->Task->expectOnce('saveSuccess');
	// 	$this->Task->expectNever('saveFailure');
	//
	// 	$accessToken = array(
	// 		'oauth_token' => 'NWNJZTK1NDE4ZMVJNDLMMWFLZJIWOT',
	// 		'oauth_token_secret' => 'MDY5Y2EYMTYZMDVJNGJLOWFHNJM0ND',
	// 		'oauth_session_handle' => 'NZMXZDI4ZJU2ZTBJNDJJZJG2ZDI2ZJ',
	// 		'oauth_expires_in' => (60*30),
	// 	);
	// 	$this->Task->XeroAuth->setReturnValue('getOAuthAccessToken', $accessToken);
	//
	// 	$this->Task->params['company'] = '4d815f83-0e78-45b1-be41-1b25788a128d';
	// 	$this->Task->update();
	//
	// 	$this->assertEqual(Configure::read('Xero.config.oauth_token'), $accessToken['oauth_token']);
	// 	$this->assertEqual(Configure::read('Xero.config.oauth_token_secret'), $accessToken['oauth_token_secret']);
	// 	$this->assertEqual(Configure::read('Xero.config.oauth_token_handle'), $accessToken['oauth_session_handle']);
	//
	//
	// }

}