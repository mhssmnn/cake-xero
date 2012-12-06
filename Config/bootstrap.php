<?php
// Load database configuration

App::uses('ConnectionManager', 'Model');

$CERTPATH = App::pluginPath('Xero') . 'Config' .DS. 'Certificates' .DS;
ConnectionManager::create('xero_partner', array(
    'datasource' => 'Xero.XeroSource',
		'oauth_consumer_key' 	=> 'EHYT7ZNVSAKWF3UHYEKS2F3I7RLPI0',
		'oauth_consumer_secret' => 'AINR0AVSOAT9HF64JMJ92QXMSK1PWU',
		'ssl' => array(
			'publicCert' => $CERTPATH . 'partner.p12',
			'privateCert' => $CERTPATH . 'privatekey.pem',
			'privateCertPass' => '3tr4cDEB'
		),
		'logRequests' => true
));


/**
 * Provide a data source to mock Xero.
 */
ConnectionManager::create('xero_test', array(
	'datasource' => 'Xero.XeroTestSource',
	'persistent' => false,
	'host' => '127.0.0.1',
	'login' => 'dev',
	'password' => '123',
	'database' => 'dd_tmp',
	'prefix' => '',
));

// For checking the API limit
Cache::config('xero_api_limit', array(
    'engine' => 'File',
    'duration' => '+1 minute',
    'path' => CACHE,
    'prefix' => 'xero_api_limit_'
));

// Write configuration data
Configure::write('Xero', array(
	'Organisation' => array(
		'Model' => 'Company',
		'Filter' => array('status' => 'ACTIVE')
	)
));