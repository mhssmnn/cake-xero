<?php

App::uses('XeroAppModel', 'Xero.Model');
App::uses('XeroSource', 'Xero.Model/Datasource');
App::uses('CakeResponse', 'Network');

class XeroTestSource extends XeroSource {

	public function parseResponse($response) {
		return parent::_parseResponse($response);
	}
	
}

class MockCurlSocket extends CurlSocket {

	public function connect() {
		$this->connected = true;
		return true;
	}

	public function write($data = null) {
		$this->_receivedResponse = $data;
	}

}

class TestModel extends XeroAppModel {
	public $useDbConfig = 'xero_partner';
	var $localModel = 'Test';
	var $endpoint = 'Tests';
	var $_schema = array(
		'TestID' => array('type' => 'primary_key'),
		'Type' => array('type' => 'string'),
		'Date' => array('type' => 'datetime'),
	);

}

class XeroSourceTestCase extends CakeTestCase {

/**
 * autoFixtures property
 *
 * @var bool false
 */
	public $autoFixtures = true;

	public $fixtures = array('plugin.xero.xero_request', 'plugin.xero.xero_credential');
	
/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		$this->testXero = new XeroTestSource();
		$this->testXero->cacheSources = false;
		$this->testXero->startQuote = '`';
		$this->testXero->endQuote = '`';

		$this->Model = new TestModel();
		$this->XeroCredential =& ClassRegistry::init('Xero.XeroCredential');
		$this->XeroRequest =& ClassRegistry::init('Xero.XeroRequest');
	}
	
