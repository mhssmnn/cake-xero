<?php
/**
 * CakePHP Datasource for accessing the Xero API
 *
 * @author Mark Haussmann <mark@debtordaddy.com>
 * @link http://www.debtordaddy.com
 * @copyright (c) 2012 Mark Haussmann
 * @license MIT License - http://www.opensource.org/licenses/mit-license.php
 */

App::uses('Datasource', 'Model/Datasource');
App::uses('OauthHelper', 'OauthLib.Lib');
App::uses('XeroConsumer', 'Xero.Lib/Oauth');
App::uses('RequestToken', 'OauthLib.Lib');
App::uses('RequestFactory', 'OauthLib.Lib');
App::uses('CurlSocket', 'Xero.Lib/Network/Http');
App::uses('Xml', 'Utility');

/**
	* XeroSource
	*
	* Provides connection and query generation for the Xero API
	*
	* @package       Xero.Model.Datasource
	*/
class XeroSource extends DataSource {

/**
 * Datasource description
 *
 * @var string
 */
	public $description = "Xero API Datasource";

/**
 * The default values to use for a request
 *
 * @var array
 * @access public
 */
	public $request = array(
		'method' => 'GET',
		'uri' => array(
			'scheme' => 'https',
			'host' => 'api-partner.network.xero.com',
			'port' => 443,
			'user' => null,
			'pass' => null,
			'path' => null,
			'query' => null,
			'fragment' => null
		),
		'auth' => array(
			'method' => 'OAuth',
			'oauth_signature_method' => 'RSA-SHA1',
			'oauth_consumer_key' => null,
			'oauth_consumer_secret' => null,
			'oauth_token' => null,
			'oauth_token_secret' => null,
		),
		'ssl' => array(
			'private_key' => null,
			'certificate' => null,
			'password' => null
		),
		'version' => '1.1',
		'body' => '',
		'line' => null,
		'header' => array(),
		'raw' => null,
		'cookies' => array()
	);


/**
 * Base configuration settings for Xero API
 *
 * @var array
 */
	protected $_baseConfig = array(
		'oauth_consumer_key' 		=> '',
		'oauth_consumer_secret' => '',
		'logRequests' => false
	);

/**
 * Access Token credentials
 *
 * @var array
 */
	public $credentials = null;

/**
 * Constructor
 *
 * @param array $config Array of configuration information for the Datasource.
 * @throws MissingConnectionException when a connection cannot be made.
 */
	public function __construct($config = null) {
		$config = array_merge((array)$config, (array) Configure::read('Xero'));
		parent::__construct($config);
	}

/**
 * Load credentials from id or returns credentials if no id provided
 * @return array Array from DB record
 */
	public function credentials($id = null) {
		if ($id !== null) {
			if (!isset($this->XeroCredential)) {
				$this->XeroCredential =& ClassRegistry::init('Xero.XeroCredential');
			}

			if (!is_array($id)) {
				$id = compact('id');
			}

			$this->credentials = $this->XeroCredential->find('first', array('conditions' => $id));
		}
		return $this->credentials;
	}

/**
 * Return request options array with SSL certificates set
 * for OAuth token signing.
 */
	public function sslRequestOptions() {
		if (empty($this->config['ssl'])) {
			return array();
		}
		return array(
			'publicCert' => $this->config['ssl']['publicCert'],
			'privateCert' => 'file:///'.$this->config['ssl']['privateCert'],
			'privateCertPass' => ''
		);
	}

/**
 * listSources() is for caching. Will implement caching in own way
 * because of Xero as Datasource. So just ``return null``.
 */
	public function listSources($data = null) {
			return null;
	}

/**
 * Tells the model your schema for ``Model::save()``.
 *
 * You may want a different schema for each model but still use a single
 * datasource. If this is your case then set a ``schema`` property on your
 * models and simply return ``$model->schema`` here instead.
 */
	public function describe($model) {
			return $model->_schema;
	}

/**
 * calculate() is for determining how we will count the records and is
 * required to get ``update()`` and ``delete()`` to work.
 *
 * We don't count the records here but return a string to be passed to
 * ``read()`` which will do the actual counting. The easiest way is to just
 * return the string 'COUNT' and check for it in ``read()`` where
 * ``$data['fields'] == 'COUNT'``.
 */
	public function calculate(Model $model, $func, $params = array()) {
			return 'COUNT';
	}

/**
 * Does the actual request
 * @return array Response body
 */
	public function request($request, $depth = 0) {
		$method = strtoupper($request['method']);
		$AccessToken = $this->getAccessToken($request);

		if ($this->credentialsHaveExpired()) {
			$this->renewAccessToken($AccessToken);
		}

		$uri = $AccessToken->consumer->http->url($request['uri']);
		$params = $AccessToken->consumer->http->config;

		if (!isset($request['header'])) {
			$request['header'] = array();
		}

		// Check if we need to record the start
		if (Cache::read($AccessToken->token, 'xero_api_limit') === false) {
			Cache::write($AccessToken->token, microtime(true), 'xero_api_limit');
		}

		$t = microtime(true);
		$response = $AccessToken->request($method, $uri, $request['header'], $params, $this->sslRequestOptions());
		$this->took = round((microtime(true) - $t) * 1000, 0);

		switch ($response->code) {
			case XeroResponseCode::BAD_REQUEST: // Request was not valid
					$response->body = @Xml::toArray(@Xml::build($response->body));
			break;
			case XeroResponseCode::UNAUTHORIZED: // Usually because token has expired
					if ($depth < 50) {
						sleep(1);
						return $this->request($request, ++$depth);
					}
			break;
			case XeroResponseCode::FORBIDDEN:
					throw new XeroSSLValidationException('The SSL certificate was rejected by Xero');
			break;
			case XeroResponseCode::NOT_FOUND:
					// The resource you're looking for cannot be found
			break;
			case XeroResponseCode::NOT_IMPLEMENTED: // The method has not been implemented (e.g. POST Organisation)
					$response->body = @Xml::toArray(@Xml::build($response->body));
			break;
			case XeroResponseCode::NOT_AVAILABLE:
			case XeroResponseCode::RATE_LIMIT_EXCEEDED:
					if ($depth < 50 && $response->body != 'The Xero API is currently offline for maintenance') {
						$timeStart = Cache::read($AccessToken->token, 'xero_api_limit');
						$sleepTime = round(microtime(true) - $timeStart+60, 0);
						CakeLog::write('xero_api', "Rate Limit Exceeded for {$AccessToken->token}. Waiting for {$sleepTime}s");
						@time_sleep_until($timeStart+60);
						return $this->request($request, ++$depth);
					}
			break;
			case XeroResponseCode::SUCCESS:
			default:
					if (!empty($response->body)) {
						$response->body = Xml::toArray(Xml::build($response->body));
						$response = $this->_parseResponse($response);
					}
		}

		$this->logRequest($request, $response);

		return $response->body;
	}

/**
 * Parses the response from Xero to provide a CakePHP structured
 * array.
 * @return array $response
 */
	protected function _parseResponse(&$response) {
		if (!isset($response->body['Response'])) {
			return $response;
		}

		$response->body = array_diff_key(
			$response->body['Response'], array_flip(array('Id', 'Status', 'ProviderName', 'DateTimeUTC'))
		);

		if (empty($response->body)) {
			return $response;
		}

		// Get singular entity key, this is used to provide
		// a CakePHP style array structure
		$entity = reset(array_keys(reset($response->body)));
		// Unwrap plural and singular entity
		$response->body = reset(reset($response->body));

		// Make sure the array returned is always in the plural form.
		// If the response is a single entity it wont be. This gets
		// removed later if the call was made with Model::findFirst
		if (!isset($response->body[0])) {
			$response->body = array($response->body);
		}

		// Return CakePHP structured array
		$entity_id_key = Inflector::underscore($entity).'_id';
		for($i = 0; $i < count($response->body); $i++) {
			$response->body[$i] = array(
				$entity => $this->underscorize($response->body[$i], $entity_id_key)
			);
		}

		return $response;
	}

/**
 * Helper function that underscoreizes and cakeizes array keys
 * @return array $array
 */
	private function underscorize($input, $entity) {
		$return = array();
    foreach ($input as $key => $value) {
    	if (is_array($value)) {
    		$value = $this->underscorize($value, Inflector::underscore($key).'_id');
    	} elseif (is_string($key)) {
    		$key = str_replace(
    			array("_i_d", "_u_t_c", $entity),
    			array('_id', '_utc', 'id'),
    			Inflector::underscore($key));
    	}
    	$return[$key] = $value;
    }
    return $return;
	}

/**
 * Creates an access token out of a request.
 * @return AccessToken $AccessToken
 */
	public function getAccessToken($request = array()) {
		if (is_string($request)) {
			$request = array('uri' => array('path' => $request));
		}

		$request = Set::merge(
			$this->request,
			array('ssl' => $this->sslRequestOptions()),
			$request
		);
		$credentials = $this->credentials();

		return $this->consumer()->getAccessToken($request, $credentials);
	}

/**
 * Creates a request token out of a request.
 * @return RequestToken $RequestToken
 */
	public function getRequestToken($oauth_callback) {
		$request = Set::merge(
			$this->request,
			array('ssl' => $this->sslRequestOptions())
		);
		return $this->consumer()->getRequestToken($request, $oauth_callback);
	}

/**
 * Renews the current AccessToken by requesting a renewal from Xero.
 * Modifies the AccessToken passed in.
 */
	public function renewAccessToken(&$AccessToken) {
		$token = $this->getAccessToken($AccessToken->consumer->accessTokenUrl());
		$token = $token->consumer->renewAccessToken($token, $this->sslRequestOptions());

		// Replace current AccessToken with new credentials
		$AccessToken->token = $token['oauth_token'];
		$AccessToken->tokenSecret = $token['oauth_token_secret'];

		// Save new credentials to the database
		$credentials = $this->credentials();
		$this->XeroCredential->save(array_merge($credentials['XeroCredential'], array(
			'key' => $token['oauth_token'],
			'secret' => $token['oauth_token_secret'],
			'expires' => date('Y-m-d H:i:s', time() + $token['oauth_expires_in']),
		)));
		$this->credentials($credentials['XeroCredential']['id']);
	}

/**
 * To help with testing
 *
 * @param array $config Config array to be merged with $this->config
 * @return CurlSocket
 */
	public function socket($config = array()) {
		return new CurlSocket($config + $this->config);
	}

/**
 * To help with testing
 *
 * @param array $config Config array to be merged with $this->config
 * @return CurlSocket
 */
	public function consumer($socket = null, $oauth_consumer_key = null, $oauth_consumer_secret = null) {
		$socket = $socket ?: $this->socket();
		$oauth_consumer_key = $oauth_consumer_key ?: $this->config['oauth_consumer_key'];
		$oauth_consumer_secret = $oauth_consumer_secret ?: $this->config['oauth_consumer_secret'];
		return new XeroConsumer($socket, $oauth_consumer_key, $oauth_consumer_secret);
	}

/**
 * Implement the R in CRUD. Calls to ``Model::find()`` arrive here.
 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		/**
		 * Here we do the actual count as instructed by our calculate()
		 * method above. We could either check the remote source or some
		 * other way to get the record count. Here we'll simply return 1 so
		 * ``update()`` and ``delete()`` will assume the record exists.
		 */
		if ($queryData['fields'] == 'COUNT') {
				return array(array(array('count' => 1)));
		}

