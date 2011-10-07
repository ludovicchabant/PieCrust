<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\Util\ServerHelper;


class ServerHelperTest extends PHPUnit_Framework_TestCase
{
    public function getRequestUriDataProvider()
    {
        return array(
            array(
                array('QUERY_STRING' => ''),
                false,
                '/'
            ),
            array(
                array('QUERY_STRING' => '/'),
                false,
                '/'
            ),
            array(
                array('QUERY_STRING' => '/blah'),
                false,
                '/blah'
            ),
            array(
                array('QUERY_STRING' => '/foo/bar'),
                false,
                '/foo/bar'
            ),
            array(
                array('QUERY_STRING' => '!debug'),
                false,
                '/'
            ),
            array(
                array('QUERY_STRING' => '/&!debug'),
                false,
                '/'
            ),
            array(
                array('QUERY_STRING' => '/foo/bar&!debug'),
                false,
                '/foo/bar'
            ),
            array(
                array('QUERY_STRING' => '/blah&!debug&!nocache'),
                false,
                '/blah'
            ),
            array(
                array('REQUEST_URI' => '/blah'),
                true,
                '/blah'
            ),
            array(
                array('REQUEST_URI' => '/blah?!debug'),
                true,
                '/blah'
            ),
            array(
                array('REQUEST_URI' => '/foo/bar'),
                true,
                '/foo/bar'
            ),
            array(
                array('REQUEST_URI' => '/foo/bar?!debug&!nocache'),
                true,
                '/foo/bar'
            )
        );
    }
    
    /**
     * @dataProvider getRequestUriDataProvider
     */
    public function testGetRequestUri($server, $prettyUrls, $expectedRequestUri)
    {
        $actualUri = ServerHelper::getRequestUri($server, $prettyUrls);
        $this->assertEquals($expectedRequestUri, $actualUri, 'The request URI was not what was expected.');
    }
}
