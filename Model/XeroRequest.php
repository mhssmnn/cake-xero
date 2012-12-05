<?php

App::uses('XeroAppModel', 'Xero.Model');

class XeroRequest extends XeroAppModel {

	public $useDbConfig = 'default';

  public $order = array('XeroRequest.modified' => 'desc');

	public function lastSuccess($organisation_id, $endpoint = null, $query = null) {
		$conditions = array('organisation_id' => $organisation_id, 'status' => 'SUCCESS', 'entities !=' => null);

		if ($endpoint !== null) {
			if (strpos('api.xro/2.0/', $endpoint) !== 0) {
				$endpoint = 'api.xro/2.0/'.$endpoint;
			}
			$conditions += compact('endpoint');
		}

		if ($query !== null) {
			$conditions += compact('query');
		}

		$result = $this->find('first', array( 'fields' => array('created'), 'conditions' => $conditions ));
		if ($result) {
			return gmdate('Y-m-d H:i:s', strtotime($result['XeroRequest']['created']));
		}
		return false;
	}
}