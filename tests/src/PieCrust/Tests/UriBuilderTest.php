<?php

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Page\Page;
use PieCrust\Util\UriBuilder;
use PieCrust\Mock\MockPieCrust;


class UriBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function buildUriDataProvider()
    {
        $af1 = array('ext' => 'something');
        $af2 = array('ext' => 'something', 'other' => 'something_else');
        return array(
            array('test.html', 'test'),
            array('somewhere/test.html', 'somewhere/test'),
            array('_index.html', ''),
            array('somewhere/_index.html', 'somewhere/_index'),
            array('_index.html', '', $af1),
            array('somewhere/_index.html', 'somewhere/_index', $af1),
            array('_index.html', '', $af2),
            array('somewhere/_index.html', 'somewhere/_index', $af2),
            array('foo.ext', 'foo.ext'),
            array('somewhere/foo.ext', 'somewhere/foo.ext'),
            array('foo.ext', 'foo', $af1),
            array('somewhere/foo.ext', 'somewhere/foo', $af1),
            array('foo.ext', 'foo', $af2),
            array('somewhere/foo.ext', 'somewhere/foo', $af2),
            array('foo.other', 'foo.other', $af1),
            array('somewhere/foo.other', 'somewhere/foo.other', $af1),
            array('foo.other', 'foo', $af2),
            array('somewhere/foo.other', 'somewhere/foo', $af2),
            array('backward\slash', 'backward/slash')
        );
    }

    /**
     * @dataProvider buildUriDataProvider
     */
    public function testBuildUri($relativePath, $expectedUri, $autoFormats = null)
    {
        $pc = new MockPieCrust();
        if ($autoFormats)
        {
            $autoFormats['html'] = '';
            $pc->getConfig()->setValue('site/auto_formats', $autoFormats);
        }

        $uri = UriBuilder::buildUri($pc, $relativePath);
        $this->assertEquals($expectedUri, $uri);
    }

    public function buildTagUriDataProvider()
    {
        return array(
            array('foo', '/foo'),
            array(array('foo', 'bar'), '/foo/bar'),
            array('foo bar', '/foo-bar'),
            array('f o o   b a r', '/f-o-o-b-a-r'),
            array(array('f o o', 'b a r'), '/f-o-o/b-a-r'),
        );
    }

    /**
     * @dataProvider buildTagUriDataProvider
     */
    public function testBuildTagUri($tag, $expectedUri)
    {
        $uri = UriBuilder::buildTagUri('/%tag%', $tag);
        $this->assertEquals($expectedUri, $uri);
    }

    public function buildCategoryUriDataProvider()
    {
        return array(
            array('foo', '/foo'),
            array('foo bar', '/foo-bar'),
            array('f o o   b a r', '/f-o-o-b-a-r')
        );
    }

    /**
     * @dataProvider buildCategoryUriDataProvider
     */
    public function testBuildCategoryUri($category, $expectedUri)
    {
        $uri = UriBuilder::buildCategoryUri('/%category%', $category);
        $this->assertEquals($expectedUri, $uri);
    }
}

