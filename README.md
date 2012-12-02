# CakePHP Xero API Plugin

This CakePHP Xero plugin provides access to the Xero API for Partner applications.

## Requirements

The master branch has the following requirements:

* CakePHP 2.1.0 or greater.
* PHP 5.3.0 or greater.
* CakeResque 2.2.1 or greater.
* OauthLib 1.1 (dev branch) or greater.

## Installation

* Clone/Copy the files in this directory into `app/Plugin/Xero`
* Ensure the plugin is loaded in `app/Config/bootstrap.php` by calling `CakePlugin::load(array('Xero' => array('bootstrap' => true)));`
* `Console/cake schema create -p Xero`
* Or `Console/cake Migrations.migration run all -p Xero` if you are using the [Migrations](https://github.com/CakeDC/migrations) plugin
* Copy your SSL certificates into `app/Plugin/Xero/Config/Certificates` and setup the bootstrap.php configuration to point to them. 


## Install Dependencies

* Clone [CakeResque](https://github.com/kamisama/Cake-Resque) into `app/Plugin/CakeResque` and run through the installation instructions.
* Clone [OauthLib](https://github.com/CakeDC/oauth_lib) into `app/Plugins/OauthLib`

## Contributing

Please.

# Documentation

## Configuration

The plugin has a few configuration settings. Settings are passed through the bootstrap.php file in the plugin.

### Configuring OAuth Settings

You can add your own Consumer Oauth tokens, SSL certificates and passphrase and the ability to turn on
or off logging of requests to the database - all in the bootstrap.php file.

	ConnectionManager::create('xero_partner', array(
			'datasource' => 'Xero.XeroSource',
			'oauth_consumer_key' 	=> 'BZEFQ3HWZBDWKFHNIGVBHFERRLPI0',
			'oauth_consumer_secret' => '0AENJTCSOAT6VCRYHNSQJ84KM3K1PWU',
			'ssl' => array(
				'publicCert' => '/path/to/partner.p12',
				'privateCert' => '/path/to/privatekey.pem',
				'privateCertPass' => 'PASSPHRASE'
			),
			'logRequests' => true
	));

#### OrganisationModel

Maps data received from Xero to a central organisation in your app.

	Configure::write('Xero', array(
		'OrganisationModel' => 'Company'
	));
	
This would, for example, allow you to retrieve an Invoice from the API and it will add the company_id foreign key.

### Callbacks

The XeroUpdateShell attempts to save Xero entities into your database using the equivaltent model. You can add a callback to your local model, that will allow you to alter the entity data before it is saved.

    beforeXeroSave(Array $entities)

Each models's `beforeXeroSave()` method is called before the model's `save()` method is called. `$entities` is the result of the API call to Xero for this particular endpoint.
