<?php

class XeroUpdate extends XeroAppModel {

	public $useDbConfig = 'default';

  public $order = array('XeroUpdate.modified' => 'desc');
	
}