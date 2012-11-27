<?php
class ModifyCredentialsAndRequestsCompanyIdColumns extends CakeMigration {

/**
 * Migration description
 *
 * @var string
 * @access public
 */
	public $description = '';

/**
 * Actions to be performed
 *
 * @var array $migration
 * @access public
 */
	public $migration = array(
		'up' => array(
		),
		'down' => array(
		),
	);

/**
 * Before migration callback
 *
 * @param string $direction, up or down direction of migration process
 * @return boolean Should process continue
 * @access public
 */
	public function before($direction) {
		if ($direction == 'up') {
			$result = $this->db->query("DESCRIBE `xero_credentials`;");
			if ($result[1]['COLUMNS']['Field'] == 'company_id' && $result[2]['COLUMNS']['Field'] != 'organisation_id') {
				$this->db->query("ALTER TABLE `xero_credentials` CHANGE `company_id` `organisation_id` CHAR(36)  NOT NULL  DEFAULT '';");
			}
		} else {
			$result = $this->db->query("DESCRIBE `xero_credentials`;");
			if ($result[1]['COLUMNS']['Field'] == 'organisation_id') {
				$this->db->query("ALTER TABLE `xero_credentials` CHANGE `organisation_id` `company_id` CHAR(36)  NOT NULL  DEFAULT '';");
			}
		}
		return true;
	}

/**
 * After migration callback
 *
 * @param string $direction, up or down direction of migration process
 * @return boolean Should process continue
 * @access public
 */
	public function after($direction) {
		return true;
	}
}