		$request = $this->request;
		if (isset($model->request)) {
			$request = array_merge_recursive($request, $model->request);
		}

		$request = $this->_buildRequestUri($request, $queryData, $model);

		return $this->request($request);
	}

/**
 * Builds the request URI - appends specific Xero endpoint URL paths, filters
 * out special conditions that Xero handles differently.
 * @return array $request The formatted request array
 */
	protected function _buildRequestUri($request, &$queryData, Model $model) {
		if (!isset($model->localModel)) {
			$model->localModel = str_replace("Xero", "", $model->alias);
		}

		if (!isset($model->endpoint)) {
			$model->endpoint = Inflector::pluralize($model->localModel);
		}

		if (!empty($model->endpoint)) {
			$request['uri']['path'] = 'api.xro/2.0/' . $model->endpoint;
		} else {
			throw new CakeException("Missing endpoint declaration (".get_class($model)."::endpoint).");
		}

		if (!empty($queryData['conditions'])) {

			// Any id condition is treated as a Xero endpoint path e.g. one can pass in
			// a contact's number as the id and it will work for the Contacts endpoint
			if (isset($queryData['conditions']['id'])) {
				if (is_string($queryData['conditions']['id']) && !empty($queryData['conditions']['id'])) {
					$request['uri']['path'] .= '/' . $queryData['conditions']['id'];
				}
				unset($queryData['conditions']['id']);
			}

			// Xero uses the If-Modified-Since header as a special filter
			if (isset($queryData['conditions']['modified_after'])) {
				$request['header']['If-Modified-Since'] = $queryData['conditions']['modified_after'];
				unset($queryData['conditions']['modified_after']);
			}

			// Xero has a special parameter for Contacts
			if (isset($queryData['conditions']['includeArchived'])) {
				$queryData['includeArchived'] = $queryData['conditions']['includeArchived'];
				unset($queryData['conditions']['includeArchived']);
			}

		}

		$request['uri']['query'] = $this->_filterQuery($queryData, $model);

		return $request;
	}

