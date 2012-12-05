<?php
App::uses('XeroRequest', 'Xero.Model');

/**
 * XeroRequest Test Case
 *
 */
class XeroRequestTestCase extends CakeTestCase {
/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array('plugin.xero.xero_request');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->XeroRequest = ClassRegistry::init('Xero.XeroRequest');
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->XeroRequest);
		parent::tearDown();
	}

/**
 * testlastSuccess method
 *
 * @return void
 */
	public function testLastSuccess() {
		$lastSuccessContacts = $this->XeroRequest->lastSuccess('uptodate_org', 'Contacts');
		$lastSuccessInvoices = $this->XeroRequest->lastSuccess('uptodate_org', 'Invoices');
		$lastSuccessIndInvoice = $this->XeroRequest->lastSuccess('uptodate_org', 'Invoices/INV-0123');
		$lastSuccessCreditNotes = $this->XeroRequest->lastSuccess('uptodate_org', 'CreditNotes');
		
		$expected = "2012-03-14 09:00:00";
		$this->assertEqual($expected, $lastSuccessContacts);
		
		$expected = "2012-03-13 09:00:00";
		$this->assertEqual($expected, $lastSuccessInvoices);
		
		$expected = "2012-03-14 09:00:00";
		$this->assertEqual($expected, $lastSuccessIndInvoice);
		
		$expected = "2012-03-15 09:00:00";
		$this->assertEqual($expected, $lastSuccessCreditNotes);
		
		$lastSuccessInvoices = $this->XeroRequest->lastSuccess('nonexisting', 'Invoices');
		$this->assertFalse($lastSuccessInvoices);
	}
}
