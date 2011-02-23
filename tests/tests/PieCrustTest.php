<?php

require_once '../website/_piecrust/PieCrust.class.php';

class PieCrustTest extends PHPUnit_Framework_TestCase
{
	public function getRequestUriDataProvider()
	{
		return array(
			// Standard QUERY
			array(
				'host.local', '/',
				array('QUERY_STRING' => null),
				'/_index',
				false
			),
			array(
				'host.local', '/',
				array('QUERY_STRING' => ''),
				'/_index',
				false
			),
			array(
				'host.local', '/',
				array('QUERY_STRING' => '/'),
				'/_index',
				false
			),
			array(
				'host.local', '/',
				array('QUERY_STRING' => '/blah'),
				'/blah',
				false
			),
			array(
				'host.local', '/',
				array('QUERY_STRING' => '/some/path'),
				'/some/path',
				false
			),
			array(
				'host.local', '/test',
				array('QUERY_STRING' => null),
				'/_index',
				false
			),
			array(
				'host.local', '/test',
				array('QUERY_STRING' => ''),
				'/_index',
				false
			),
			array(
				'host.local', '/test',
				array('QUERY_STRING' => '/'),
				'/_index',
				false
			),
			array(
				'host.local', '/test',
				array('QUERY_STRING' => '/blah'),
				'/blah',
				false
			),
			array(
				'host.local', '/test',
				array('QUERY_STRING' => '/some/path'),
				'/some/path',
				false
			),
			// URL rewriting queries
			array(
				'host.local', '/',
				array('QUERY_STRING' => null, 'REQUEST_URI' => '/'),
				'/_index',
				true
			),
			array(
				'host.local', '/',
				array('QUERY_STRING' => null, 'REQUEST_URI' => '/blah'),
				'/blah',
				true
			),
			array(
				'host.local', '/',
				array('QUERY_STRING' => '/something/else', 'REQUEST_URI' => '/blah'),
				'/blah',
				true
			)
		);
	}

	/**
	 * @dataProvider getRequestUriDataProvider
	 */
	public function testGetRequestUri($host, $urlBase, $serverVars, $expectedUri, $usingPrettyUrls)
    {
        $pc = new PieCrust($host, $urlBase);
		$pc->setConfig(array('site' => array('pretty_urls' => $usingPrettyUrls)));
		$uri = $pc->getRequestUri($serverVars);
        $this->assertEquals($expectedUri, $uri);
    }
}