/**
 * Will filter query options that do not work with the Xero API
 * but that may have been added from the CakePHP default options.
 * Usually called from XeroSource::_buildRequestUri().
 * @return Array $query
 */
	protected function _filterQuery($query, Model $model) {
		$base = array_fill_keys(array('conditions', 'order'), array());
		$query = array_intersect_key((array)$query, $base);

		$query['where'] = $this->conditions($query['conditions']);
		unset($query['conditions']);

		return $query;
	}

/**
 * Creates a WHERE clause by parsing given conditions data.  If an array or string
 * conditions are provided those conditions will be parsed and quoted.  If a boolean
 * is given it will be integer cast as condition.  Null will return 1 = 1.
 *
 * Results of this method are stored in a memory cache.  This improves performance, but
 * because the method uses a simple hashing algorithm it can infrequently have collisions.
 * Setting DboSource::$cacheMethods to false will disable the memory cache.
 *
 * @param mixed $conditions Array or string of conditions, or any value.
 * @return string SQL fragment
 */
	public function conditions($conditions) {
		$out = '';
		$bool = array('and', 'or', 'not', 'and not', 'or not', 'xor', '||', '&&');

		if (is_array($conditions) && !empty($conditions)) {
			foreach($conditions as $key => $value) {
				if (is_numeric($key) && !empty($value)) {
					$out[] = $value;
				} else {
					$out[] = $this->key($key) . $this->value($value);
				}
			}

			if (empty($out)) {
				return null;
			}
			return '(' . implode(') AND (', $out) . ')';
		}

		return $conditions;
	}


