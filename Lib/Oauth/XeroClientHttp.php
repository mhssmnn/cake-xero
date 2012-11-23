<?php
/**
 * Copyright 2010-2012, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010-2012, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('ClientHttp', 'OauthLib.Lib');

/**
 * ClientHTTP class
 *
 * @package oauth_lib
 * @subpackage oauth_lib.libs
 */
class XeroClientHttp extends ClientHttp {

	public function getQuery($request = null) {
		$cfg = $this->sock->config;
		if (empty($request)) {
			$request = $this;
		}

		$this->sock->config['request']['uri']['host'] = $request->sockUri->config['host'];
		if (isset($request->sockUri->config['scheme'])) {
			$this->sock->config['request']['uri']['scheme'] = $request->sockUri->config['scheme'];
		}
		if ($this->sock->config['request']['uri']['scheme'] == 'https') {
			$this->sock->config['request']['uri']['port'] = 443;
		}
		$body = $this->body();
		$query = array(
			'uri' => $this->sock->config['request']['uri'],
			'method' => $request->method,
			'body' => $this->body(),
			'header' => array(
				'Connection' => 'close',
				'Authorization' => $request->authorization,
			),
		);
		$query['header'] += $this->sock->config['request']['header'];
		if (empty($body) && (in_array($request->method, array('POST', 'PUT')))) {
			$query['header']['Content-Length'] = 0; 
		}

		OauthHelper::log(array('socket::query' => $query));
		return $query;
	}

/**
 * Configure oauth for request
 *
 * @param HttpSocket $http
 * @param ConsumerObject $consumer
 * @param TokenObject $token
 * @param array $options
 * @return void
 */
	public function oauth(&$http, &$consumer = null, &$token = null, $options = array()) {
		parent::oauth($http, $consumer, $token, $options);
		$opts = $this->oauthHelper->options;

		$default = array('request_uri' => $opts['request_uri'], 
			'consumer' => $consumer, 
			'token' => $token, 
			'scheme' => 'header', 
			'signature_method' => null, 
			'nonce' => null, 
			'timestamp' => null);
		$options = array_merge($default, (array)$options);

		// allows us to override private functions
		if ($options['scheme'] == 'header') {
			$this->authorization = null;
			$this->oauthHelper = new XeroClientHelper($this, $options);
			$this->authorization = $this->oauthHelper->header();
		}
	}

}


/**
 * CakePHP Oauth library http client implementation. This is HttpSocket extension that transarently handle oauth signing.
 * 
 * It provides set of methods to use in combine with Cakephp Auth component to authenticate users
 * with remote auth servers like twitter.com, so users will have transparent authentication later.
 *
 * @package oauth_lib
 * @subpackage oauth_lib.libs
 */
class XeroClientHelper extends ClientHelper {

/**
 * Oauth configuration parameters
 *
 * @return array
 */
	public function oauthParameters() {
		$params = array(
			'oauth_callback' => @$this->options['oauth_callback'],
			'oauth_consumer_key' => $this->options['consumer']->key,
			'oauth_signature_method' => $this->options['signature_method'],
			'oauth_token' => (isset($this->options['token']->token) ? $this->options['token']->token : ''),
			'oauth_timestamp' => $this->timestamp(),
			'oauth_nonce' => $this->nonce(),
			'oauth_verifier' => @$this->options['oauth_verifier'],
			'oauth_session_handle' => @$this->options['oauth_session_handle'],
			'oauth_version' => '1.0'
			);

		foreach (array_keys($params) as $param)	{
			if (strlen($params[$param]) == 0) {
				unset($params[$param]);
			}
		}
		return $params;
	}
}