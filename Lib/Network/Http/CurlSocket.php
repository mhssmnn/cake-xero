<?php

/**
 * HTTP Socket connection class.
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) :  Rapid Development Framework (http://www.cakephp.org)
 * Copyright 2005-2008, Cake Software Foundation, Inc. (http://www.cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright 2005-2008, Cake Software Foundation, Inc. (http://www.cakefoundation.org)
 * @link          http://www.cakefoundation.org/projects/info/cakephp CakePHP(tm) Project
 * @package		  oauth_lib
 * @subpackage	  oauth_lib.vendors
 */

App::uses('CakeSocket', 'Network');
App::uses('HttpSocket', 'Network/Http');
App::uses('Set', 'Utility');
App::uses('Router', 'Routing');

/**
 * Cake network socket connection class.
 *
 * Core base class for HTTP network communication.
 *
 * @package       cake
 * @subpackage    cake.cake.libs
 */
class CurlSocket extends HttpSocket {

/**
 * Object description
 *
 * @var string
 * @access public
 */
	var $description = 'CURL-based DataSource Interface';


/**
 * Parses and sets the specified URI into current request configuration.
 *
 * @param string|array $uri URI, See HttpSocket::_parseUri()
 * @return boolean If uri has merged in config
 */
	public function configUri($uri = null) {
		return $this->_configUri($uri);
	}

/**
 * Takes a $uri array and turns it into a fully qualified URL string
 *
 * @param string|array $uri Either A $uri array, or a request string. Will use $this->config if left mpty.
 * @param string $uriTemplate The Uri template/format to use.
 * @return mixed A fully qualified URL formatted according to $uriTemplate, or false on failure
 */
	public function buildUri($uri = array(), $uriTemplate = '%scheme://%user:%pass@%host:%port/%path?%query#%fragment') {
		return $this->_buildUri($uri, $uriTemplate);
	}

/**
 * Parses the given URI and breaks it down into pieces as an indexed array with elements
 * such as 'scheme', 'port', 'query'.
 *
 * @param string|array $uri URI to parse
 * @param boolean|array $base If true use default URI config, otherwise indexed array to set 'scheme', 'host', 'port', etc.
 * @return array Parsed URI
 */
	public function parseUri($uri = null, $base = array()) {
		return $this->_parseUri($uri, $base);
	}

/**
 * This function can be thought of as a reverse to PHP5's http_build_query(). It takes a given query string and turns it into an array and
 * supports nesting by using the php bracket syntax. So this means you can parse queries like:
 *
 * - ?key[subKey]=value
 * - ?key[]=value1&key[]=value2
 *
 * A leading '?' mark in $query is optional and does not effect the outcome of this function.
 * For the complete capabilities of this implementation take a look at
 * HttpSocketTest::testparseQuery()
 *
 * @param string|array $query A query string to parse into an array or an array to return
 * directly "as is"
 * @return array The $query parsed into a possibly multi-level array. If an empty $query is
 *     given, an empty array is returned.
 */
	public function parseQuery($query) {
		return $this->_parseQuery($query);
	}

/**
 * Mirror of the HttpSocket Connect method as much as possible.
 *
 * @return boolean Success
 * @access public
 */
	function connect() {
		if ($this->connection != null) {
			$this->disconnect();
		}

		$this->connection = curl_init();

		$this->connected = is_resource($this->connection);
		if ($this->connected) {
			curl_setopt($this->connection, CURLOPT_CONNECTTIMEOUT, $this->config['timeout']);
			curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, true);
		}
		return $this->connected;
	}


/**
 * Disconnect the socket from the current connection.
 *
 * @return boolean Success
 * @access public
 */
	function disconnect() {
		if (!is_resource($this->connection)) {
			$this->connected = false;
			return true;
		}
		
		@curl_close($this->connection);
		$this->connected = false;

		if (!$this->connected) {
			$this->connection = null;
		}
		return !$this->connected;
	}


/**
 * Write data to the socket.
 *
 * @param string $data The data to write to the socket
 * @return boolean Success
 * @access public
 */
	function write($data = null) {
		if (!$this->connected) {
			if (!$this->connect()) {
				return false;
			}
		}
		// pr($this);
		// throw new Exception(); die;

		if ($this->request['method'] == 'PUT' || $this->request['method'] == 'POST') {
			curl_setopt($this->connection, CURLOPT_CUSTOMREQUEST, $this->request['method']);
			curl_setopt($this->connection, CURLOPT_POSTFIELDS, $this->request['body']);
		}

		if ($this->request['header'] !== false) {
			curl_setopt($this->connection, CURLOPT_HEADER, true);
			curl_setopt($this->connection, CURLOPT_HTTPHEADER, explode("\r\n", $this->request['header']));
		}

		if (!empty($this->config['ssl'])) {
			curl_setopt($this->connection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->connection, CURLOPT_SSLCERTTYPE, "p12");
			
			if (!empty($this->config['ssl']['publicCert'])) {
				curl_setopt($this->connection, CURLOPT_SSLCERT, $this->config['ssl']['publicCert']);
			}
			
			if (!empty($this->config['ssl']['privateCertPass'])) {
				curl_setopt($this->connection, CURLOPT_SSLCERTPASSWD, $this->config['ssl']['privateCertPass']);
			}
		}

		curl_setopt($this->connection, CURLOPT_URL, $this->buildUri($this->request['uri']));
		
		$this->_receivedResponse = curl_exec($this->connection);
	}


/**
 * Read data from the socket. Returns false if no data is available or no connection could be
 * established.
 *
 * @param integer $length Optional buffer length to read; defaults to 1024
 * @return mixed Socket data
 */
  public function read($length = 1024) {
    if (!$this->_receivedResponse) {
      return false;
    }

    $response = $this->_receivedResponse;
    $this->_receivedResponse = false;

    return $response;
  }
}
