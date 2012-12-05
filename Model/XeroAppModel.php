<?php

App::uses('AppModel', 'Model');

class XeroAppModel extends AppModel {
  public $useDbConfig = 'xero_partner';

	private $_findMethods = array(
		'first' => true,
		'all' => true,
	);

/**
 * Automagic Update method. Provides a basic update mechanism, to be 
 * overloaded where necessary.
 * @param array $organisation The organisation that the update relates to
 * @param array $conditions Optional query conditions
 * @return array Ids of the entity updated
 */
	public function update($organisation, $conditions = array()) {
		if (empty($organisation)) {
			return false;
		}

		$organisation_id = $organisation['Organisation']['id'];

		if (!isset($this->localModel)) {
			$this->localModel = str_replace("Xero", "", $this->alias);
		}

		if (!isset($this->endpoint)) {
			$this->endpoint = Inflector::pluralize($this->localModel);
		}

		$this->_setDatasourceCredentialsFromOrganisationId($organisation_id);
		$this->_parseConditions($organisation_id, $conditions);

		if (!empty($conditions['id'])) {
			$entities = array();
			foreach ((array)$conditions['id'] as $id) {
				$entities[] = $this->find('first', array('conditions' => $conditions + compact('id')));
			}
		} else {
			$entities = $this->find('all', compact('conditions'));
		}

		$this->_saveAsLocalModel($organisation_id, $entities);
		
		return Set::flatten(Set::classicExtract($entities, "{n}.{s}.id"));
	}

/**
 * Parses the conditions and alters values where necessary:
 * 		- adds a last modified condition if requested.
 * 		- explodes Ids if necessary
 * @param array $organisation The organisation that the update relates to
 * @param array $conditions Query conditions
 * @return void
 */
	private function _parseConditions($organisation_id, &$conditions) {
		if (!empty($conditions['modified_after'])) {
			if (!isset($this->XeroRequest)) {
				$this->XeroRequest = ClassRegistry::init('Xero.XeroRequest');
			}
			
			if ($conditions['modified_after'] == 'last_update') {
				$query = $this->getDatasource()->conditions(array_diff_key(array('id'=>'', 'modified_after'=>''), $conditions));
				$lastUpdate = $this->XeroRequest->lastSuccess($organisation_id, $this->endpoint, $query);
				if ($lastUpdate) {
					$conditions['modified_after'] = $lastUpdate;
				}
			}
			
		}
		if (!empty($conditions['id']) && is_string($conditions['id']) && strpos(",",$conditions['id']) !== false) {
			$conditions['id'] = explode(',', $conditions['id']);
		}
	}

/**
 * Saves the retrieved data to a local model (if one exists).
 * Calls the callback method "beforeXeroSave" if it has been implemented.
 * 
 * @param array $organisation The organisation that the update relates to
 * @param array $entities entity data retrieved from the update.
 * @return void
 */
	private function _saveAsLocalModel($organisation_id, $entities) {
		App::uses($this->localModel, 'Model');

		if (class_exists($this->localModel)) {
			$model = new $this->localModel;
			foreach ($entities as $entity) {
				$localOrgForeignKey = Inflector::underscore(Configure::read('Xero.OrganisationModel')).'_id';
				$entity[$this->localModel][$localOrgForeignKey] = $organisation_id;

				if (method_exists($model, 'beforeXeroSave')) {
					$entity = $model->beforeXeroSave($entity);
				}

				if (!$model->saveAll($entity)) {
					throw new Exception(
						sprintf(
							"Unable to update invoice (%s): %s", $entity[$this->localModel]['id'], print_r($entity, true)
						), E_USER_NOTICE
					);
				}
			}
		}
		
		return true;
	}

/**
 * Sets the credentials in the datasource.
 * 
 * @param string $id The organisation id
 * @return void
 */
	private function _setDatasourceCredentialsFromOrganisationId($id) {
		$this->getDatasource()->credentials(array('organisation_id' => $id));
	}
}