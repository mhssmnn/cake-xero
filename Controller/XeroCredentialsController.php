<?php
App::uses('XeroConsumer', 'Xero.Lib/Oauth');
App::uses('RequestToken', 'OauthLib.Token');
App::uses('HttpResponse', 'Network/Http');

class XeroCredentialsController extends XeroAppController {

	function authorise($organisation_id = null) {
		// Create a new company id if none exists
		if ($organisation_id === null) {
			$organisation_id = String::uuid();
		}

		$oauth_callback = Router::url(array('action' => 'create'), true);
		$this->Session->write('Xero.Auth.organisation_id', $organisation_id);

		$datasource =& ConnectionManager::getDataSource('xero_partner');
		$RequestToken = $datasource->getRequestToken($oauth_callback);

		$this->Session->write('Xero.Auth.RequestToken', $RequestToken);

		$this->redirect($RequestToken->authorizeUrl());
	}

/**
 * Callback from Xero comes here.
 */
	function create() {
		if (!empty($this->request->query['oauth_verifier'])) {
			$datasource =& ConnectionManager::getDataSource('xero_partner');

			$RequestToken = $this->Session->read('Xero.Auth.RequestToken');
			$this->Session->delete('Xero.Auth.RequestToken');
			if (!$RequestToken) {
				return $this->redirect(array('action' => 'authorise'));
			}

			$uri = array(
				'scheme' => 'https',
				'host' => 'api-partner.network.xero.com',
				'port' => 443,
				'path' => '/oauth/AccessToken'
			);
			$RequestToken->consumer->http->request['uri'] = $uri;
			$RequestToken->consumer->http->config['request']['uri'] = $uri;
			$RequestToken->consumer->http->config['scheme'] = 'https';
			$RequestToken->consumer->http->config['request']['header'] = array();
			$RequestToken->consumer->http->config['headers'] = array();
			$params = $RequestToken->consumer->http->config;

			$AccessToken = $RequestToken->getAccessToken(array_merge($datasource->sslRequestOptions(), array('oauth_verifier' => $this->request->query['oauth_verifier'])), $params);

			// Save new credentials to the database
			$this->XeroCredential->save(array(
				'organisation_id' => $this->Session->read('Xero.Auth.organisation_id'),
				'key' => $AccessToken->token,
				'secret' => $AccessToken->tokenSecret,
				'session_handle' => $AccessToken->params['oauth_session_handle'],
				'expires' => date('Y-m-d H:i:s', time() + $AccessToken->params['oauth_expires_in']),
			));
		}
	}
	
	function reauthorise($organisation_id) {
		$this->XeroCredential->deleteAll(array('organisation_id' => $organisation_id), false);
		$this->redirect(array('action' => 'add'));
	}
}