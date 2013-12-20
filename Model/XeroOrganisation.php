<?php

class XeroOrganisation extends XeroAppModel {

	var $endpoint = 'Organisation';
	var $localModel = 'Company';

	public function update($organisation, $conditions = array()) {
		if (empty($organisation)) {
			return false;
		}

		if (!is_array($organisation) || !isset($organisation['Organisation'])
			|| !is_array($organisation['Organisation'])) {
			throw new XeroBadParametersException("XeroAppMode::update expected an Organisation record from the DB");
		}

		$organisation_id = $organisation['Organisation']['id'];

		$this->_setDatasourceCredentialsFromOrganisationId($organisation_id);
		$this->_parseConditions($organisation_id, $conditions);

		$entity = $this->find('first', compact('conditions'));

		App::uses($this->localModel, 'Model');
		$this->{$this->localModel} = new $this->localModel;

		if (method_exists($this->{$this->localModel}, 'beforeXeroSave')) {
			$entity = $this->{$this->localModel}->beforeXeroSave($entity);
		}

		$entity[$this->localModel]['id'] = $organisation_id;

		if (!$this->{$this->localModel}->save($entity, array('validate' => false))) {
			$id = Hash::get($entity, "{$this->localModel}.id") ?: "!UNKNOWN ID!";

			throw new XeroIOException(
				sprintf("Unable to update invoice (%s): %s", $id, print_r($entity, true)), E_USER_NOTICE
			);
		}

		if (empty($entity)) {
			return array();
		}

		return array($organisation_id);
	}

}