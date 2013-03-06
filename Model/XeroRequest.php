<?php

App::uses('XeroAppModel', 'Xero.Model');

class XeroRequest extends XeroAppModel {

	public $useDbConfig = 'default';

  public $order = array('XeroRequest.modified' => 'desc');

	public function lastSuccess($organisation_id, $endpoint = null, $query = null) {
		$conditions = array('organisation_id' => $organisation_id, 'status' => 'SUCCESS', 'entities !=' => null);
		return $this->_lastRecordDate($conditions, $endpoint, $query);
	}

	public function lastUpdateDate($organisation_id, $endpoint = null, $query = null) {
		$conditions = array('organisation_id' => $organisation_id, 'status' => 'SUCCESS');
		return $this->_lastRecordDate($conditions, $endpoint, $query);
	}

	protected function _lastRecordDate($conditions, $endpoint = null, $query = null) {
		if ($endpoint !== null) {
			if (!is_array($endpoint)) {
				$endpoint = (array) $endpoint;
			}
			foreach ($endpoint as &$end) {
				if (strpos('api.xro/2.0/', $end) !== 0) {
					$end = 'api.xro/2.0/'.$end;
				}
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