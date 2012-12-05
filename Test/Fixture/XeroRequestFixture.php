<?php
/**
 * XeroRequestFixture
 *
 */
class XeroRequestFixture extends CakeTestFixture {

/**
 * Fields
 *
 * @var array
 */
	public $fields = array(
		'id' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 36, 'key' => 'primary', 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'organisation_id' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 36, 'key' => 'index', 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'status' => array('type' => 'string', 'null' => true, 'default' => NULL, 'length' => 25, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'endpoint' => array('type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'query' => array('type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'entities' => array('type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'error' => array('type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'modified' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'indexes' => array('PRIMARY' => array('column' => 'id', 'unique' => 1), 'organisation_id' => array('column' => 'organisation_id', 'unique' => 0)),
		'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'MyISAM')
	);

	var $records = array(
		array(
			'id' => 'contacts_only_request',
			'organisation_id' => 'uptodate_org',
			'status' => 'SUCCESS',
			'endpoint' => 'api.xro/2.0/Contacts',
			'query' => '',
			'entities' => 'cid1,cid2,cid3',
			'error' => '',
			'created' => '2012-03-14 09:00:00',
			'modified' => '2012-03-14 09:00:00'
		),
		array(
			'id' => 'invoices_only_request',
			'organisation_id' => 'uptodate_org',
			'status' => 'SUCCESS',
			'endpoint' => 'api.xro/2.0/Invoices',
			'query' => '(Type == "ACCREC")',
			'entities' => 'iid1,iid2,iid3',
			'error' => '',
			'created' => '2012-03-13 09:00:00',
			'modified' => '2012-03-13 09:00:00'
		),
		array(
			'id' => 'single_invoice_only_request',
			'organisation_id' => 'uptodate_org',
			'status' => 'SUCCESS',
			'endpoint' => 'api.xro/2.0/Invoices/INV-0123',
			'query' => '(Type == "ACCREC")',
			'entities' => 'INV-0123',
			'error' => '',
			'created' => '2012-03-14 09:00:00',
			'modified' => '2012-03-14 09:00:00'
		),
		array(
			'id' => 'credit_notes_only_request',
			'organisation_id' => 'uptodate_org',
			'status' => 'SUCCESS',
			'endpoint' => 'api.xro/2.0/CreditNotes',
			'query' => '',
			'entities' => 'cnid1,cnid2,cnid3',
			'error' => '',
			'created' => '2012-03-15 09:00:00',
			'modified' => '2012-03-15 09:00:00'
		),
		array(
			'id' => 'invoices_only_failed_request',
			'organisation_id' => 'uptodate_org',
			'status' => 'FAILED',
			'endpoint' => 'api.xro/2.0/Invoices',
			'query' => '(Type == "ACCREC")',
			'entities' => '',
			'error' => 'ApiMethodNotImplementedException: The Api Method called is not implemented (20)',
			'created' => '2012-03-16 09:00:00',
			'modified' => '2012-03-16 09:00:00'
		),
	);

}