/**
 * Extracts a Model.field identifier and an SQL condition operator from a string, formats
 * and inserts values, and composes them into an SQL snippet.
 *
 * @param Model $model Model object initiating the query
 * @param string $key An SQL key snippet containing a field and optional SQL operator
 * @param mixed $value The value(s) to be inserted in the string
 * @return string
 */
	protected function key($key) {
		$operatorMatch = '/^(<[>=]?(?![^>]+>)\\x20?|[>=!]{1,3}(?!<)\\x20?)/is';

		if (strpos($key, ' ') === false) {
			$operator = '=';
		} else {
			list($key, $operator) = explode(' ', trim($key), 2);
		}

		if (!preg_match($operatorMatch, trim($operator))) {
			$operator .= ' ==';
		}
		$operator = trim($operator);

		return "{$key} {$operator} ";
	}


/**
 * Returns a quoted and escaped string of $data for use in an SQL statement.
 *
 * @param string $data String to be prepared for use in an SQL statement
 * @param string $column The column into which this data will be inserted
 * @return string Quoted and escaped data
 */
	public function value($data, $column = null) {
		if (is_array($data) && !empty($data)) {
			return array_map(
				array(&$this, 'value'),
				$data, array_fill(0, count($data), $column)
			);
		}

		if ($data === null || (is_array($data) && empty($data))) {
			return 'NULL';
		}

		if (empty($column)) {
			$column = $this->introspectType($data);
		}

		switch ($column) {
			case 'boolean':
				return $data ? "true" : "false";
			break;
			case 'string':
			case 'text':
				return '"' . addslashes($data) . '"';
			default:
				if ($data === '') {
					return 'NULL';
				}
				if (is_float($data)) {
					return str_replace(',', '.', strval($data));
				}
				if ((is_int($data) || $data === '0') || (
					is_numeric($data) && strpos($data, ',') === false &&
					$data[0] != '0' && strpos($data, 'e') === false)
				) {
					return $data;
				}
				return '"' . addslashes($data) . '"';
			break;
		}
	}

