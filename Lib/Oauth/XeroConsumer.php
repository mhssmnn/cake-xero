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
App::uses('XeroClientHttp', 'Xero.Lib/Oauth');

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
 * Create the http request object for a given httpMethod and path
 *
 * @param HttpSocket $socket
 * @param string $httpMethod
 * @param string $path
 * @param array $params
 * @return ClientHttp, Request instanse
 */
	protected function _createHttpRequest(&$socket, $httpMethod, $path, $params = array()) {
		if (isset($params['data'])) {
			$data = $params['data'];
			unset($params['data']);
		} else {
			$data = null;
		}

		if (isset($params['headers'])) {
			$headers = $params['headers'];
			unset($params['headers']);
		} else {
			$headers = $params;
		}

		switch (strtoupper($httpMethod)) {
			case 'POST':
				$request = new XeroClientHttp($socket, $path, $headers, 'POST');
			break;
			case 'PUT':
				$request = new XeroClientHttp($socket, $path, $headers, 'PUT');
			break;
			case 'GET':
				$request = new XeroClientHttp($socket, $path, $headers, 'GET');
			break;
			case 'DELETE':
				$request = new XeroClientHttp($socket, $path, $headers, 'DELETE');
			break;
			case 'HEAD':
				$request = new XeroClientHttp($socket, $path, $headers, 'HEAD');
			break;
			default:
				throw new Exception("Don't know how to handle httpMethod: " . $httpMethod);
			break;
		}
		if (is_array($data)) {
			$request->setFormData($data);
		} elseif (!empty($data)) {
			$request->body($data);
		}
		return $request;
	}
}
