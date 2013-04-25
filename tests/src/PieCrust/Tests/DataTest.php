<?php

namespace PieCrust\Tests;

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\Page\Page;
use PieCrust\Data\PaginationData;
use PieCrust\Mock\MockFileSystem;


class DataTest extends PieCrustTestCase
{
    public function testPaginationData()
    {
        $fs = MockFileSystem::create()
            ->withPage(
                'blah',
                array('title' => "My Blah", 'foo' => "My Foo", 'format' => 'none'),
                'Some contents.'
            );

        $app = new PieCrust(array('root' => $fs->url('kitchen')));
        $page = Page::createFromUri($app, '/blah');

        $data = new PaginationData($page);
        $this->assertEquals('My Blah', $data['title']);
        $this->assertEquals('My Foo', $data['foo']);
        $this->assertEquals('/?/blah', $data['url']);
        $this->assertEquals('blah', $data['slug']);
        $this->assertEquals('Some contents.', $data['content']);
        $this->assertFalse($data['has_more']);
    }
}

