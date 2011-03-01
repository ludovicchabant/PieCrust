<?php

require_once '../website/_piecrust/PieCrust.class.php';

class RequestUriParsingTest extends PHPUnit_Framework_TestCase
{
	public function getRequestUriDataProvider()
	{
		return array(
			// Standard QUERY
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => null),
				'/_index',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => ''),
				'/_index',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => '/'),
				'/_index',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => '/blah'),
				'/blah',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => '/some/path'),
				'/some/path',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/test'),
				array('QUERY_STRING' => null),
				'/_index',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/test'),
				array('QUERY_STRING' => ''),
				'/_index',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/test'),
				array('QUERY_STRING' => '/'),
				'/_index',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/test'),
				array('QUERY_STRING' => '/blah'),
				'/blah',
				false
			),
			array(
				array('host' => 'host.local', 'url_base' => '/test'),
				array('QUERY_STRING' => '/some/path'),
				'/some/path',
				false
			),
			// URL rewriting queries
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => null, 'REQUEST_URI' => '/'),
				'/_index',
				true
			),
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => null, 'REQUEST_URI' => '/blah'),
				'/blah',
				true
			),
			array(
				array('host' => 'host.local', 'url_base' => '/'),
				array('QUERY_STRING' => '/something/else', 'REQUEST_URI' => '/blah'),
				'/blah',
				true
			)
		);
	}

	/**
	 * @dataProvider getRequestUriDataProvider
	 */
	public function testGetRequestUri($parameters, $serverVars, $expectedUri, $usingPrettyUrls)
    {
        $pc = new PieCrust($parameters);
		$pc->setConfig(array('site' => array('pretty_urls' => $usingPrettyUrls)));
		$uri = $pc->getRequestUri($serverVars);
        $this->assertEquals($expectedUri, $uri);
    }
}
