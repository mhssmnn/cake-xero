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

	// function testParseResponse() {
	// 	$aContact = unserialize('a:1:{s:8:"Response";a:5:{s:2:"Id";s:36:"df10b009-9e60-4bfb-b039-9befe703a87a";s:6:"Status";s:2:"OK";s:12:"ProviderName";s:25:"Business Catalyst Connect";s:11:"DateTimeUTC";s:28:"2011-05-04T19:24:44.9580741Z";s:8:"Contacts";a:1:{s:7:"Contact";a:10:{s:9:"ContactID";s:36:"6170fb21-2766-4af9-81e5-0d73f5243c23";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:33:"Petrie McLoud Watson & Associates";s:9:"FirstName";s:6:"Morris";s:8:"LastName";s:8:"McKenzie";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:4:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:4:"4561";s:13:"PhoneAreaCode";s:3:"915";s:16:"PhoneCountryCode";s:2:"02";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-14T00:46:05.25";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}}}}');
	// 	$response = new stdClass();
	// 	$response->body = $aContact;
	// 	$expected = array();
	// 	$expected['Contact'] = array($aContact['Response']['Contacts']['Contact']);
	// 	$result = $this->Xero->_parseResponse($response);
	// 	$this->assertEqual($result, $expected);

	// 	$anyContact = unserialize('a:1:{s:8:"Response";a:5:{s:2:"Id";s:36:"b96924b6-0700-4780-b9fc-03dff5d094cc";s:6:"Status";s:2:"OK";s:12:"ProviderName";s:25:"Business Catalyst Connect";s:11:"DateTimeUTC";s:28:"2011-05-04T22:53:07.9993586Z";s:8:"Contacts";a:1:{s:7:"Contact";a:42:{i:0;a:10:{s:9:"ContactID";s:36:"6170fb21-2766-4af9-81e5-0d73f5243c23";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:33:"Petrie McLoud Watson & Associates";s:9:"FirstName";s:6:"Morris";s:8:"LastName";s:8:"McKenzie";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:4:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:4:"4561";s:13:"PhoneAreaCode";s:3:"915";s:16:"PhoneCountryCode";s:2:"02";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-14T00:46:05.25";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:1;a:8:{s:9:"ContactID";s:36:"b24c9042-f610-451e-b317-1a8d5712602f";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:14:"Gateway Motors";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"349227";s:13:"PhoneAreaCode";s:3:"800";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.333";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:2;a:8:{s:9:"ContactID";s:36:"aa5b440f-ac5f-426b-9437-1ab0ec8a24f3";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:17:"Carlton Functions";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"9179945";s:13:"PhoneAreaCode";s:2:"02";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.567";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:3;a:9:{s:9:"ContactID";s:36:"6d42f03b-181f-43e3-93fb-2025c012de92";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:18:"Wilson Periodicals";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:22:"2011-05-10T01:39:25.62";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";s:15:"DefaultCurrency";s:3:"NZD";}i:4;a:8:{s:9:"ContactID";s:36:"1d8e804c-c2e7-44fe-9606-2517de3c6441";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"City Agency";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"9173555";s:13:"PhoneAreaCode";s:2:"01";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-14T00:46:05.407";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:5;a:10:{s:9:"ContactID";s:36:"0af90bb3-9ad0-49b2-a884-2c565c16078d";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"Net Connect";s:18:"BankAccountDetails";s:19:"98-0908-09786756-00";s:9:"TaxNumber";s:19:"15% GST on Expenses";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:5:{s:11:"AddressType";s:5:"POBOX";s:12:"AddressLine1";s:12:"P O Box 7900";s:12:"AddressLine2";s:20:"South Mailing Centre";s:4:"City";s:7:"Oaktown";s:10:"PostalCode";s:4:"1236";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"500998";s:13:"PhoneAreaCode";s:3:"800";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:3:{s:9:"PhoneType";s:3:"FAX";s:11:"PhoneNumber";s:6:"500999";s:13:"PhoneAreaCode";s:3:"800";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.963";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:6;a:8:{s:9:"ContactID";s:36:"b319b3b5-373b-4f3a-874f-2c9acd3e5f8d";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:7:"Boom FM";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:2:{s:11:"AddressType";s:5:"POBOX";s:11:"AttentionTo";s:23:"Human Resources Manager";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:4:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:4:"9191";s:13:"PhoneAreaCode";s:3:"555";s:16:"PhoneCountryCode";s:2:"01";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-14T00:46:05.28";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:7;a:8:{s:9:"ContactID";s:36:"2b6d2035-ded0-45a7-b2ad-4b01c788eaef";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:13:"Ridgeway Bank";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:40.103";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:8;a:8:{s:9:"ContactID";s:36:"8a8fb8ad-e6ff-4c3e-b871-515124e40840";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:14:"Marine Systems";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:2:{s:11:"AddressType";s:5:"POBOX";s:11:"AttentionTo";s:13:"Accounts Dept";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"9786456";s:13:"PhoneAreaCode";s:2:"02";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-27T01:18:23.493";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:9;a:17:{s:9:"ContactID";s:36:"16c56769-c3c8-4dad-bd40-523f83bfe017";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:12:"Bayside Club";s:9:"FirstName";s:3:"Bob";s:8:"LastName";s:9:"Partridge";s:12:"EmailAddress";s:22:"secretarybob@bsclub.co";s:13:"SkypeUserName";s:10:"bayside577";s:18:"BankAccountDetails";s:19:"98-0918-03451237-00";s:9:"TaxNumber";s:10:"90-987-789";s:25:"AccountsReceivableTaxType";s:7:"OUTPUT2";s:22:"AccountsPayableTaxType";s:7:"OUTPUT2";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:7:{s:11:"AddressType";s:6:"STREET";s:12:"AddressLine1";s:20:"148 Bay Harbour Road";s:4:"City";s:13:"Ridge Heights";s:6:"Region";s:11:"Madeupville";s:10:"PostalCode";s:4:"6001";s:7:"Country";s:11:"New Zealand";s:11:"AttentionTo";s:14:"Club Secretary";}i:1;a:2:{s:11:"AddressType";s:5:"POBOX";s:11:"AttentionTo";s:14:"Club Secretary";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:3:{s:9:"PhoneType";s:3:"DDI";s:11:"PhoneNumber";s:7:"2024418";s:13:"PhoneAreaCode";s:2:"02";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"2024455";s:13:"PhoneAreaCode";s:2:"02";}i:2;a:3:{s:9:"PhoneType";s:6:"MOBILE";s:11:"PhoneNumber";s:7:"7774455";s:13:"PhoneAreaCode";s:2:"01";}i:3;a:3:{s:9:"PhoneType";s:3:"FAX";s:11:"PhoneNumber";s:7:"2025566";s:13:"PhoneAreaCode";s:2:"02";}}}s:14:"UpdatedDateUTC";s:23:"2011-05-18T20:49:58.507";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";s:15:"DefaultCurrency";s:3:"NZD";}i:10;a:10:{s:9:"ContactID";s:36:"e1120641-c8d5-4e51-962c-52d1d1086a92";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"Basket Case";s:9:"FirstName";s:4:"Mary";s:8:"LastName";s:4:"Munn";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:3:{s:9:"PhoneType";s:6:"MOBILE";s:11:"PhoneNumber";s:7:"7773001";s:13:"PhoneAreaCode";s:2:"01";}i:3;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"9176665";s:13:"PhoneAreaCode";s:2:"02";}}}s:14:"UpdatedDateUTC";s:22:"2011-05-15T00:38:39.76";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:11;a:11:{s:9:"ContactID";s:36:"9b9ba9e5-e907-4b4e-8210-54d82b0aa479";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"PowerDirect";s:18:"BankAccountDetails";s:18:"99-9009-1234567-00";s:9:"TaxNumber";s:19:"15% GST on Expenses";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:5:{s:11:"AddressType";s:5:"POBOX";s:12:"AddressLine1";s:12:"P O Box 8900";s:12:"AddressLine2";s:22:"Central Mailing Centre";s:4:"City";s:7:"Oaktown";s:10:"PostalCode";s:4:"1288";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"887612";s:13:"PhoneAreaCode";s:3:"800";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:3:{s:9:"PhoneType";s:3:"FAX";s:11:"PhoneNumber";s:6:"887613";s:13:"PhoneAreaCode";s:3:"800";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:40.043";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";s:15:"DefaultCurrency";s:3:"NZD";}i:12;a:8:{s:9:"ContactID";s:36:"0e0639b6-0153-4cf1-8dcd-5da1a6cb4d62";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:15:"Rex Media Group";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"4546789";s:13:"PhoneAreaCode";s:2:"01";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-23T23:52:34.47";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:13;a:9:{s:9:"ContactID";s:36:"fef6755f-549b-4617-b1e9-60bdffb517d8";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:19:"Ridgeway University";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:2:{s:11:"AddressType";s:5:"POBOX";s:11:"AttentionTo";s:13:"Accounts Dept";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"8005001";s:13:"PhoneAreaCode";s:2:"01";}i:1;a:3:{s:9:"PhoneType";s:6:"MOBILE";s:11:"PhoneNumber";s:7:"7775001";s:13:"PhoneAreaCode";s:2:"01";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-27T01:18:23.12";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";s:15:"DefaultCurrency";s:3:"NZD";}i:14;a:8:{s:9:"ContactID";s:36:"2a7f9bc5-d0fe-40bf-834d-6c7be44307e7";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:17:"Woolworths Market";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:40.197";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:15;a:8:{s:9:"ContactID";s:36:"6f57ea79-6d24-4a5b-8742-6f00662740dd";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:14:"Capital Cab Co";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"235689";s:13:"PhoneAreaCode";s:3:"800";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-22T02:00:07.74";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:16;a:8:{s:9:"ContactID";s:36:"add5b147-1013-4737-9641-7813d91b7fa9";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"Gable Print";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.853";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:17;a:8:{s:9:"ContactID";s:36:"16b4cdc2-b36a-4b25-b9c8-7a50c17b10ba";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:27:"Truxton Property Management";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"9157357";s:13:"PhoneAreaCode";s:2:"02";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:40.183";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:18;a:8:{s:9:"ContactID";s:36:"19ca28fa-75ac-4994-b479-7c2ec9348c74";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"PC Complete";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"322600";s:13:"PhoneAreaCode";s:3:"800";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.507";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:19;a:8:{s:9:"ContactID";s:36:"779a7956-6600-444e-90fd-860eaa30901c";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:22:"Fulton Airport Parking";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-10T04:55:39.84";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:20;a:10:{s:9:"ContactID";s:36:"bde095a6-1c01-4e1d-b6f4-9190cfe89a9c";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:13:"ABC Furniture";s:9:"FirstName";s:5:"Trish";s:8:"LastName";s:8:"Rawlings";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"124578";s:13:"PhoneAreaCode";s:3:"800";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-22T02:00:07.63";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:21;a:8:{s:9:"ContactID";s:36:"baf81bcb-eebf-4b8a-977d-95b11f593800";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:10:"Berry Brew";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-10T04:55:39.59";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:22;a:10:{s:9:"ContactID";s:36:"46aa21e5-da3d-4e09-a5c5-9e5f3c996ad7";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:15:"City Limousines";s:9:"FirstName";s:6:"Martin";s:8:"LastName";s:4:"Dale";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:2:{s:11:"AddressType";s:5:"POBOX";s:11:"AttentionTo";s:13:"Accounts Dept";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"8004001";s:13:"PhoneAreaCode";s:2:"01";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:3:{s:9:"PhoneType";s:6:"MOBILE";s:11:"PhoneNumber";s:7:"7774001";s:13:"PhoneAreaCode";s:2:"01";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-27T01:18:22.543";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:23;a:8:{s:9:"ContactID";s:36:"873c07a8-511a-420d-a664-a01a50834730";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:23:"Office Supplies Company";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-10T04:55:39.98";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:24;a:8:{s:9:"ContactID";s:36:"c37099f9-b207-4c0a-84de-a635eea70167";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:11:"Espresso 31";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.823";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:25;a:8:{s:9:"ContactID";s:36:"4edc4b51-91eb-468c-aea8-a8a0cb6c9545";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:20:"Young Bros Transport";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"3435678";s:13:"PhoneAreaCode";s:2:"02";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-23T23:52:34.64";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:4:"true";}i:26;a:8:{s:9:"ContactID";s:36:"8c555bc8-8098-43cd-bfa1-acd5573db5d6";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:15:"Melrose Parking";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.947";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:27;a:8:{s:9:"ContactID";s:36:"a1eaf3b8-91dd-4bcb-9393-aee19fe70189";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:12:"SMART Agency";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"9159889";s:13:"PhoneAreaCode";s:2:"02";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.473";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:28;a:8:{s:9:"ContactID";s:36:"8696589a-a719-463b-8ff3-b58e0a08ab28";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:18:"Hamilton Smith Ltd";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"2345678";s:13:"PhoneAreaCode";s:2:"01";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-23T23:52:34.687";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:29;a:8:{s:9:"ContactID";s:36:"85fc5d12-c655-4587-91b4-b5bb77243bab";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:21:"Port & Philip Freight";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:2:{s:11:"AddressType";s:5:"POBOX";s:11:"AttentionTo";s:18:"Corporate Services";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"2323434";s:13:"PhoneAreaCode";s:2:"01";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-23T23:52:34.547";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:30;a:8:{s:9:"ContactID";s:36:"f8a34028-83c6-4cc2-91ed-bada1ba04ff5";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:21:"MCO Cleaning Services";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"5119119";s:13:"PhoneAreaCode";s:2:"02";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.427";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:31;a:8:{s:9:"ContactID";s:36:"c5f9cb5b-2125-4e70-b83e-beac7d9cc6b2";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:16:"Hoyt Productions";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"7411234";s:13:"PhoneAreaCode";s:2:"02";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.537";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:32;a:8:{s:9:"ContactID";s:36:"ae136984-5d9a-4025-a045-bf4dc42730ea";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:16:"Brunswick Petals";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:3:"FAX";}i:2;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-10T04:55:39.62";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:33;a:8:{s:9:"ContactID";s:36:"3987133b-4faa-4fa5-b703-d0cafb3f7177";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:17:"Swanston Security";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"112113";s:13:"PhoneAreaCode";s:3:"800";}i:2;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:40.167";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:34;a:10:{s:9:"ContactID";s:36:"342a5560-dbb7-408f-835b-d93d49511d6d";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:31:"DIISR - Small Business Services";s:12:"EmailAddress";s:14:"cad@diisr.govt";s:13:"SkypeUserName";s:12:"maggie-diisr";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:5:{s:11:"AddressType";s:5:"POBOX";s:12:"AddressLine1";s:8:"GPO 9566";s:4:"City";s:9:"Pinehaven";s:10:"PostalCode";s:4:"9862";s:11:"AttentionTo";s:20:"Corporate Accounting";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:3:{s:9:"PhoneType";s:3:"FAX";s:11:"PhoneNumber";s:7:"8009002";s:13:"PhoneAreaCode";s:2:"01";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"8009001";s:13:"PhoneAreaCode";s:2:"01";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-23T23:52:34.813";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}i:35;a:8:{s:9:"ContactID";s:36:"e884b133-0d53-4883-ae8e-eabe4afc7350";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:15:"Central Copiers";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:6:"244844";s:13:"PhoneAreaCode";s:3:"800";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-22T02:00:07.397";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:36;a:8:{s:9:"ContactID";s:36:"b10e50e4-53cf-4655-bd20-eb8c4b780f68";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:9:"Coco Cafe";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:1;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.793";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:37;a:8:{s:9:"ContactID";s:36:"c0a64e06-4d52-4d8c-bbb8-f34eb4089546";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:8:"7-Eleven";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"FAX";}i:1;a:1:{s:9:"PhoneType";s:3:"DDI";}i:2;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:3;a:1:{s:9:"PhoneType";s:6:"MOBILE";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.293";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:38;a:8:{s:9:"ContactID";s:36:"40c72a58-0a4a-4eae-a622-f3549405e2df";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:4:"Xero";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:4:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:4:"9376";s:13:"PhoneAreaCode";s:3:"438";s:16:"PhoneCountryCode";s:4:"0800";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:1:{s:9:"PhoneType";s:3:"FAX";}i:3;a:1:{s:9:"PhoneType";s:3:"DDI";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:40.213";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:39;a:8:{s:9:"ContactID";s:36:"2e29540e-1a00-4c23-bbc9-f4db029c7586";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:17:"Bayside Wholesale";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:3:"DDI";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:5:"55669";s:13:"PhoneAreaCode";s:3:"850";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-22T02:00:07.71";s:10:"IsSupplier";s:4:"true";s:10:"IsCustomer";s:5:"false";}i:40;a:8:{s:9:"ContactID";s:36:"f1d403d1-7d30-46c2-a2be-fc2bb29bd295";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:8:"24 Locks";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:5:"POBOX";}i:1;a:1:{s:11:"AddressType";s:6:"STREET";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:1:{s:9:"PhoneType";s:7:"DEFAULT";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:23:"2011-04-10T04:55:39.217";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:5:"false";}i:41;a:8:{s:9:"ContactID";s:36:"a8cb2a69-f2f4-4eb6-a240-ff51ee93dd74";s:13:"ContactStatus";s:6:"ACTIVE";s:4:"Name";s:9:"Bank West";s:9:"Addresses";a:1:{s:7:"Address";a:2:{i:0;a:1:{s:11:"AddressType";s:6:"STREET";}i:1;a:1:{s:11:"AddressType";s:5:"POBOX";}}}s:6:"Phones";a:1:{s:5:"Phone";a:4:{i:0;a:3:{s:9:"PhoneType";s:7:"DEFAULT";s:11:"PhoneNumber";s:7:"2023456";s:13:"PhoneAreaCode";s:2:"02";}i:1;a:1:{s:9:"PhoneType";s:6:"MOBILE";}i:2;a:1:{s:9:"PhoneType";s:3:"DDI";}i:3;a:1:{s:9:"PhoneType";s:3:"FAX";}}}s:14:"UpdatedDateUTC";s:22:"2011-04-14T00:46:05.36";s:10:"IsSupplier";s:5:"false";s:10:"IsCustomer";s:4:"true";}}}}}');
	// 	$response = new stdClass();
	// 	$response->body = $anyContact;
	// 	$expected = array();
	// 	$expected['Contact'] = $anyContact['Response']['Contacts']['Contact'];
	// 	$result = $this->Xero->_parseResponse($response);

	// 	$this->assertEqual($result, $expected);
	// }

	// function testGetOAuthRequestToken() {
	// 	$oAuthRequestToken = 'REQUEST';
	// 	$oAuthRequestTokenSecret = 'SECRET';
	// 	$oAuthVerifier = 'HANDLE';
	// 	$oAuthExpires = time();

	// 	$response = new CakeResponse();
	// 	$response->code = 200;
	// 	$response->body = 'oauth_token=0L2DWMNVUJSHDIP93NLFJK146ZSMVN&oauth_token_secret=';
	// 	$response->headers = array('Content-Type' => 'text');
	// 	$this->Http->expects($this->once())->method('request')->will($this->returnValue($response));

	// 	$expected = array(
	// 		'oauth_token' => '0L2DWMNVUJSHDIP93NLFJK146ZSMVN',
	// 		'oauth_token_secret' => '',
	// 	);

	// 	$result = $this->Xero->getOAuthRequestToken('/');
	// 	$this->assertEqual($expected, $result);
	// }
	
	// function testGetOAuthAccessToken() {
	// 	$oAuthRequestToken = 'REQUEST';
	// 	$oAuthRequestTokenSecret = 'SECRET';
	// 	$oAuthVerifier = 'HANDLE';
	// 	$oAuthExpires = time();
		
	// 	$response = new CakeResponse();
	// 	$response->code = 200;
	// 	$response->body = 'oauth_token=0L2DWMNVUJSHDIP93NLFJK146ZSMVN&oauth_token_secret=&oauth_session_handle=&oauth_expires_in='.$oAuthExpires;
	// 	$response->headers = array('Content-Type' => 'text');
	// 	$this->Http->expects($this->once())->method('request')->will($this->returnValue($response));
		
	// 	$expected = array(
	// 		'oauth_token' => '0L2DWMNVUJSHDIP93NLFJK146ZSMVN',
	// 		'oauth_token_secret' => '',
	// 		'oauth_session_handle' => '',
	// 		'oauth_expires_in' => $oAuthExpires
	// 	);
		
	// 	$result = $this->Xero->getOAuthAccessToken($oAuthRequestToken, $oAuthRequestTokenSecret, $oAuthVerifier);
	// 	$this->assertEqual($expected, $result);
	// }
	
	// function testValue() {
	// 	$result = $this->Xero->value('{$__cakeForeignKey__$}');
	// 	$this->assertEquals($result, '{$__cakeForeignKey__$}');

	// 	$result = $this->Xero->value(array('first', 2, 'third'));
	// 	$expected = array('"first"', 2, '"third"');
	// 	$this->assertEquals($expected, $result);
	// }
	
	// function testRequest() {
	// 	$this->markTestSkipped('Cannot test request method');
	// 	$config = array(
	// 		'oauth_token' => "",
	// 		'oauth_token_secret' => "",
	// 		'oauth_token_handle' => "",
	// 		'oauth_token_expires' => "",
	// 	);
		
	// 	$this->Xero->config = array_merge($this->Xero->config, $config);
		
	// 	$model = new TestModel();
	// 	$model->request = array(
	//     "uri" => array(
 //        "endpoint" => "Accounts",
 //        "path" => "Accounts",
 //      ),
	//     "ssl" => true,
	//     "auth" => array(
 //        "method" => "OAuth",
 //      ),
	//     "header" => array(
 //        "If-Modified-Since" => "2012-05-23 21:28:10",
 //      )
	// 	);
	// 	$response = $this->Xero->request($model);
	// 	$this->assertNotEmpty($response, 'No response from request');
	// 	pr($this->Xero); die;
		
	// }
	
	

}