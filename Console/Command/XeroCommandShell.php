<?php

/**
 * Xero Update Shell
 *
 * PHP 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 2.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Shell', 'Console');

/**
 * Update shell allows us to update an organisations data retrieved from Xero
 *
 * @package       Xero.Console
 */
class XeroCommandShell extends Shell {
	var $uses = array('Xero.XeroOrganisation');

	public function startup() {
		App::uses('ClassRegistry', 'Utility');

		$prefix = 'Xero.Xero';
		$endpoint = $this->params['endpoint'];
		$subendpoint = '';

		if (strpos($endpoint,".") !== false) {
			list($endpoint, $subendpoint) = explode('.', $endpoint);
		}

		$uses = array($prefix . ucfirst(
			Inflector::singularize( $endpoint )
		));

		$modelClassName = $uses[0];
		if (strpos($uses[0], '.') !== false) {
			list($plugin, $modelClassName) = explode('.', $uses[0]);
		}
		$this->modelClass = $modelClassName;

		foreach ($uses as $modelClass) {
			list($plugin, $modelClass) = pluginSplit($modelClass, true);
			$this->{$modelClass} = ClassRegistry::init($plugin . $modelClass);
		}

		$this->{$modelClass}->endpoint = ucfirst( $endpoint );

		if ($subendpoint) {
			$this->{$modelClass}->endpoint .= '/' . $subendpoint;
		}
	}

/**
 * Subcommand "contact" comes here. Runs updates for contacts.
 *
 * @return void
 */
	public function send() {
		$organisation_id = $this->params['organisation'];
		$conditions = array('conditions' => $this->_conditions());
		$endpoint = $this->{$this->modelClass};

		$endpoint->getDatasource()->credentials(array('organisation_id' => $organisation_id));
		$result = $endpoint->find('all', $conditions);

		if (isset($this->params['pluck'])) {
			$result = Set::classicExtract($result, $this->params['pluck']);
		}

		$this->out( json_encode($result) );
	}

/**
 * Helper function that merges conditions passed in with default conditions
 *
 * @return array
 */
	private function _conditions($conditions = array()) {
		if (!empty($this->params['conditions'])) {
			$conditions = array_merge($conditions, (array) $this->params['conditions']);
		}
		return $conditions;
	}

/**
 * Returns the option parser
 *
 * @return OptionParser
 */
	public function getOptionParser() {
	  $parser = parent::getOptionParser();

	  $sendParser = array(
	  	'options' => array(
				'endpoint' => array(
					'help' => __d('xero_console', 'Endpoint to use'),
					'default' => 'contacts'
				),
				'conditions' => array(
					'help' => __d('xero_console', 'Adds additional conditions to request'),
					'default' => ''
				),
				'organisation' => array(
					'short' => 'c',
					'help' => 'ID of specific organisation to process'
				),
				'pluck' => array(
					'help' => 'Path in result array to pluck'
				)
			)
	  );

	  $parser
	  	->description(__d('xero_console', "A Shell to manage PHP Resque.\n"))
	  	->addSubcommand('send', array(
				'help' => __d('xero_console', 'Updates all endpoints available.'),
				'parser' => $sendParser
			));

	  return $parser;
	}
}