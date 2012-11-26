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
		$config = array_merge($config, (array) Configure::read('Xero'));
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

		// Check if we need to record the start
		if (Cache::read($AccessToken->token, 'xero_api_limit') === false) {
			Cache::write($AccessToken->token, microtime(true), 'xero_api_limit');
		}

		$t = microtime(true);
		$response = $AccessToken->request($method, $uri, $params['request']['header'], $params, $this->sslRequestOptions());
		$this->took = round((microtime(true) - $t) * 1000, 0);

		// Token has expired - sleep for a second and then re-call
		if ($response->code == XeroResponseCode::UNAUTHORIZED && $depth < 50) {
			// is the problem because of the token?
			sleep(1);
			return $this->request($request, ++$depth);
		}

		// Rate limit exceeded - wait before requesting
		// Only recurse 10 times?
		if ($response->code == XeroResponseCode::RATE_LIMIT_EXCEEDED && $depth < 50) {
			$timeStart = Cache::read($AccessToken->token, 'xero_api_limit');
			$sleepTime = round(microtime(true) - $timeStart+60, 0);
			CakeLog::write('xero_api', "Rate Limit Exceeded for {$AccessToken->token}. Waiting for {$sleepTime}s");

			@time_sleep_until($timeStart+60);
			return $this->request($request, ++$depth);
		}

		if ($response->code == XeroResponseCode::SUCCESS && !empty($response->body)) {
			$response->body = Xml::toArray(Xml::build($response->body));
			$response = $this->_parseResponse($response);
		}

		// Log request
		$this->_logRequest($request, $response);

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
		for($i = 0; $i < count($response->body); $i++) {
			$response->body[$i] = array($entity => $response->body[$i]);
		}

		$underscorize = function($arr) use (&$underscorize, &$entity) {
			$rarr = array();
			$find = array("_i_d", "_u_t_c", Inflector::underscore($entity) . '_id');
			$repl = array('_id', '_utc', 'id');
			foreach ($arr as $k => $v) {
				$_k = $k;
				if (is_string($k) && !is_array($v)) {
					$_k = str_replace($find, $repl, Inflector::underscore($k));
				}
				$rarr[$_k] = is_array($v) ? $underscorize($v, $_k) : $v;
			}
			return $rarr;
		};
		$response->body = $underscorize($response->body);

  	return $response;
  }

/**
 * Creates an access token out of a request.
 * @return AccessToken $AccessToken
 */
  public function getAccessToken($request = array()) {
  	if (is_string($request)) {
  		$request = array('uri' => array('path' => $request));
  	}
		
		$request = Set::merge($this->request, $request);
		$credentials = $this->credentials();
		
  	$options = array(
			'uri' => $request['uri'],
			'http_method' => $request['method'],
			'signature_method' => $request['auth']['oauth_signature_method'],
			'request_token_uri' => '/oauth/RequestToken',
			'authorize_uri' => '/oauth/Authorize',
			'access_token_uri' => '/oauth/AccessToken',
		);

		if (!empty($credentials['XeroCredential']['session_handle'])) {
			$options['oauth_session_handle'] = $credentials['XeroCredential']['session_handle'];
		} elseif (!empty($credentials['XeroCredential']['oauth_verifier'])) {
			$options['oauth_verifier'] = $credentials['XeroCredential']['oauth_verifier'];
		}

		$socket = new CurlSocket(compact('request'));
		$socket->config['ssl'] = $this->config['ssl'];

		$Consumer = new XeroConsumer($socket, $this->config['oauth_consumer_key'], $this->config['oauth_consumer_secret'], $options);

		$AccessToken = new AccessToken($Consumer, $credentials['XeroCredential']['key'], $credentials['XeroCredential']['secret']);
		if (!$AccessToken) {
			throw new CakeException("Unable to create Access Token");
		}

  	return $AccessToken;
  }

/**
 * Creates a request token out of a request.
 * @return RequestToken $RequestToken
 */
  public function getRequestToken($oauth_callback) {
  	$token = null;
  	$request = Set::merge($this->request, array('uri' => array('path' => '/oauth/RequestToken')));
		$options = array(
			'uri' => $request['uri'],
			'http_method' => $request['method'],
			'signature_method' => $request['auth']['oauth_signature_method'],
			'request_token_uri' => '/oauth/RequestToken',
			'authorize_url' => 'https://api.xero.com/oauth/Authorize',
			'access_token_uri' => '/oauth/AccessToken'
		);
		$config = array_merge(compact('request'), $this->config, array('headers' => array()));
		$socket = new CurlSocket($config);
		$Consumer = new XeroConsumer($socket, $this->config['oauth_consumer_key'], $this->config['oauth_consumer_secret'], $options);

		$uri = $socket->url($config['request']['uri']);
		$requestOptions = array_merge($this->sslRequestOptions(), compact('oauth_callback'));
		
		$token = $Consumer->tokenRequest('GET', $uri, $token, $requestOptions, $config);

  	return new RequestToken($Consumer, $token['oauth_token'], $token['oauth_token_secret']);
  }

/**
 * Renews the current AccessToken by requesting a renewal from Xero.
 * Modifies the AccessToken passed in.
 */
  public function renewAccessToken(&$AccessToken) {
  	$token = $this->getAccessToken($AccessToken->consumer->accessTokenPath());

  	$config = $token->consumer->http->config;
		$config['headers'] = array();

		$uri = $token->consumer->http->url($config['request']['uri']);
		$token = $token->consumer->tokenRequest('GET', $uri, $token, $this->sslRequestOptions(), $config);

		// Make sure we got new tokens
		if (!isset($token['oauth_token'], $token['oauth_token_secret'])) {
			throw new CakeException("Unable to renew AccessToken");
		}

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
		if (isset($model->endpoint)) {
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
  private function _logRequest($request, $response) {
  	if (!$this->config['logRequests']) {
  		return;
  	}
  	
  	$error = $entities = '';
  	$credentials = $this->credentials();
  	$status = ($response->code != XeroResponseCode::SUCCESS) ? 'FAILED' : 'SUCCESS';

		if ($response->code == XeroResponseCode::SUCCESS) {
			if (is_array($response->body)) {
				$entities = Set::classicExtract($response->body, '{n}.{s}.id');
				$entities = implode(",", Set::flatten($entities));
			}
		} else {
			CakeLog::write('xero_api_error', "Error for {$AccessToken->token}.\n".print_r($response, true));
			if (is_array($response->body) && isset($response->body['ApiException'])) {
				$error = __("%s: %s (%s)", $response->body['ApiException']['Type'], $response->body['ApiException']['Message'], $response->body['ApiException']['ErrorNumber']);
			} else {
				// SSL certificate error
				$body = ($response->code == XeroResponseCode::FORBIDDEN) ? 'The client SSL certificate was not valid.' : $response->body;
				$error = __("%s %s:\n%s", $response->code, $response->reasonPhrase, $body);
			}
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
	const RATE_LIMIT_EXCEEDED = 503;
}