/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Model);
		unset($this->XeroCredential);
		unset($this->XeroRequest);
		ClassRegistry::flush();
	}

	public function testCredentials() {
		$this->testXero->credentials(1);
		$this->assertNotEmpty($this->testXero->credentials, 'Xero Credentials empty');
		$expected = $this->XeroCredential->read(null, 1);
		$this->assertEqual($this->testXero->credentials, $expected, 'Xero Credentials could not be loaded');
		$this->assertEqual($this->testXero->credentials(), $expected, 'Credentials return expected.');
		$this->testXero->credentials(array('id' => 1));
		$this->assertEqual($this->testXero->credentials, $expected, 'Xero Credentials could not be loaded using conditions');
	}

	public function testSslRequestOptions() {
		$this->assertEqual($this->testXero->sslRequestOptions(), array(), 'Empty array not returned');
		$options = array('publicCert', 'privateCert', 'privateCertPass');
		$this->testXero->config['ssl'] = array_combine($options, $options);
		$expected = array('publicCert' => 'publicCert', 'privateCert' => 'file:///privateCert', 'privateCertPass' => '');
		$this->assertEqual($this->testXero->sslRequestOptions(), $expected, 'SSL Request Options were incorrect.');
	}

	public function testSuccessfulRequest() {
		$xmlResponse = "<Response><Id>Id</Id><Status>Status</Status><ProviderName>Xero</ProviderName><DateTimeUTC></DateTimeUTC><Tests><Test><AnotherModel><AnotherModelID>123</AnotherModelID></AnotherModel></Test></Tests></Response>";
		$this->_setupRequestMocks(200, $xmlResponse);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
		$expected = array(array('Test' => array('AnotherModel' => array('id' => 123))));
		$this->assertEqual($result, $expected, 'Result was not returned as expected');
	}

	public function testBadRequest() {
		$xmlResponse = "<ApiException><ErrorNumber>10</ErrorNumber><Type>ValidationException</Type><Message>A validation exception occurred</Message></ApiException>";
		$this->_setupRequestMocks(400, $xmlResponse);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
		$expected = array('ApiException' => array('ErrorNumber' => 10, 'Type' => 'ValidationException', 'Message' => 'A validation exception occurred'));
		$this->assertEqual($result, $expected, 'Result was not returned as expected');
	}

	public function testUnauthorizedRequest() {
		$response = "oauth_problem=xxxxxxxxxxxxxxxx&oauth_problem_advice=xxxxxxxxxxxxxxxxxx";
		$this->_setupRequestMocks(401, $response);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request, 50); // 50 so no recursion happens
		$this->assertEqual($result, $response, 'Result was not returned as expected');
	}

	/**
	 * @expectedException XeroSSLValidationException
	 */
	public function testForbiddenRequest() {
		$this->_setupRequestMocks(403, "", false);
		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
	}

	public function testNotFoundRequest() {
		$response = "The resource you're looking for cannot be found";

		$this->_setupRequestMocks(404, $response);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
		$this->assertEqual($result, $response, 'Result was not returned as expected');
	}

	public function testNotImplementedRequest() {
		$xmlResponse = "<ApiException><ErrorNumber>20</ErrorNumber><Type>ApiMethodNotImplementedException</Type><Message>The Api Method called is not implemented</Message></ApiException>";
 		$this->_setupRequestMocks(501, $xmlResponse);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
		$expected = array('ApiException' => array('ErrorNumber' => 20, 'Type' => 'ApiMethodNotImplementedException', 'Message' => 'The Api Method called is not implemented'));
		$this->assertEqual($result, $expected, 'Result was not returned as expected');
	}

	public function testNotImplementedRequestWithLogging() {
		$xmlResponse = "<ApiException><ErrorNumber>20</ErrorNumber><Type>ApiMethodNotImplementedException</Type><Message>The Api Method called is not implemented</Message></ApiException>";
		$expected = array('ApiException' => array('ErrorNumber' => 20, 'Type' => 'ApiMethodNotImplementedException', 'Message' => 'The Api Method called is not implemented'));

		$this->_setupRequestMocks(501, $xmlResponse, false);
		$this->MockXeroSource->config['logRequests'] = true;
		$this->MockXeroSource->credentials = array('XeroCredential' => array('organisation_id' => 'test_request_with_logging'));
		$this->MockXeroSource->XeroRequest =& $this->XeroRequest;
		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
		$this->assertEqual($result, $expected, 'Result was not returned as expected');

		$expected = array('XeroRequest' => array(
			'organisation_id' => 'test_request_with_logging',
			'status' => 'FAILED',
			'endpoint' => '',
			'query' => '',
			'entities' => '',
			'error' => "ApiMethodNotImplementedException: The Api Method called is not implemented (20)"
		));
		$result = $this->XeroRequest->findByOrganisationId('test_request_with_logging', array_keys($expected['XeroRequest']));
		$this->assertEquals($result, $expected, 'Could not find request in log');
	}

	public function testNotAvailableRequest() {
		$response = "The Xero API is currently offline for maintenance";

		$this->_setupRequestMocks(503, $response);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request);
		$this->assertEqual($result, $response, 'Result was not returned as expected');
	}

	public function testRateLimitExceededRequest() {
		$response = "oauth_problem=rate%20limit%20exceeded&oauth_problem_advice=please%20wait%20before%20retrying%20the%20xero%20api";
		$this->_setupRequestMocks(503, $response);

		$request = $this->MockXeroSource->request;
		$result = $this->MockXeroSource->request($request, 50); // 50 so no recursion happens
		$this->assertEqual($result, $response, 'Result was not returned as expected');
	}

	function _setupRequestMocks($code, $body, $mockLogRequests = true) {
		$methodsToMock = array('getAccessToken', 'credentialsHaveExpired');
		$mockLogRequests && $methodsToMock = array_merge($methodsToMock, array('logRequest'));

		$this->MockXeroSource = $this->getMock('XeroSource', $methodsToMock);
		$this->MockConsumer = $this->getMock('XeroConsumer', array(), array(new MockCurlSocket, 'token', 'secret'));
		$this->MockAccessToken = $this->getMock('AccessToken', array('request'), array($this->MockConsumer, 'token', 'secret'));

		$responseObject = new stdClass();
		$responseObject->body = $body;
		$responseObject->code = $code;

		$this->MockXeroSource->expects($this->once())->method('getAccessToken')->will($this->returnValue($this->MockAccessToken));
		$this->MockXeroSource->expects($this->once())->method('credentialsHaveExpired')->will($this->returnValue(false));
		if ($mockLogRequests) {
			$this->MockXeroSource->expects($this->once())->method('logRequest')->will($this->returnValue(true));
		}
		
		$this->MockAccessToken->expects($this->once())->method('request')->will($this->returnValue($responseObject));
	}

	public function testParseResponse() {
		$result = $this->testXero->parseResponse(array());
		$this->assertEqual($result, array(), 'Should parse empty array and return empty array');

		$result = $this->testXero->parseResponse($this->_buildR(array()));
		$this->assertEqual($result->body, array(), 'Should parse object with empty response and return object without Response array');

		$result = $this->testXero->parseResponse($this->_buildR(array('Test' => array())));
		$this->assertEqual($result->body, array(array(null)), 'Should return an array wrapped in an array with a null 0th value.');

		$result = $this->testXero->parseResponse($this->_buildR(array('Tests' => array('Test' => array()))));
		$this->assertEqual($result->body, array(array('Test' => array())), 'Should return a numeric array with a Test key pointing to an empty array');

		$result = $this->testXero->parseResponse($this->_buildR(array('Tests' => array('Test' => array('TestNumber' => '123')))));
		$this->assertEqual($result->body, array(array('Test' => array('test_number' => 123))), 'Should return an underscorized key with a value 123');

		$result = $this->testXero->parseResponse($this->_buildR(array('Tests' => array('Test' => array('TestID' => 123, 'AnotherID' => 456)))));
		$this->assertEqual($result->body, array(array('Test' => array('id' => 123, 'another_id' => 456))), 'Should return IDs converted to underscored, prefix-less ids');

		$result = $this->testXero->parseResponse($this->_buildR(array('Tests' => array('Test' => array('AnotherModel' => array())))));
		$this->assertEqual($result->body, array(array('Test' => array('AnotherModel' => array()))), 'Should return associated models as CamelCased.');

		$result = $this->testXero->parseResponse($this->_buildR(array('Tests' => array('Test' => array('AnotherModel' => array('TestID' => 123))))));
		$this->assertEqual($result->body, array(array('Test' => array('AnotherModel' => array('test_id' => 123)))), 'ModelIDs in AssociatedModels should keep their prefix_id.');

		$result = $this->testXero->parseResponse($this->_buildR(array('Tests' => array('Test' => array('AnotherModel' => array('AnotherModelID' => 123))))));
		$this->assertEqual($result->body, array(array('Test' => array('AnotherModel' => array('id' => 123)))), 'AssociatedModelIDs in AssociatedModels should drop their id prefix.');
	}

	function _buildR($response = array()) {
		$stdResponse = new stdClass();
		if (!isset($response['Response'])) {
			$stdResponse->body = array('Response' => array());
			$stdResponse->body['Response'] = $response;
		} else {
			$stdResponse->body = $response;
		}

		return $stdResponse;
	}

	public function testGetAccessToken() {
		$Consumer = $this->getMock('XeroConsumer', array('getAccessToken'), array(new MockCurlSocket, 'token', 'secret'));
		$MockXeroSource = $this->getMock('XeroSource', array('consumer'));
		$request = array('uri' => array('path' => '/oauth/RequestToken'), 'ssl' => $MockXeroSource->sslRequestOptions());
		$credentials = array('XeroCredential' => array('key' => 'token', 'secret' => 'tokenSecret'));

		$Consumer->expects($this->once())
						 ->method('getAccessToken')
						 ->with($this->equalTo($request), 
						 				$this->equalTo($credentials));
		$MockXeroSource->credentials = $credentials;
		$MockXeroSource->request = array();
		$MockXeroSource->expects($this->once())->method('consumer')->will($this->returnValue($Consumer));
		
		$MockXeroSource->getAccessToken('/oauth/RequestToken');
	}

	public function testGetRequestToken() {
		$Consumer = $this->getMock('XeroConsumer', array('getRequestToken'), array(new MockCurlSocket, 'token', 'secret'));
		$MockXeroSource = $this->getMock('XeroSource', array('consumer'));
		$request = array('uri' => 'https://api-partner.xero.com:443/oauth/RequestToken');

		$MockXeroSource->request = $request;
		$Consumer->expects($this->once())
						 ->method('getRequestToken')
						 ->with($this->equalTo($request + array('ssl' => $MockXeroSource->sslRequestOptions())), 
						 				$this->equalTo('oob'));
		$MockXeroSource->expects($this->once())->method('consumer')->will($this->returnValue($Consumer));
		
		$MockXeroSource->getRequestToken('oob');
	}

	public function testRenewAccessToken() {
		$Consumer = $this->getMock('XeroConsumer', array('accessTokenUrl', 'renewAccessToken', 'tokenRequest'), array(new MockCurlSocket, 'token', 'secret'));
		$AccessToken = new AccessToken($Consumer, 'token','secret');
		$newAccessToken = array('oauth_token' => 'newtoken', 'oauth_token_secret' => 'newsecret', 'oauth_expires_in' => '15');

		$Consumer->expects($this->once())->method('accessTokenUrl')->will($this->returnValue('/oauth/AccessToken'));
		$Consumer->expects($this->once())->method('renewAccessToken')->will($this->returnValue($newAccessToken));

		$credentials = array('id' => 'test_credentials', 'organisation_id' => 'test_organisation', 'session_handle' => '');
		$MockXeroSource = $this->getMock('XeroSource', array('getAccessToken'));
		$MockXeroSource->expects($this->once())->method('getAccessToken')->will($this->returnValue($AccessToken));
		$MockXeroSource->XeroCredential =& $this->XeroCredential;
		$MockXeroSource->credentials = array('XeroCredential' => $credentials);

		$MockXeroSource->renewAccessToken($AccessToken);
		$this->assertEquals($AccessToken->token, 'newtoken', 'AccessToken token was not renewed');
		$this->assertEquals($AccessToken->tokenSecret, 'newsecret', 'AccessToken secret was not renewed');

		$expected = $credentials + array('key' => 'newtoken', 'secret' => 'newsecret');
		$result = $this->XeroCredential->findById('test_credentials', array_keys($expected));
		$this->assertEquals($result, array('XeroCredential' => $expected), "Credentials do not appear to have been updated.\n".print_r($result, true).print_r($expected, true));

		$result = $this->XeroCredential->findById('test_credentials', array('expires'));
		$this->assertNotNull($result['XeroCredential']['expires'], 'Token expiry shouldnt be null.');
		$this->assertLessThanOrEqual(strtotime($result['XeroCredential']['expires']), date('Y-m-d H:i:s', time()+15), 'Token expiry seems to be set too far in the future.');
	}

	public function testReadEndpoint() {
		$queryData = array('conditions' => array(), 'fields' => null);
		$MockXeroSource = $this->getMock('XeroSource', array('request'));
		$MockXeroSource->expects($this->once())->method('request')->will($this->returnArgument(0));
		$expected = $MockXeroSource->request;
		$expected['uri']['path'] = 'api.xro/2.0/Tests';
		$expected['uri']['query'] = array('where' => array());
		$result = $MockXeroSource->read($this->Model, $queryData);
		$this->assertEquals($result, $expected, "Incorrectly formed request: \n".var_export($result, true));
	}

	public function testReadEndpointSingleton() {
		$queryData = array('conditions' => array('id' => 'test_id'), 'fields' => null);
		$MockXeroSource = $this->getMock('XeroSource', array('request'));
		$MockXeroSource->expects($this->once())->method('request')->will($this->returnArgument(0));
		$expected = $MockXeroSource->request;
		$expected['uri']['path'] = 'api.xro/2.0/Tests/test_id';
		$expected['uri']['query'] = array('where' => array());
		$result = $MockXeroSource->read($this->Model, $queryData);
		$this->assertEquals($result, $expected, "Incorrectly formed request: \n".var_export($result, true));
	}

	public function testReadEndpointWithModifiedAfter() {
		$queryData = array('conditions' => array('modified_after' => '2012-01-01 00:00:00'), 'fields' => null);
		$MockXeroSource = $this->getMock('XeroSource', array('request'));
		$MockXeroSource->expects($this->once())->method('request')->will($this->returnArgument(0));
		$expected = $MockXeroSource->request;
		$expected['uri']['path'] = 'api.xro/2.0/Tests';
		$expected['uri']['query'] = array('where' => array());
		$expected['header'] = array('If-Modified-Since' => '2012-01-01 00:00:00');
		$result = $MockXeroSource->read($this->Model, $queryData);
		$this->assertEquals($result, $expected, "Incorrectly formed request: \n".var_export($result, true));
	}

	/**
	 * @expectedException CakeException
	 */
	public function testReadWithoutEndpoint() {
		$model = new Model();
		$model->localModel = '';
		$this->testXero->read($model, array('fields' => null));
	}

	public function testCredentialsHaveExpired() {
		$this->testXero->credentials = array('XeroCredential' => array('expires' => date('Y-m-d H:i:s', strtotime('-1 minute'))));
		$this->assertTrue($this->testXero->credentialsHaveExpired());

		$this->testXero->credentials = array('XeroCredential' => array('expires' => date('Y-m-d H:i:s', strtotime('+1 minute'))));
		$this->assertFalse($this->testXero->credentialsHaveExpired());
	}

	public function testLogRequestSuccess() {
		$this->testXero->config['logRequests'] = true;
		$this->testXero->credentials = array('XeroCredential' => array('organisation_id' => 'log_result_success'));
		$request = array('uri' => array('path' => 'test', 'query' => array('where' => array())));
		$response = new stdClass();
		$response->code = 200;
		$response->body = array(array('Test' => array('id' => '123')));

		$this->testXero->logRequest($request, $response);
		$result = $this->XeroRequest->findByEndpoint('test', array('organisation_id', 'status', 'endpoint', 'query', 'entities', 'error'));
		$expected = array('XeroRequest' => array(
			'organisation_id' => 'log_result_success',
			'status' => 'SUCCESS',
			'endpoint' => 'test',
			'query' => '',
			'entities' => '123',
			'error' => ''
		));
		$this->assertEqual($result, $expected, 'Expected result was not logged');
	}

	public function testLogRequestFailure() {
		$this->testXero->config['logRequests'] = true;
		$this->testXero->credentials = array('XeroCredential' => array('organisation_id' => 'log_result_failure'));
		$request = array('uri' => array('path' => 'test', 'query' => array('where' => array())));
		$response = new stdClass();
		$response->code = 501; // Not implemented
		$response->body = array('ApiException' => array('ErrorNumber' => '20', 'Type' => 'ApiMethodNotImplementedException', 'Message' => 'The Api Method called is not implemented'));

		$this->testXero->logRequest($request, $response);
		$result = $this->XeroRequest->findByEndpoint('test', array('organisation_id', 'status', 'endpoint', 'query', 'entities', 'error'));
		$expected = array('XeroRequest' => array(
			'organisation_id' => 'log_result_failure',
			'status' => 'FAILED',
			'endpoint' => 'test',
			'query' => '',
			'entities' => '',
			'error' => __("%s: %s (%s)", 'ApiMethodNotImplementedException', 'The Api Method called is not implemented', '20')
		));
		$this->assertEqual($result, $expected, "Not logged correctly\n".print_r($result, true).print_r($expected, true));
	}
}