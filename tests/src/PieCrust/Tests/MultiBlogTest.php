<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Mock\MockFileSystem;


class MultiBlogTest extends PieCrustTestCase
{
    public function testDefaultPostLayout()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array(
                'default_format' => 'none',
                'blogs' => array('otherblog', 'testblog')
            )))
            ->withTemplate(
                'post',
                'Title: {{ page.title }}'
            )
            ->withTemplate(
                'testblog/post',
                'Overridden, Title: {{ page.title }}'
            )
            ->withPost(
                'test1', 
                5, 8, 2012, 
                array('title' => 'test1 title'), 
                'Blah blah',
                'testblog'
            )
            ->withPost(
                'test2', 
                6, 8, 2012, 
                array('title' => 'test2 title'), 
                'Blah blah',
                'otherblog'
            );
        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $expectedContents = 'Overridden, Title: test1 title';
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/testblog/2012/08/05/test1.html'))
        );

        $expectedContents = 'Title: test2 title';
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/otherblog/2012/08/06/test2.html'))
        );
    }
}

