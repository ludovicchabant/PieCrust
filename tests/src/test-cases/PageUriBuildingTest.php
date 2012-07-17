<?php

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Page\Page;
use PieCrust\Util\UriBuilder;


class PageUriBuildingTest extends PHPUnit_Framework_TestCase
{
    public function buildUriDataProvider()
    {
        return array(
            array('test.html', 'test'),
            array('somewhere/test.html', 'somewhere/test'),
            array('_index.html', ''),
            array('somewhere/_index.html', 'somewhere/_index'),
            array('_index.html', '_index', '.html', false),
            array('somewhere/_index.html', 'somewhere/_index', '.html', false),
            array('foo.ext', 'foo.ext'),
            array('somewhere/foo.ext', 'somewhere/foo.ext'),
            array('foo.ext', 'foo', '.ext'),
            array('somewhere/foo.ext', 'somewhere/foo', '.ext'),
            array('backward\slash', 'backward/slash')
        );
    }

    /**
     * @dataProvider buildUriDataProvider
     */
    public function testBuildUri($relativePath, $expectedUri, $stripExtension = '.html', $stripIndex = true)
    {
        $uri = UriBuilder::buildUri($relativePath, $stripExtension, $stripIndex);
        $this->assertEquals($expectedUri, $uri);
    }
}

