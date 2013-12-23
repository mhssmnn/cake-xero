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
class XeroUpdateShell extends Shell {
	var $uses = array('Xero.XeroOrganisation', 'Xero.XeroInvoice', 'Xero.XeroContact', 'Xero.XeroCreditNote');

/**
 * Checks if the organisation(s) is active and need to be updated, and if so
 * adds the organisation id to be updated to the organisation parameter.
 * If there are mulitple organisations to be updated then we add each to the queue
 * and stop this process with the success exit code.
 *
 * @return void
 */
	public function startup() {
		// If we are dealing with multiple organisations then enqueue separate instances
		// which will contain errors and issues to a single organisation.
		$organisations = $this->findOrganisations();
		if (count($organisations) !== 1) {
			foreach ($organisations as $organisation) {
				$this->enqueueUpdate($organisation['Organisation']['id']);
			}

			$this->_stop(0); // Exit with success
		}

		$organisation = reset($organisations);
		$this->params['organisation'] = $organisation['Organisation']['id'];
	}

/**
 * Subcommand "all" comes here. Runs updates for all entity types.
 *
 * @return void
 */
	public function all() {
		$this->contacts();
		$this->invoices();
		$this->credit_notes();
	}

/**
 * Subcommand "contact" comes here. Runs updates for contacts.
 *
 * @return void
 */
	public function contacts() {
		try {
			$conditions = array(
				'id' => $this->params['contact'],
				'modified_after' => $this->params['since']
			);
			$this->XeroContact->update($this->_getOrganisation(), $conditions);

			if ($this->params['fetch_invoices']) {
				$this->invoices();
			}
		} catch (Exception $e) {
			$this->_updateError($e->getMessage(), $e->getTraceAsString());
		}
	}

/**
 * Subcommand "invoice" comes here. Runs updates for invoices and lineitems.
 *
 * @return void
 */
	public function invoices() {
		try {
			$conditions = array(
				'id' => $this->params['invoice'],
				'modified_after' => $this->params['since'],
				'Type == "ACCREC"'
			);

			if ($this->params['paid_only']) {
				$conditions[] = 'Status == "PAID" OR Status == "VOIDED" OR Status == "DELETED"';
			}

			if (!empty($this->params['contact'])) {
				$contact_id = $this->params['contact'];
				$conditions[] = "Contact.ID == Guid({$contact_id})";
			}

			$invoices = $this->XeroInvoice->update($this->_getOrganisation(), $conditions);

			if (!is_array($invoices) || $this->params['no_line_item']) {
				return true;
			}

			foreach ($invoices as $invoice_id) {
				$conditions['id'] = $invoice_id;
				$this->XeroInvoice->update($this->_getOrganisation(), $conditions);
			}
		} catch (Exception $e) {
			$this->_updateError($e->getMessage(), $e->getTraceAsString());
		}
	}

/**
 * Subcommand "credit_note" comes here. Runs updates for credit notes.
 *
 * @return void
 */
	public function credit_notes() {
		try {
			$conditions = array(
				'id' => $this->params['credit_note'],
				'modified_after' => $this->params['since']
			);
			$this->XeroCreditNote->update($this->_getOrganisation(), $conditions);
		} catch (Exception $e) {
			$this->_updateError($e->getMessage(), $e->getTraceAsString());
		}
	}

/**
 * Subcommand "organisation" comes here. Runs updates for for an organisation.
 *
 * @return void
 */
	public function organisations() {
		try {
			$conditions = array(
				'modified_after' => $this->params['since']
			);
			$this->XeroOrganisation->update($this->_getOrganisation(), $conditions);
		} catch (Exception $e) {
			$this->_updateError($e->getMessage(), $e->getTraceAsString());
		}
	}

/**
 * Gets a list of ACTIVE organisations, filtered by the optional
 * organisation parameter.
 * Uses the Configured Organisation.Model to determine the local
 * model that stores the organisations.
 *
 * @return array Names and Ids of the organisations to update.
 */
	public function findOrganisations() {
		// Bind local organisation on the fly
		if (!isset($this->XeroOrganisation->Organisation)) {
			$this->XeroOrganisation->bindModel(array('hasOne' => array(
				'Organisation' => array(
					'className' => Configure::read('Xero.Organisation.Model')
				)
			)));
		}

		// Get organisation model from bind
		$Organisation =& $this->XeroOrganisation->Organisation;

		$Organisation->contain(array());
		$conditions = (array) Configure::read('Xero.Organisation.Filter');
		if (isset($this->params['organisation']) && $this->params['organisation']) {
			$conditions += array('id' => $this->params['organisation']);
		}

		$organisations = $Organisation->find('all', array(
			'fields' => array('id', 'name'),
			'conditions' => $conditions
		));

		if (!$organisations && isset($this->params['organisation']) && $this->params['organisation']) {
			$this->_organisation = array('Organisation' => array('id' => $this->params['organisation']));
			return array($this->_organisation);
		}

		return $organisations;
	}

/**
 * Adds an organisation to the CakeResque queue to be processed
 * by workers.
 *
 * @return void
 */
	public function enqueueUpdate($id) {
		$args = array(
			$this->command,
			"--organisation={$id}",
			"--since={$this->params['since']}"
		);

		if ($this->params['paid_only'] === true) {
			$args[] = '--paid_only';
		}

		CakeResque::enqueue('default', 'Xero.XeroUpdate', $args);
		CakeLog::write('xero_update', __("Queued: %s", $id));
	}

/**
 * Returns the current organisation, or retrieves it from the database
 * if it is not there already.
 * NB: we assume that one instance of XeroUpdateShell will always
 * relate to one organisation
 *
 * @return array The Organisation
 */
	private function _getOrganisation() {
		if (!isset($this->_organisation)) {
			$Organisation =& $this->XeroOrganisation->Organisation;
			$Organisation->contain(array());
			$this->_organisation = $Organisation->findById($this->params['organisation']);

			if ($this->_organisation === false) {
				throw new CakeException(__("Unable to find organisation with id: %s", $this->params['organisation']));
			}
		}

		return $this->_organisation;
	}

/**
 * Logs any error that occurs during an update and the triggers an error
 *
 * @return void
 */
	private function _updateError($errStr, $errTrace) {
		$organisation = $this->_getOrganisation();
    $timeCode = time();
    $error = sprintf("[%s] Error updating.\r\n%s\r\n[Trace]\r\n%s",
      @$organisation['Organisation']['name'],
      $errStr,
      $errTrace
    );
    $this->log($error, 'reports/'.$timeCode);
    trigger_error(sprintf("Error in updating %s: Report #%s", @$organisation['Organisation']['name'], $timeCode), E_USER_NOTICE);
  }

/**
 * Returns the option parser
 *
 * @return OptionParser
 */
	public function getOptionParser() {
	  $parser = parent::getOptionParser();

	  $genericArgs = array(
	  	'options' => array(
				'since' => array(
					'short' => 's',
					'help' => __d('xero_console', 'Run updates since specified date and time. (Format: Y-m-d H:i:s)'),
					'default' => 'last_update'
				),
				'organisation' => array(
					'short' => 'c',
					'help' => 'ID of specific organisation to process. Omit to run for all'
				)
			)
	  );

	  $organisationArgs = array();

	  $creditNoteArgs = array(
	  	'options' => array(
				'credit_note' => array(
					'short' => 'n',
					'help' => __d('xero_console', 'Only update specified credit notes. (Comma separated list)'),
					'default' => array()
				),
			)
	  );

	  $contactArgs = array(
	  	'options' => array(
				'contact' => array(
					'short' => 'C',
					'help' => __d('xero_console', 'Only update specified contacts. (Comma separated list)'),
					'default' => array()
				),
				'fetch_invoices' => array(
					'help' => __d('xero_console', 'Fetch invoices for contacts?'),
					'boolean' => true,
					'default' => false
				),
			)
	  );

	  $invoiceArgs = array(
	  	'options' => array(
				'invoice' => array(
					'short' => 'i',
					'help' => __d('xero_console', 'Only update specified invoices. (Comma separated list)'),
					'default' => array()
				),
				'contact' => array(
					'help' => __d('xero_console', 'Only update specified contacts invoices.')
				),
				'no_line_item' => array(
					'short' => 'l',
					'help' => __d('xero_console', 'Do not fetch any line-items for invoices?'),
					'boolean' => true,
					'default' => false
				),
				'paid_only' => array(
					'short' => 'p',
					'help' => __d('xero_console', 'Only update paid invoices.'),
					'boolean' => true
				),
			)
	  );

	  $parser
	  	->description(__d('xero_console', "A Shell to manage PHP Resque.\n"))
	  	->addSubcommand('all', array(
				'help' => __d('xero_console', 'Updates all endpoints available.'),
				'parser' => array_merge_recursive($invoiceArgs, $contactArgs, $creditNoteArgs, $genericArgs)
			))
	  	->addSubcommand('invoices', array(
				'help' => __d('xero_console', 'Updates only invoices.'),
				'parser' => array_merge_recursive($invoiceArgs, $genericArgs)
			))
	  	->addSubcommand('contacts', array(
				'help' => __d('xero_console', 'Updates only contacts.'),
				'parser' => array_merge_recursive($contactArgs, $genericArgs)
			))
	  	->addSubcommand('organisations', array(
				'help' => __d('xero_console', 'Updates organisational details (i.e. Country Code).'),
				'parser' => array_merge_recursive($organisationArgs, $genericArgs)
			))
	  	->addSubcommand('credit_notes', array(
				'help' => __d('xero_console', 'Updates only credit notes.'),
				'parser' => array_merge_recursive($creditNoteArgs, $genericArgs)
			));

	  return $parser;
	}
}