/**
 * Guesses the data type of an array
 *
 * @param string $value
 * @return void
 */
	public function introspectType($value) {
		if (!is_array($value)) {
			if (is_bool($value)) {
				return 'boolean';
			}
			if (is_float($value) && floatval($value) === $value) {
				return 'float';
			}
			if (is_int($value) && intval($value) === $value) {
				return 'integer';
			}
			if (is_string($value) && strlen($value) > 255) {
				return 'text';
			}
			return 'string';
		}
	}

/**
 * Tests to see if the current credentials have expired.
 * @return Boolean
 */
	public function credentialsHaveExpired() {
		$credentials = $this->credentials();
		return ( strtotime($credentials['XeroCredential']['expires']) < time() );
	}

/**
 * Logs the request in the local database.
 * Can be used for a modified_after date based on entity type.
 */
	public function logRequest($request, $response) {
		if (!$this->config['logRequests']) {
			return;
		}

		$verbose = ($this->config['logRequests'] === 'VERBOSE');
		$error = $entities = '';
		$credentials = $this->credentials();
		$status = ($response->code != XeroResponseCode::SUCCESS) ? 'FAILED' : 'SUCCESS';

		if ($response->code == XeroResponseCode::SUCCESS) {
			if (is_array($response->body)) {
				$entities = Set::classicExtract($response->body, '{n}.{s}.id');
				$entities = @implode(",", Set::flatten($entities));
			}
		} else {
			CakeLog::write('xero_api_error', "Error:\n".print_r($request, true) . "\n\n" . print_r($response, true));
			if (is_array($response->body) && isset($response->body['ApiException'])) {
				$error = __("%s: %s (%s)", $response->body['ApiException']['Type'], $response->body['ApiException']['Message'], $response->body['ApiException']['ErrorNumber']);
			} else {
				$error = __("%s %s:\n%s", $response->code, $response->reasonPhrase, $response->body);
			}
		}

		if ($verbose) {
			$error = json_encode( compact('request', 'response') );
		}

		if (!isset($this->XeroRequest)) {
			$this->XeroRequest =& ClassRegistry::init('Xero.XeroRequest');
		}
		$this->XeroRequest->create();
		$this->XeroRequest->save(array(
			'organisation_id' => $credentials['XeroCredential']['organisation_id'],
			'status' => $status,
			'endpoint' => $request['uri']['path'],
			'query' => implode(",", (array) $request['uri']['query']['where']),
			'entities' => $entities,
			'error' => $error
		));
	}

}


class XeroResponseCode {
	const SUCCESS = 200;
	const BAD_REQUEST = 400;
	const UNAUTHORIZED = 401;
	const FORBIDDEN = 403;
	const NOT_FOUND = 404;
	const NOT_IMPLEMENTED = 501;
	const NOT_AVAILABLE = 503;
	const RATE_LIMIT_EXCEEDED = 503;
}
class XeroSSLValidationException extends CakeException {}