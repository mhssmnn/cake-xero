<?php

class XeroRequest extends XeroAppModel {

	public $useDbConfig = 'default';

  public $order = array('XeroRequest.modified' => 'desc');

	public function lastSuccess($company_id, $endpoint, $query) {
		if (strpos('api.xro/2.0/', $endpoint) !== 0) {
			$endpoint = 'api.xro/2.0/'.$endpoint;
		}
		$result = $this->find('first', array(
			'fields' => array('created'),
			'conditions' => array(
				'status' => 'SUCCESS', 
				'entities !=' => null,
				'endpoint' => $endpoint,
				'query' => $query
			)
		));

		if ($result) {
			return gmdate('Y-m-d H:i:s', strtotime($result));
		}
		return false;
	}
}