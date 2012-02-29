<?php

require_once 'unittest_setup.php';

use PieCrust\PieCrust;
use PieCrust\Baker\PageBaker;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\Page\Page;


class PageBakerTest extends PHPUnit_Framework_TestCase
{
    public function testPageBake()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => false
                )))
            ->withPage(
                'blah',
                array('layout' => 'none', 'format' => 'none'),
                'Some contents.'
            );

        $app = new PieCrust(array('root' => vfsStream::url('root/kitchen')));
        $page = Page::createFromUri($app, '/blah');
        
        $baker = new PageBaker(vfsStream::url('root/counter'));
        $baker->bake($page);

        $this->assertFalse($baker->wasPaginationDataAccessed());
        $this->assertEquals(1, $baker->getPageCount());
        $this->assertEquals(array(vfsStream::url('root/counter/blah.html')), $baker->getBakedFiles());
        $this->assertFileExists(vfsStream::url('root/counter/blah.html'));
        $this->assertEquals("Some contents.", file_get_contents(vfsStream::url('root/counter/blah.html')));
    }

    public function testSinglePageBake()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => false,
                    'posts_fs' => 'flat',
                    'posts_per_page' => 5
                )))
            ->withPage(
                'blah',
                array('layout' => 'none', 'format' => 'none'),
                <<<EOD
{% for post in pagination.posts %}
{{ post.title }}
{% endfor %}
EOD
            )
            ->withPost('slug1', 23, 8, 2011, array('title' => 'ONE'), '')
            ->withPost('slug2', 08, 9, 2011, array('title' => 'TWO'), '')
            ->withPost('slug3', 10, 9, 2011, array('title' => 'THREE'), '')
            ->withPost('slug4', 18, 9, 2011, array('title' => 'FOUR'), '');

        $app = new PieCrust(array(
            'root' => vfsStream::url('root/kitchen'),
            'cache' => false
        ));
        $page = Page::createFromUri($app, '/blah');
        
        $baker = new PageBaker(vfsStream::url('root/counter'));
        $baker->bake($page);

        $this->assertTrue($baker->wasPaginationDataAccessed());
        $this->assertEquals(1, $baker->getPageCount());
        $this->assertEquals(
            array(vfsStream::url('root/counter/blah/index.html')), 
            $baker->getBakedFiles());
        $this->assertFileExists(vfsStream::url('root/counter/blah/index.html'));
        $this->assertEquals("FOUR\nTHREE\nTWO\nONE\n", file_get_contents(vfsStream::url('root/counter/blah/index.html')));
    }

    public function testMultiplePageBake()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => false,
                    'posts_fs' => 'flat',
                    'posts_per_page' => 5
                )))
            ->withPage(
                'blah',
                array('layout' => 'none', 'format' => 'none'),
                <<<EOD
{% for post in pagination.posts %}
{{ post.title }}
{% endfor %}
EOD
            )
            ->withPost('slug1', 23, 8, 2011, array('title' => 'ONE'), '')
            ->withPost('slug2', 08, 9, 2011, array('title' => 'TWO'), '')
            ->withPost('slug3', 10, 9, 2011, array('title' => 'THREE'), '')
            ->withPost('slug4', 18, 9, 2011, array('title' => 'FOUR'), '')
            ->withPost('slug5', 22, 9, 2011, array('title' => 'FIVE'), '')
            ->withPost('slug6', 28, 9, 2011, array('title' => 'SIX'), '')
            ->withPost('slug7', 17, 10, 2011, array('title' => 'SEVEN'), '');

        $app = new PieCrust(array(
            'root' => vfsStream::url('root/kitchen'),
            'cache' => false
        ));
        $page = Page::createFromUri($app, '/blah');
        
        $baker = new PageBaker(vfsStream::url('root/counter'));
        $baker->bake($page);

        $this->assertTrue($baker->wasPaginationDataAccessed());
        $this->assertEquals(2, $baker->getPageCount());
        $this->assertEquals(
            array(
                vfsStream::url('root/counter/blah/index.html'),
                vfsStream::url('root/counter/blah/2/index.html')
            ), 
            $baker->getBakedFiles());
        $this->assertFileExists(vfsStream::url('root/counter/blah/index.html'));
        $this->assertFileExists(vfsStream::url('root/counter/blah/2/index.html'));
        $this->assertEquals("SEVEN\nSIX\nFIVE\nFOUR\nTHREE\n", file_get_contents(vfsStream::url('root/counter/blah/index.html')));
        $this->assertEquals("TWO\nONE\n", file_get_contents(vfsStream::url('root/counter/blah/2/index.html')));
    }

    public function testPageWithAsset()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => false
                )))
            ->withPage(
                'blah',
                array('layout' => 'none', 'format' => 'none'),
                "Some contents:\n{{ asset.foo }}"
            )
            ->withAsset('_content/pages/blah-assets/foo.txt', 'FOO!');

        $app = new PieCrust(array('root' => vfsStream::url('root/kitchen')));
        $page = Page::createFromUri($app, '/blah');
        
        $baker = new PageBaker(vfsStream::url('root/counter'), array('copy_assets' => true));
        $baker->bake($page);

        $this->assertFalse($baker->wasPaginationDataAccessed());
        $this->assertEquals(1, $baker->getPageCount());
        $this->assertEquals(array(vfsStream::url('root/counter/blah.html')), $baker->getBakedFiles());
        $this->assertFileExists(vfsStream::url('root/counter/blah.html'));
        $this->assertEquals(
            "Some contents:\n/blah/foo.txt",
            file_get_contents(vfsStream::url('root/counter/blah.html'))
        );
        $this->assertFileExists(vfsStream::url('root/counter/blah/foo.txt'));
        $this->assertEquals(
            'FOO!',
            file_get_contents(vfsStream::url('root/counter/blah/foo.txt'))
        );
    }
}
