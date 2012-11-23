<?php

App::uses('XeroAppModel', 'Xero.Model');

class XeroInvoice extends XeroAppModel {
	
	var $endpoint = 'Invoices';
	
	var $hasMany = array(
		'Xero.XeroLineItem'
	);
	var $belongsTo = array(
		'Xero.XeroContact'
	);
	
	var $_schema = array(
		'InvoiceID' => array('type' => 'primary_key'),
		'InvoiceNumber' => array('type' => 'string'),
		'Type' => array('type' => 'string'),
		'Date' => array('type' => 'datetime'),
		'DueDate' => array('type' => 'datetime'),
		'Status' => array('type' => 'string'),
		'LineAmountTypes' => array('type' => 'string'),
		'SubTotal' => array('type' => 'decimal'),
		'TotalTax' => array('type' => 'decimal'),
		'Total' => array('type' => 'decimal'),
		'UpdatedDateUTC' => array('type' => 'datetime'),
		'CurrencyCode' => array('type' => 'string'),
		'AmountDue' => array('type' => 'decimal'),
		'AmountPaid' => array('type' => 'decimal'),
		'AmountCredited' => array('type' => 'decimal'),
	);
}
