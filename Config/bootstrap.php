<?php
// Load database configuration
App::uses('ConnectionManager', 'Model');
ConnectionManager::create('xero_partner', array(
    'datasource' => 'Xero.XeroSource',
    // Consumer Oauth Tokens
		'oauth_consumer_key' 	=> '',
		'oauth_consumer_secret' => '',
		// Necessary SSL certificates and Passphrase
		'ssl' => array(
			'publicCert' => App::pluginPath('Xero') . 'Config' .DS. 'Certificates' .DS . 'partner.p12',
			'privateCert' => App::pluginPath('Xero') . 'Config' .DS. 'Certificates' .DS . 'privatekey.pem',
			'privateCertPass' => 'PASSPHRASE'
		),
		// Writes requests to the database, allowing us to track the last successful update
		'logRequests' => true
));

// For checking the API limit
Cache::config('xero_api_limit', array(
    'engine' => 'File',
    'duration' => '+1 minute',
    'path' => CACHE . 'xero',
    'prefix' => 'xero_api_limit_'
));

// Write configuration data
Configure::write('Xero', array(
	'OrganisationModel' => 'Company'
));