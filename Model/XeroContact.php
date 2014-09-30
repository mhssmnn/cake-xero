<?php

App::uses('XeroAppModel', 'Xero.Model');

class XeroContact extends XeroAppModel {

	var $endpoint = 'Contacts';

	var $hasMany = array(
		'Xero.XeroInvoice'
	);

	public $excludeFromModifiedAfterQuery = array('id', 'modified_after', 'includeArchived');
}
