<?php
/**
 * This is the Xero Updates Schema file
 *
 * Use it to configure database for the Xero plugin
 *
 * PHP 5
 */

/*
 *
 * Using the Schema command line utility
 * cake schema run create XeroUpdates
 *
 */
class XeroRequestsSchema extends CakeSchema {

	public $name = 'XeroRequests';

	public function before($event = array()) {
		return true;
	}

	public function after($event = array()) {
	}

	public $xero_requests = array(
			'id' => array('type' => 'uuid', 'null' => false, 'default' => NULL, 'length' => 36, 'key' => 'primary', 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'organisation_id' => array('type' => 'uuid', 'null' => false, 'default' => NULL, 'length' => 36, 'key' => 'index', 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'status' => array('type' => 'string', 'null' => true, 'length' => 25, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'endpoint' => array('type'=>'string', 'null' => true, 'length' => 25, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'entities' => array('type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'error' => array('type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'created' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
			'modified' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
			'indexes' => array(
				'PRIMARY' => array('column' => 'id', 'unique' => 1),
				'organisation_id' => array('column' => 'company_id', 'unique' => 0),
			),
			'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'MyISAM'),
		);

}