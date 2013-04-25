<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Data\DataBuilder;
use PieCrust\Page\Linker;
use PieCrust\Page\LinkData;
use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;

class LinkerTest extends PieCrustTestCase
{
    public function testOnePage()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), '');
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/', false);
        $linker = new Linker($page);

        $this->assertLinkerIsDirectory($linker, '', array('_index'));
        $this->assertLinkerIsPage($linker['_index'], '_index', '/?/', true);
    }

    public function testOnePageInSubDir()
    {
        $fs = MockFileSystem::create()
            ->withPage('foo/bar', array(), '');
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo/bar', false);
        $linker = new Linker($page);

        $this->assertLinkerIsDirectory($linker, 'foo', array('bar'));
        $this->assertLinkerIsPage($linker['bar'], 'bar', '/?/foo/bar', true);
    }

    public function testSeveralPages()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), '')
            ->withPage('foo', array(), '')
            ->withPage('other', array(), '')
            ->withPage('foo/bar', array(), '');
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $linker = new Linker($page);

        $this->assertLinkerIsDirectory($linker, '', array('_index', 'foo', 'other', 'foo_'));
        $this->assertLinkerIsPage($linker['_index'], '_index', '/?/', false);
        $this->assertLinkerIsPage($linker['foo'], 'foo', '/?/foo', true);
        $this->assertLinkerIsPage($linker['other'], 'other', '/?/other', false);
        $this->assertLinkerIsDirectory($linker['foo_'], 'foo', array('bar'));
        $this->assertLinkerIsPage($linker['foo_']['bar'], 'bar', '/?/foo/bar', false);
    }

    public function testSeveralPagesInSubDir()
    {
        $fs = MockFileSystem::create()
            ->withPage('foo/bar', array(), '')
            ->withPage('foo/other', array(), '')
            ->withPage('foo/bar/inside', array(), '');
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo/bar', false);
        $linker = new Linker($page);

        $this->assertLinkerIsDirectory($linker, 'foo', array('bar', 'other', 'bar_'));
        $this->assertLinkerIsPage($linker['bar'], 'bar', '/?/foo/bar', true);
        $this->assertLinkerIsPage($linker['other'], 'other', '/?/foo/other', false);
        $this->assertLinkerIsDirectory($linker['bar_'], 'bar', array('inside'));
        $this->assertLinkerIsPage($linker['bar_']['inside'], 'inside', '/?/foo/bar/inside', false);
    }

    public function testEmptySite()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), '');
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('_index', '/?/', true)
        ));
    }

    public function testSiteWithOnePage()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), '')
            ->withPage('foo', array('bar' => '42'), '');
        $pc = $fs->getApp();

        $page = Page::createFromUri($pc, '/', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('_index', '/?/', true),
            $this->makeLinkData('foo', '/?/foo', false, array('bar' => '42'))
        ));

        $page = Page::createFromUri($pc, '/foo', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('_index', '/?/'),
            $this->makeLinkData('foo', '/?/foo', true, array('bar' => '42'))
        ));
    }

    public function testSiteWithTwoPages()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), '')
            ->withPage('foo', array('bar' => '42'), '')
            ->withPage('foo/bar', array('baz' => 'none'), '');
        $pc = $fs->getApp();

        $page = Page::createFromUri($pc, '/', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('_index', '/?/', true),
            $this->makeLinkData('foo', '/?/foo', false, array('bar' => '42')),
            $this->makeLinkData('bar', '/?/foo/bar', false, array('baz' => 'none'))
        ));

        $page = Page::createFromUri($pc, '/foo', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('_index', '/?/'),
            $this->makeLinkData('foo', '/?/foo', true, array('bar' => '42')),
            $this->makeLinkData('bar', '/?/foo/bar', false, array('baz' => 'none'))
        ));

        $page = Page::createFromUri($pc, '/foo/bar', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('_index', '/?/'),
            $this->makeLinkData('foo', '/?/foo', false, array('bar' => '42')),
            $this->makeLinkData('bar', '/?/foo/bar', true, array('baz' => 'none'))
        ));
    }

    public function testSiteWithPageAssets()
    {
        $fs = MockFileSystem::create()
            ->withPage('foo', array(), '')
            ->withPageAsset('foo', 'bar1')
            ->withPageAsset('foo', 'bar2')
            ->withPage('something-assets', array(), '');
        $pc = $fs->getApp();

        $page = Page::createFromUri($pc, '/foo', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('foo', '/?/foo', true),
            $this->makeLinkData('something-assets', '/?/something-assets')
        ));

        $page = Page::createFromUri($pc, '/something-assets', false);
        $data = DataBuilder::getSiteData($page);
        $linker = $data['site']->pages();
        $this->assertLinkerIsPagesArray($linker, array(
            $this->makeLinkData('foo', '/?/foo'),
            $this->makeLinkData('something-assets', '/?/something-assets', true)
        ));
    }

    protected function assertLinkerIsPage($linker, $name, $uri, $isSelf)
    {
        $this->assertTrue($linker instanceof LinkData);
        $this->assertEquals($name, $linker['name']);
        $this->assertEquals($uri, $linker['url']);
        $this->assertFalse($linker['is_dir']);
        $this->assertEquals((bool)$isSelf, $linker['is_self']);
    }

    protected function assertLinkerIsDirectory($linker, $name, $pageKeys)
    {
        $this->assertTrue($linker instanceof Linker);
        $this->assertEquals($name, $linker->name());
        $this->assertTrue($linker->is_dir());
        $this->assertFalse($linker->is_self());
        $this->assertEquals(count($pageKeys), count($linker));
        foreach ($pageKeys as $key)
        {
            $this->assertTrue(isset($linker[$key]), "The linker doesn't contain page: " . $key);
        }
    }

    protected function assertLinkerIsPagesArray($linker, $pages)
    {
        $this->assertInstanceOf('\PieCrust\Page\Iteration\RecursiveLinkerIterator', $linker);

        $count = 0;
        foreach ($linker as $key => $actual)
        {
            $this->assertInstanceOf('\PieCrust\Page\LinkData', $actual);
            $this->assertLessThan(count($pages), $count);
            $expected = $pages[$count];
            $this->assertEquals($expected['name'], $key);
            foreach ($expected as $key => $value)
            {
                $this->assertEquals($value, $actual[$key]);
            }
            ++$count;
        }
        $this->assertEquals(count($pages), $count);
    }

    protected function makeLinkData($name, $url, $isSelf = false, $additionalData = null)
    {
        $data = array('name' => $name, 'url' => $url, 'is_self' => $isSelf);
        if ($additionalData != null)
        {
            $data = array_merge($data, $additionalData);
        }
        return $data;
    }
}

