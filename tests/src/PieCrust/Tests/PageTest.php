<?php

namespace PieCrust\Tests;

use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;
use PieCrust\Util\PathHelper;


class PageTest extends PieCrustTestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreatePageFromNullUri()
    {
        $app = new MockPieCrust();
        Page::createFromUri($app, null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreatePageFromNullPath()
    {
        $app = new MockPieCrust();
        Page::createFromPath($app, null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreatePageFromInvalidPath()
    {
        $app = new MockPieCrust();
        Page::createFromPath($app, 'something/missing.html');
    }

    /**
     * @expectedException \PieCrust\PieCrustException
     */
    public function testCreateInvalidPage()
    {
        $app = MockFileSystem::create()->getApp();
        Page::createFromUri($app, 'something/missing.html');
    }

    public function testCreateDefaultTagPage()
    {
        $app = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'tag_url' => 'tag/%tag%'
                )
            ))
            ->getApp();
        $page = Page::createFromUri($app, '/tag/foo');
        $expected = PathHelper::getUserOrThemePath($app, '_tag.html');
        $this->assertEquals($expected, $page->getPath());
    }

    public function testCreateDefaultCategoryPage()
    {
        $app = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'category_url' => 'cat/%category%',
                )
            ))
            ->getApp();
        $page = Page::createFromUri($app, '/cat/foo');
        $expected = PathHelper::getUserOrThemePath($app, '_category.html');
        $this->assertEquals($expected, $page->getPath());
    }

    public function testTagPages()
    {
        $app = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withPost('test1', 1, 1, 2012, array('tags' => array('tag one')), 'Test one')
            ->withPost('test2', 1, 2, 2012, array('tags' => array('foo')), 'Test two')
            ->withPost('test3', 1, 3, 2012, array(), 'Test three')
            ->withPost('test4', 1, 4, 2012, array('tags' => array('bar', 'tag one')), 'Test four')
            ->withPost('test5', 1, 5, 2012, array('tags' => array('foo', 'tag one')), 'Test five')
            ->withPage(
                '_tag', 
                array('layout' => 'none'), 
                "{{tag}}\n{% for p in pagination.posts %}\n{{p.content|raw}}\n{% endfor %}"
            )
            ->getApp();

        $page = Page::createFromUri($app, '/tag/foo');
        $this->assertEquals(
            "foo\nTest five\nTest two\n",
            $page->getContentSegment()
        );
        $page = Page::createFromUri($app, '/tag/tag-one');
        $this->assertEquals(
            "tag one\nTest five\nTest four\nTest one\n",
            $page->getContentSegment()
        );
    }

    public function testCategoryPages()
    {
        $app = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withPost('test1', 1, 1, 2012, array('category' => 'cat one'), 'Test one')
            ->withPost('test2', 1, 2, 2012, array('category' => 'foo'), 'Test two')
            ->withPost('test3', 1, 3, 2012, array('category' => 'foo'), 'Test three')
            ->withPost('test4', 1, 4, 2012, array('category' => 'cat one'), 'Test four')
            ->withPost('test5', 1, 5, 2012, array('category' => 'cat one'), 'Test five')
            ->withPage(
                '_category', 
                array('layout' => 'none'), 
                "{{category}}\n{% for p in pagination.posts %}\n{{p.content|raw}}\n{% endfor %}"
            )
            ->getApp();

        $page = Page::createFromUri($app, '/foo');
        $this->assertEquals(
            "foo\nTest three\nTest two\n",
            $page->getContentSegment()
        );
        $page = Page::createFromUri($app, '/cat-one');
        $this->assertEquals(
            "cat one\nTest five\nTest four\nTest one\n",
            $page->getContentSegment()
        );
    }
}

