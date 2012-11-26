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
}
