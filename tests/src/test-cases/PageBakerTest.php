<?php

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\Baker\PageBaker;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\Page\Page;


class PageBakerTest extends PHPUnit_Framework_TestCase
{
    public function pageBakeDataProvider()
    {
        return array(
            array(false, null, 'blah.html'),
            array(true, null, 'blah/index.html'),
            array(false, array('pretty_urls' => true), 'blah/index.html'),
            array(true, array('pretty_urls' => false), 'blah.html'),
            array(false, array('content_type' => 'foo'), 'blah.foo'),
            array(true, array('content_type' => 'foo'), 'blah/index.html'),
            array(false, array('pretty_urls' => true, 'content_type' => 'foo'), 'blah/index.html'),
            array(true, array('pretty_urls' => false, 'content_type' => 'foo'), 'blah.foo')
        );
    }

    /**
     * @dataProvider pageBakeDataProvider
     */
    public function testPageBake($prettyUrls, $extraPageConfig, $expectedName)
    {
        $pageConfig = array(
            'layout' => 'none', 
            'format' => 'none'
        );
        if ($extraPageConfig)
            $pageConfig = array_merge($pageConfig, $extraPageConfig);

        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => $prettyUrls
                )))
            ->withPage(
                'blah',
                $pageConfig,
                'Some contents.'
            );

        $app = new PieCrust(array('root' => vfsStream::url('root/kitchen')));
        $page = Page::createFromUri($app, '/blah');
        
        $baker = new PageBaker(vfsStream::url('root/counter'));
        $baker->bake($page);

        $this->assertFalse($baker->wasPaginationDataAccessed());
        $this->assertEquals(1, $baker->getPageCount());
        $this->assertEquals(
            array(vfsStream::url('root/counter/' . $expectedName)), 
            $baker->getBakedFiles()
        );
        $this->assertFileExists(vfsStream::url('root/counter/' . $expectedName));
        $this->assertEquals(
            "Some contents.", 
            file_get_contents(vfsStream::url('root/counter/' . $expectedName))
        );
    }

    public function singlePageBakeDataProvider()
    {
        return array(
            array(false, 'blah.html'),
            array(true, 'blah/index.html')
        );
    }

    /**
     * @dataProvider singlePageBakeDataProvider
     */
    public function testSinglePageBake($prettyUrls, $expectedName)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => $prettyUrls,
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
            array(vfsStream::url('root/counter/' . $expectedName)), 
            $baker->getBakedFiles()
        );
        $this->assertFileExists(vfsStream::url('root/counter/' . $expectedName));
        $this->assertEquals(
            "FOUR\nTHREE\nTWO\nONE\n", 
            file_get_contents(vfsStream::url('root/counter/' . $expectedName))
        );
    }

    public function multiplePageBakeDataProvider()
    {
        return array(
            array(false, 'blah.html', 'blah/2.html'),
            array(true, 'blah/index.html', 'blah/2/index.html')
        );
    }

    /**
     * @dataProvider multiplePageBakeDataProvider
     */
    public function testMultiplePageBake($prettyUrls, $expectedName1, $expectedName2)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => $prettyUrls,
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
                vfsStream::url('root/counter/' . $expectedName1),
                vfsStream::url('root/counter/' . $expectedName2)
            ), 
            $baker->getBakedFiles()
        );
        $this->assertFileExists(vfsStream::url('root/counter/' . $expectedName1));
        $this->assertFileExists(vfsStream::url('root/counter/' . $expectedName2));
        $this->assertEquals(
            "SEVEN\nSIX\nFIVE\nFOUR\nTHREE\n", 
            file_get_contents(vfsStream::url('root/counter/' . $expectedName1))
        );
        $this->assertEquals(
            "TWO\nONE\n", 
            file_get_contents(vfsStream::url('root/counter/' . $expectedName2))
        );
    }

    public function pageWithAssetDataProvider()
    {
        return array(
            array(false, 'blah.html'),
            array(true, 'blah/index.html')
        );
    }

    /**
     * @dataProvider pageWithAssetDataProvider
     */
    public function testPageWithAsset($prettyUrls, $expectedName)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => $prettyUrls
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
        $this->assertEquals(
            array(vfsStream::url('root/counter/' . $expectedName)), 
            $baker->getBakedFiles()
        );
        $this->assertFileExists(vfsStream::url('root/counter/' . $expectedName));
        $this->assertEquals(
            "Some contents:\n/blah/foo.txt",
            file_get_contents(vfsStream::url('root/counter/' . $expectedName))
        );
        $this->assertFileExists(vfsStream::url('root/counter/blah/foo.txt'));
        $this->assertEquals(
            'FOO!',
            file_get_contents(vfsStream::url('root/counter/blah/foo.txt'))
        );
    }
}
