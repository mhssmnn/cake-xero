<?php
/**
 * This is the Xero Credentials Schema file
 *
 * Use it to configure database for the Xero plugin
 *
 * PHP 5
 */

/*
 *
 * Using the Schema command line utility
 * cake schema run create XeroCredentials
 *
 */
class XeroCredentialsSchema extends CakeSchema {

	public $name = 'XeroCredentials';

	public function before($event = array()) {
		return true;
	}

	public function after($event = array()) {
	}

	public $xero_credentials = array(
			'id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'key' => 'primary'),
			'organisation_id' => array('type' => 'uuid', 'null' => false, 'default' => NULL, 'length' => 36, 'key' => 'index', 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'key' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 40, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'secret' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 40, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'session_handle' => array('type' => 'string', 'null' => false, 'default' => NULL, 'length' => 40, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
			'expires' => array('type' => 'datetime', 'null' => false, 'default' => '0000-00-00 00:00:00'),
			'created' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
			'modified' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
			'indexes' => array(
				'PRIMARY' => array('column' => 'id', 'unique' => 1),
				'organisation_id' => array('column' => 'company_id', 'unique' => 0),
			),
			'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'MyISAM'),
		);

}
