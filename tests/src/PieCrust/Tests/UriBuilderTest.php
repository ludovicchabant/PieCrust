<?php

namespace PieCrust\Tests;

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Page\Page;
use PieCrust\Util\UriBuilder;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class UriBuilderTest extends PieCrustTestCase
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
            array('épatant', '/epatant'),
            array('El Niño', '/el-nino')
        );
    }

    /**
     * @dataProvider buildTagUriDataProvider
     */
    public function testBuildTagUri($tag, $expectedUri)
    {
        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('blog/tag_url', '/%tag%');
        $uri = UriBuilder::buildTagUri($pc, 'blog', $tag);
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
        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('blog/category_url', '/%category%');
        $uri = UriBuilder::buildCategoryUri($pc, 'blog', $category);
        $this->assertEquals($expectedUri, $uri);
    }

    public function tagSlugifyDataProvider()
    {
        return array(
            array('foo', 'foo'),
            array('foo bar!', 'foo-bar'),
            array('foo/bar,oy', 'foo-bar-oy'),
            array('épatant', 'epatant'),
            array('.htaccess', 'htaccess'),
            array('functions.php', 'functions-php'),
            array('foo bar!', 'foo-bar', 'transliterate'),
            array('foo/bar,OY', 'foo-bar-OY', 'transliterate'),
            array('épatant', 'epatant', 'transliterate'),
            array('foo/bar,OY', 'foo-bar-oy', 'transliterate|lowercase'),
            array('épaTANT', 'epatant', 'transliterate|lowercase'),
            array('foo bar!', 'foo-bar', 'dash'),
            array('foo/bar,OY', 'foo-bar-OY', 'dash'),
            array('épatant', '-patant', 'dash'),
            array('foo bar!', 'foo-bar', 'encode'),
            array('foo/bar,OY', 'foo-bar-OY', 'encode'),
            array('épatant', '%C3%A9patant', 'encode'),
            array('Это тэг', '%D0%AD%D1%82%D0%BE-%D1%82%D1%8D%D0%B3', 'encode'),
            array('foo bar!', 'foo-bar', 'none'),
            array('foo/bar,oy', 'foo-bar-oy', 'none'),
            array('épatant', 'épatant', 'none')
        );
    }

    /**
     * @dataProvider tagSlugifyDataProvider
     */
    public function testTagSlugify($value, $expectedValue, $slugifyMode = null, $locale = null)
    {
        $fs = MockFileSystem::create();
        if ($slugifyMode)
        {
            $fs->withConfig(array('site' => array(
                'slugify' => $slugifyMode
            )));
        }
        $pc = $fs->getApp();
        $flags = $pc->getConfig()->getValue('site/slugify_flags');
        $actualValue = UriBuilder::slugify($value, $flags);
        $this->assertEquals($expectedValue, $actualValue);
    }
}

