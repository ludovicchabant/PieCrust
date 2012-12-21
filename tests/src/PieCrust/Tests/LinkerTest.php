<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Page\Linker;
use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;

class LinkerTest extends \PHPUnit_Framework_TestCase
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

    protected function assertLinkerIsPage($linker, $name, $uri, $isSelf)
    {
        $this->assertTrue(is_array($linker));
        $this->assertEquals($name, $linker['name']);
        $this->assertEquals($uri, $linker['uri']);
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
}

