<?php

namespace PieCrust\Tests;

use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;
use PieCrust\Util\PathHelper;


class PageIteratorTest extends PieCrustTestCase
{
    public function testSkip()
    {
        $fs = MockFileSystem::create()
            ->withSimpleDummyPosts(12)
            ->withPage('/foo', array('format' => 'none'), <<<EOD
{% for p in blog.posts.skip(5) %}
{{p.title}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
Test Title 6
Test Title 5
Test Title 4
Test Title 3
Test Title 2
Test Title 1
Test Title 0

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testLimit()
    {
        $fs = MockFileSystem::create()
            ->withSimpleDummyPosts(12)
            ->withPage('/foo', array('format' => 'none'), <<<EOD
{% for p in blog.posts.limit(4) %}
{{p.title}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
Test Title 11
Test Title 10
Test Title 9
Test Title 8

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testSlice()
    {
        $fs = MockFileSystem::create()
            ->withSimpleDummyPosts(12)
            ->withPage('/foo', array('format' => 'none'), <<<EOD
{% for p in blog.posts.slice(3, 4) %}
{{p.title}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
Test Title 8
Test Title 7
Test Title 6
Test Title 5

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testSort()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('post_url' => '%slug%')))
            ->withPost('post1', 1, 1, 2012, array('foo' => 4), '')
            ->withPost('post2', 2, 1, 2012, array('foo' => 2), '')
            ->withPost('post3', 3, 1, 2012, array('foo' => 1), '')
            ->withPost('post4', 4, 1, 2012, array('foo' => 3), '')
            ->withPage('/foos', array('format' => 'none'), <<<EOD
{% for p in blog.posts.sort('foo') %}
{{p.slug}}
{% endfor %}
EOD
            )
            ->withPage('/inv-foos', array('format' => 'none'), <<<EOD
{% for p in blog.posts.sort('foo', true) %}
{{p.slug}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foos', false);
        $this->assertEquals(
            <<<EOD
post3
post2
post4
post1

EOD
            ,
            $page->getContentSegment()
        );
        $page = Page::createFromUri($pc, '/inv-foos', false);
        $this->assertEquals(
            <<<EOD
post1
post4
post2
post3

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testSortSubProperty()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withPage('foo/aaa', array('nav' => array('order' => 3)), 'AAA')
            ->withPage('foo/bbb', array('nav' => array('order' => 1)), 'BBB')
            ->withPage('foo/ccc', array('nav' => array('order' => 2)), 'CCC')
            ->withPage('foo/test', array(), <<<EOD
{% for p in siblings.sort('nav.order') %}
{% if p.is_self %}
MYSELF
{% else %}
{{p.nav.order}}: {{p.content}}
{% endif %}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo/test', false);
        $this->assertEquals(
            <<<EOD
MYSELF
1: BBB
2: CCC
3: AAA

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testFilter()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('post_url' => '%slug%')))
            ->withPost('post1', 1, 1, 2012, array('foo' => 'blah'), '')
            ->withPost('post2', 2, 1, 2012, array('foo' => 'bar'), '')
            ->withPost('post3', 3, 1, 2012, array('foo' => 'boh'), '')
            ->withPost('post4', 4, 1, 2012, array('foo' => 'blah'), '')
            ->withPage(
                '/foos',
                array(
                    'format' => 'none',
                    'blahs' => array('is_foo' => 'blah')
                ),
                <<<EOD
{% for p in blog.posts.filter('blahs') %}
{{p.slug}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foos', false);
        $this->assertEquals(
            <<<EOD
post4
post1

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testMagicFilter()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('post_url' => '%slug%')))
            ->withPost('post1', 1, 1, 2012, array('foo' => 'blah'), '')
            ->withPost('post2', 2, 1, 2012, array('foo' => 'bar'), '')
            ->withPost('post3', 3, 1, 2012, array('foo' => 'boh'), '')
            ->withPost('post4', 4, 1, 2012, array('foo' => 'blah'), '')
            ->withPage('/foos', array('format' => 'none'), <<<EOD
{% for p in blog.posts.is_foo('blah') %}
{{p.slug}}
{% endfor %}

{% for p in blog.posts.in_foo('bar') %}
{{p.slug}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foos', false);
        $this->assertEquals(
            <<<EOD
post4
post1

post2

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testSortDateWithPostsOnSameDay()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array(
                'post_url' => '%slug%',
                'default_format' => 'none',
                'posts_per_page' => 10
            )))
            ->withPost('post1', 31, 12, 2013, array('time' => '15:07:05'))
            ->withPost('post2', 31, 12, 2013, array('time' => '15:17:30'))
            ->withPost('post3', 31, 12, 2013, array('time' => '20:03:42'))
            ->withPost('post4', 1, 1, 2014, array('time' => '14:07:07'))
            ->withPost('post5', 1, 1, 2014, array('time' => '14:08:09'))
            ->withPost('post6', 1, 1, 2014, array('time' => '14:09:11'))
            ->withPost('post7', 1, 1, 2014, array('time' => '14:10:13'))
            ->withPost('post8', 1, 1, 2014, array('time' => '14:11:14'))
            ->withPage('/foos', array('format' => 'none'), <<<EOD
{% for p in pagination.posts %}
{{p.slug}}
{% endfor %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foos', false);
        $this->assertEquals(
            <<<EOD
post8
post7
post6
post5
post4
post3
post2
post1

EOD
            ,
            $page->getContentSegment()
        );
    }
}

