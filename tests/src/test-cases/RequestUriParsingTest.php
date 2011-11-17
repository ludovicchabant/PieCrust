<?php

require_once ('unittest_setup.php');

use PieCrust\Util\ServerHelper;


class RequestUriParsingTest extends PHPUnit_Framework_TestCase
{
    public function getRequestUriDataProvider()
    {
        return array(
            // Standard QUERY
            array(
                array('QUERY_STRING' => null),
                '/',
                false
            ),
            array(
                array('QUERY_STRING' => ''),
                '/',
                false
            ),
            array(
                array('QUERY_STRING' => '/'),
                '/',
                false
            ),
            array(
                array('QUERY_STRING' => '/blah'),
                '/blah',
                false
            ),
            array(
                array('QUERY_STRING' => '/some/path'),
                '/some/path',
                false
            ),
            array(
                array('QUERY_STRING' => null),
                '/',
                false
            ),
            array(
                array('QUERY_STRING' => ''),
                '/',
                false
            ),
            array(
                array('QUERY_STRING' => '/'),
                '/',
                false
            ),
            array(
                array('QUERY_STRING' => '/blah'),
                '/blah',
                false
            ),
            array(
                array('QUERY_STRING' => '/some/path'),
                '/some/path',
                false
            ),
            // URL rewriting queries
            array(
                array('QUERY_STRING' => null, 'REQUEST_URI' => '/'),
                '/',
                true
            ),
            array(
                array('QUERY_STRING' => null, 'REQUEST_URI' => '/blah'),
                '/blah',
                true
            ),
            array(
                array('QUERY_STRING' => '/something/else', 'REQUEST_URI' => '/blah'),
                '/blah',
                true
            )
        );
    }

    /**
     * @dataProvider getRequestUriDataProvider
     */
    public function testGetRequestUri($serverVars, $expectedUri, $usingPrettyUrls)
    {
        $uri = ServerHelper::getRequestUri($serverVars, $usingPrettyUrls);
        $this->assertEquals($expectedUri, $uri);
    }
}
