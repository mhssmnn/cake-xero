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

App::uses('Consumer', 'OauthLib.Lib');

/**
 * CakePHP Oauth library consumer implementation.
 *
 * It provides set of methods to use in combine with Cakephp Auth component to authenticate users
 * with remote auth servers like twitter.com, so users will have transparent authentication later.
 *
 * @package oauth_lib
 * @subpackage oauth_lib.libs
 */
class XeroConsumer extends Consumer {

/**
 * 'oauth_signature_method', Signature method used by server. Defaults to HMAC-SHA1
 * 'request_token_uri', default paths on site. These are the same as the defaults set up by the generators
 * 'scheme',
 *  Possible values:
 *    'header' - via the Authorize header (Default)
 *    'body' - url form encoded in body of POST request
 *    'query_string' - via the query part of the url
 * 'http_method', Default http method used for OAuth Token Requests (defaults to 'post')
 *
 * @var array
 */
	private $__defaultOptions = array(
		'signature_method' => 'RSA-SHA1',
		'server_url' => 'https://api-partner.network.xero.com/api.xro/2.0/',
		'request_token_url' => 'https://api-partner.network.xero.com/oauth/RequestToken',
		'authorize_url' => 'https://api.xero.com/oauth/Authorize',
		'access_token_url' => 'https://api-partner.network.xero.com/oauth/AccessToken',
		'request_token_uri' => 'https://api-partner.network.xero.com/oauth/RequestToken',
		'authorize_uri' => 'https://api.xero.com/oauth/Authorize',
		'access_token_uri' => 'https://api-partner.network.xero.com/oauth/AccessToken',
		'scheme' => 'header',
		'http_method' => 'GET',
		'oauth_version' => "1.0"
	);

/**
 * XeroConsumer constructor
 *
 * @param CurlSocket $socket
 * @param string $consumerKey
 * @param string $consumerSecret
 * @param array $options
 */
	public function __construct(CurlSocket &$socket, $consumerKey, $consumerSecret, $options = array()) {
		$this->initConsumer($consumerKey, $consumerSecret, $options);
		$this->http =& $socket;
	}

/**
 * Returns the specified url
 */
	public function oauthUrl($key = null) {
		if ($key === null) {
			return $this->options;
		}
		if (!array_key_exists($key, $this->options)) {
			$key = 'server_url';
		}
		return $this->options[$key];
	}

/**
 * Creates an access token out of a request.
 * @return AccessToken $AccessToken
 */
  public function getAccessToken($request, $credentials) {
  	$requestOptions = $request['ssl'];

		$params = array(
			'uri' => $request['uri'],
			'http_method' => $request['method'],
			'signature_method' => $request['auth']['oauth_signature_method'],
		);

		if (!empty($credentials['XeroCredential']['session_handle'])) {
			$params['oauth_session_handle'] = $credentials['XeroCredential']['session_handle'];
		} elseif (!empty($credentials['XeroCredential']['oauth_verifier'])) {
			$params['oauth_verifier'] = $credentials['XeroCredential']['oauth_verifier'];
		}

		$this->options = array_merge($this->__defaultOptions, $params);

		$AccessToken = new AccessToken($this, $credentials['XeroCredential']['key'], $credentials['XeroCredential']['secret']);
		
		if (!$AccessToken) {
			throw new CakeException("Unable to create Access Token");
		}

  	return $AccessToken;
  }


/**
 * Makes a request to the service for a new OAuthRequestToken
 *
 * if oauth_callback wasn't provided, it is assumed that oauth_verifiers
 * will be exchanged out of band
 *
 * If request tokens are passed between the consumer and the provider out of
 * band (i.e. callbacks cannot be used), need to use "oob" string per section 6.1.1
 *
 * @param array $requestOptions
 * @param array $params
 * @return boolean
 */
	public function getRequestToken($request, $oauth_callback = null) {
		$token = null;
		$defaultOptions = array('oauth_callback' => 'oob');
		$requestOptions = array_merge($defaultOptions, compact('oauth_callback'), $request['ssl']);

		$params = array(
			'uri' => $request['uri'],
			'http_method' => $request['method'],
			'signature_method' => $request['auth']['oauth_signature_method'],
		);

		$this->options = array_merge($this->__defaultOptions, $params);
		
		$response = $this->tokenRequest(
			'GET', 
			$this->requestTokenUrl(),
			$token, $requestOptions, $params
		);

		if (!isset($response['oauth_token']) || !isset($response['oauth_token_secret'])) {
			return false;
		}
		
		return new RequestToken($this, $response['oauth_token'], $response['oauth_token_secret']);
	}

/**
 * Renews the current AccessToken by requesting a renewal from Xero.
 * Modifies the AccessToken passed in.
 */
  public function renewAccessToken($token, $requestOptions) {
  	$config = $this->http->config;
		$config['headers'] = array();

		$token = $this->tokenRequest('GET', $this->accessTokenUrl(), $token, $requestOptions, $config);

		// Make sure we got new tokens
		if (!isset($token['oauth_token'], $token['oauth_token_secret'])) {
			throw new CakeException("Unable to renew AccessToken");
		}

		return $token;
  }

}