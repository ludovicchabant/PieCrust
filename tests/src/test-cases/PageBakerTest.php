<?php

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\Baker\PageBaker;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\Page\Page;


class PageBakerTest extends PHPUnit_Framework_TestCase
{
    public function getOutputPathDataProvider()
    {
        return array(
            // Pretty URLs
            array(
                '', 1, true,
                'index.html'
            ),
            array(
                '', 2, true,
                '2/index.html'
            ),
            array(
                'foo', 1, true,
                'foo/index.html'
            ),
            array(
                'foo', 2, true,
                'foo/2/index.html'
            ),
            array(
                'foo/bar', 1, true,
                'foo/bar/index.html'
            ),
            array(
                'foo/bar', 2, true,
                'foo/bar/2/index.html'
            ),
            array(
                'foo.ext', 1, true,
                'foo.ext/index.html'
            ),
            array(
                'foo.ext', 2, true,
                'foo.ext/2/index.html'
            ),
            // Non-pretty URLs
            array(
                '', 1, false,
                'index.html'
            ),
            array(
                '', 2, false,
                '2.html'
            ),
            array(
                'foo', 1, false,
                'foo.html'
            ),
            array(
                'foo', 2, false,
                'foo/2.html'
            ),
            array(
                'foo/bar', 1, false,
                'foo/bar.html'
            ),
            array(
                'foo/bar', 2, false,
                'foo/bar/2.html'
            ),
            array(
                'foo.ext', 1, false,
                'foo.ext'
            ),
            array(
                'foo.ext', 2, false,
                'foo/2.ext'
            )
        );
    }

    /**
     * @dataProvider getOutputPathDataProvider
     */
    public function testGetOutputPath($uri, $pageNumber, $prettyUrls, $expectedPath)
    {
        $app = new MockPieCrust();
        $page = new MockPage($app);
        $page->uri = $uri;
        $page->pageNumber = $pageNumber;
        if ($prettyUrls)
            $page->getConfig()->setValue('pretty_urls', true);

        $baker = new PageBaker('/tmp');
        $path = $baker->getOutputPath($page);
        $expectedPath = '/tmp/' . $expectedPath;
        $this->assertEquals($expectedPath, $path);
    }

    public function pageBakeDataProvider()
    {
        return array(
            array(false, 'blah', null, 'blah.html'),
            array(false, 'blah', array('pretty_urls' => true), 'blah/index.html'),
            array(false, 'blah.foo', null, 'blah.foo'),
            array(false, 'blah.foo', array('pretty_urls' => true), 'blah.foo/index.html'),

            array(true, 'blah', null, 'blah/index.html'),
            array(true, 'blah', array('pretty_urls' => false), 'blah.html'),
            array(true, 'blah.foo', null, 'blah.foo/index.html'),
            array(true, 'blah.foo', array('pretty_urls' => false), 'blah.foo'),
        );
    }

    /**
     * @dataProvider pageBakeDataProvider
     */
    public function testPageBake($prettyUrls, $name, $extraPageConfig, $expectedName)
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
                $name,
                $pageConfig,
                'Some contents.'
            );

        $app = new PieCrust(array('root' => vfsStream::url('root/kitchen')));
        $page = Page::createFromUri($app, '/' . $name);
        
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
            array(false, 'blah', 'blah.html'),
            array(false, 'blah.foo', 'blah.foo'),

            array(true, 'blah', 'blah/index.html'),
            array(true, 'blah.foo', 'blah.foo/index.html')
        );
    }

    /**
     * @dataProvider singlePageBakeDataProvider
     */
    public function testSinglePageBake($prettyUrls, $name, $expectedName)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => $prettyUrls,
                    'posts_fs' => 'flat',
                    'posts_per_page' => 5
                )))
            ->withPage(
                $name,
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
        $page = Page::createFromUri($app, '/' . $name);
        
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
            array(false, 'blah', 'blah.html', 'blah/2.html'),
            array(false, 'blah.foo', 'blah.foo', 'blah/2.foo'),

            array(true, 'blah', 'blah/index.html', 'blah/2/index.html'),
            array(true, 'blah.foo', 'blah.foo/index.html', 'blah.foo/2/index.html')
        );
    }

    /**
     * @dataProvider multiplePageBakeDataProvider
     */
    public function testMultiplePageBake($prettyUrls, $name, $expectedName1, $expectedName2)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'pretty_urls' => $prettyUrls,
                    'posts_fs' => 'flat',
                    'posts_per_page' => 5
                )))
            ->withPage(
                $name,
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
        $page = Page::createFromUri($app, '/' . $name);
        
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
            array('/', false, 'blah', 'blah.html', '/blah/foo.txt'),
            array('/', false, 'blah.bar', 'blah.bar', '/blah/foo.txt'),
            array('/root', false, 'blah', 'blah.html', '/root/blah/foo.txt'),
            array('/root', false, 'blah.bar', 'blah.bar', '/root/blah/foo.txt'),

            array('/', true, 'blah', 'blah/index.html', '/blah/foo.txt'),
            array('/', true, 'blah.bar', 'blah.bar/index.html', '/blah.bar/foo.txt'),
            array('/root', true, 'blah', 'blah/index.html', '/root/blah/foo.txt'),
            array('/root', true, 'blah.bar', 'blah.bar/index.html', '/root/blah.bar/foo.txt')
        );
    }

    /**
     * @dataProvider pageWithAssetDataProvider
     */
    public function testPageWithAsset($siteRoot, $prettyUrls, $name, $expectedName, $expectedAsset)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'root' => $siteRoot,
                    'pretty_urls' => $prettyUrls
                )))
            ->withPage(
                $name,
                array('layout' => 'none', 'format' => 'none'),
                "Some contents:\n{{ asset.foo }}"
            )
            ->withAsset('_content/pages/blah-assets/foo.txt', 'FOO!');

        $app = new PieCrust(array('root' => vfsStream::url('root/kitchen')));
        $page = Page::createFromUri($app, '/' . $name);
        
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
            "Some contents:\n" . $expectedAsset,
            file_get_contents(vfsStream::url('root/counter/' . $expectedName))
        );
        $expectedAssetPath = substr($expectedAsset, strlen($siteRoot));
        $this->assertFileExists(vfsStream::url('root/counter/' . $expectedAssetPath));
        $this->assertEquals(
            'FOO!',
            file_get_contents(vfsStream::url('root/counter/' . $expectedAssetPath))
        );
    }
}
