<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Page\Page;
use PieCrust\Util\PathHelper;
use PieCrust\Util\UriParser;
use PieCrust\Mock\MockFileSystem;


class UriParserTest extends PieCrustTestCase
{
    protected function makeUriInfo($uri, $path, $wasPathChecked, $pageNumber = 1, $type = Page::TYPE_REGULAR, $blogKey = null, $key = null, $date = null)
    {
        return array(
                'uri' => $uri,
                'page' => $pageNumber,
                'type' => $type,
                'blogKey' => $blogKey,
                'key' => $key,
                'date' => $date,
                'path' => $path,
                'was_path_checked' => $wasPathChecked
            );
    }
    
    public function parseUriDataProvider()
    {
        $pagesDir = '%pages_dir%';
        $postsDir = '%posts_dir%';
        return array(
            array(
                array(),
                '',
                $this->makeUriInfo('', $pagesDir . '_index.html', true)
            ),
            array(
                array(),
                '/',
                $this->makeUriInfo('', $pagesDir . '_index.html', true)
            ),
            array(
                array(),
                '/2',
                $this->makeUriInfo('', $pagesDir . '_index.html', true, 2)
            ),
            array(
                array(),
                '/error500',
                $this->makeUriInfo('error500', $pagesDir . 'error500.html', true)
            ),
            array(
                array(),
                '/42things',
                $this->makeUriInfo('42things', $pagesDir . '42things.html', true)
            ),
            array(
                array(),
                '/existing',
                $this->makeUriInfo('existing', $pagesDir . 'existing.html', true)
            ),
            array(
                array(),
                '/ex-is-ting',
                $this->makeUriInfo('ex-is-ting', $pagesDir . 'ex-is-ting.html', true)
            ),
            array(
                array(),
                '/exist_ing',
                $this->makeUriInfo('exist_ing', $pagesDir . 'exist_ing.html', true)
            ),
            array(
                array(),
                '/ex-is-ting/2',
                $this->makeUriInfo('ex-is-ting', $pagesDir . 'ex-is-ting.html', true, 2)
            ),
            array(
                array(),
                '/extended.foo',
                $this->makeUriInfo('extended.foo', $pagesDir . 'extended.foo', true)
            ),
            array(
                array(),
                '/extended.foo/2',
                $this->makeUriInfo('extended.foo', $pagesDir . 'extended.foo', true, 2)
            ),
            array(
                array(),
                '/ext-ended.foo/2',
                $this->makeUriInfo('ext-ended.foo', $pagesDir . 'ext-ended.foo', true, 2)
            ),
            array(
                array(),
                '/extend_ed.foo/2',
                $this->makeUriInfo('extend_ed.foo', $pagesDir . 'extend_ed.foo', true, 2)
            ),
            array(
                array(),
                '/cat/something',
                $this->makeUriInfo('cat/something', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', true, 1, Page::TYPE_CATEGORY, 'blog', 'something')
            ),
            array(
                array(),
                '/cat/something/2',
                $this->makeUriInfo('cat/something', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', true, 2, Page::TYPE_CATEGORY, 'blog', 'something')
            ),
            array(
                array(),
                '/cat/some-thing_',
                $this->makeUriInfo('cat/some-thing_', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', true, 1, Page::TYPE_CATEGORY, 'blog', 'some-thing_')
            ),
            array(
                array(),
                '/tag/blah',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', true, 1, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/blah/2',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', true, 2, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/bl_ah-h',
                $this->makeUriInfo('tag/bl_ah-h', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', true, 1, Page::TYPE_TAG, 'blog', 'bl_ah-h')
            ),
            array(
                array(),
                '/blah',
                null
            ),
            array(
                array(),
                '/blah/2',
                null
            ),
            array(
                array(),
                '/blah.ext',
                null
            ),
            array(
                array(),
                '/blah.ext/2',
                null
            ),
            array(
                array(),
                '2011/02/03/some-post',
                $this->makeUriInfo('2011/02/03/some-post', $postsDir . '2011-02-03_some-post.html', false, 1, Page::TYPE_POST, 'blog', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(
                    'site' => array('blogs' => array('blogone', 'blogtwo'))
                ),
                '/blogone/2011/02/03/some-post',
                $this->makeUriInfo('blogone/2011/02/03/some-post', $postsDir . 'blogone/2011-02-03_some-post.html', false, 1, Page::TYPE_POST, 'blogone', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(
                    'site' => array('blogs' => array('blogone', 'blogtwo'))
                ),
                '/blogtwo/2011/02/03/some-post',
                $this->makeUriInfo('blogtwo/2011/02/03/some-post', $postsDir . 'blogtwo/2011-02-03_some-post.html', false, 1, Page::TYPE_POST, 'blogtwo', null, mktime(0, 0, 0, 2, 3, 2011))
            )
         );
    }

    /**
     * @dataProvider parseUriDataProvider
     */
    public function testParseUri($config, $uri, $expectedUriInfo)
    {
        if (!isset($config['site']))
            $config['site'] = array();
        $config['site']['root'] = 'http://whatever/';
        $config['site']['category_url'] = 'cat/%category%';
        // Prevent the URI parser from having to re-capture the file extension
        // to get a proper path, since this calls `glob`, which will fail against
        // a virtual file-system.
        $config['site']['auto_formats'] = array('html' => '', 'md' => false, 'textile' => false);

        $fs = MockFileSystem::create()
            ->withConfig($config)
            ->withPost('some-post', 3, 2, 2011)
            ->withPage('_index')
            ->withPage('_category')
            ->withPage('_tag')
            ->withPage('error500')
            ->withPage('42things')
            ->withPage('existing')
            ->withPage('ex-is-ting')
            ->withPage('exist_ing')
            ->withPage('extended.foo')
            ->withPage('ext-ended.foo')
            ->withPage('extend_ed.foo');

        $pc = new PieCrust(array('root' => $fs->getAppRoot(), 'debug' => true, 'cache' => false));
        $uriInfo = UriParser::parseUri($pc, $uri);

        if ($expectedUriInfo != null && isset($expectedUriInfo['path']))
        {
            $pagesDir = $fs->url('kitchen/_content/pages/');
            $postsDir = $fs->url('kitchen/_content/posts/');
            $expectedUriInfo['path'] = str_replace(
                array('%pages_dir%', '%posts_dir%'),
                array($pagesDir, $postsDir),
                $expectedUriInfo['path']);
        }

        $this->assertEquals($expectedUriInfo, $uriInfo, 'The URI info was not what was expected.');
    }

    public function testParseRegularOnlyUri()
    {
        $fs = MockFileSystem::create()
            ->withPage('existing-page');

        $pc = new PieCrust(array('root' => $fs->getAppRoot(), 'cache' => false));
        $uriInfo = UriParser::parseUri($pc, '/existing-page');
        $this->assertEquals(
            $this->makeUriInfo('existing-page', $fs->url('kitchen/_content/pages/existing-page.html'), true),
            $uriInfo
        );
    }

    public function testParseRegularOnlyUriThatDoesntExist()
    {
        $fs = MockFileSystem::create()
            ->withPage('existing-page');

        $pc = new PieCrust(array('root' => $fs->getAppRoot(), 'cache' => false));
        $uriInfo = UriParser::parseUri($pc, '/non-existing-page', UriParser::PAGE_URI_REGULAR);
        $this->assertNull($uriInfo);
    }

    public function parseAutoFormatUrisDataProvider()
    {
        $pagesDir = '%pages_dir%';
        $postsDir = '%posts_dir%';
        return array(
            array(
                array(),
                '/',
                $this->makeUriInfo('', $pagesDir . '_index.md', true)
            ),
            array(
                array(),
                'markdown/page',
                $this->makeUriInfo('markdown/page', $pagesDir . 'markdown/page.md', true)
            ),
            array(
                array(),
                'textile/page',
                $this->makeUriInfo('textile/page', $pagesDir . 'textile/page.text', true)
            ),
            array(
                array(),
                'normal/page',
                $this->makeUriInfo('normal/page', $pagesDir . 'normal/page.html', true)
            ),
            array(
                array(),
                '/cat/something',
                $this->makeUriInfo('cat/something', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.md', true, 1, Page::TYPE_CATEGORY, 'blog', 'something')
            ),
            array(
                array(),
                '/cat/something/2',
                $this->makeUriInfo('cat/something', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.md', true, 2, Page::TYPE_CATEGORY, 'blog', 'something')
            ),
            array(
                array(),
                '/cat/some-thing_',
                $this->makeUriInfo('cat/some-thing_', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.md', true, 1, Page::TYPE_CATEGORY, 'blog', 'some-thing_')
            ),
            array(
                array(),
                '/tag/blah',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.md', true, 1, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/blah/2',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.md', true, 2, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/bl_ah-h',
                $this->makeUriInfo('tag/bl_ah-h', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.md', true, 1, Page::TYPE_TAG, 'blog', 'bl_ah-h')
            ),
            array(
                array(),
                '2011/02/03/some-post',
                $this->makeUriInfo('2011/02/03/some-post', $postsDir . '2011-02-03_some-post.md', false, 1, Page::TYPE_POST, 'blog', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(),
                '2011/02/04/other-post',
                $this->makeUriInfo('2011/02/04/other-post', $postsDir . '2011-02-04_other-post.text', false, 1, Page::TYPE_POST, 'blog', null, mktime(0, 0, 0, 2, 4, 2011))
            )
        );
    }

    /**
     * @dataProvider parseAutoFormatUrisDataProvider
     */
    public function testParseAutoFormatUris($config, $uri, $expectedUriInfo)
    {
        if (!isset($config['site']))
            $config['site'] = array();
        $config['site']['category_url'] = 'cat/%category%';
        $config['site']['auto_formats'] = array(
            'md' => 'markdown',
            'text' => 'textile'
        );
        // We have to use a "real" mock FS (i.e. it will use real files instead
        // of vfsStream) because that's the place in the PieCrust code where we
        // need to use `glob()`, which isn't supported with virtual streams.
        $fs = MockFileSystem::create(true, true)
            ->withConfig($config)
            ->withPost('some-post', 3, 2, 2011, array(), 'Blah.', null, 'md')
            ->withPost('other-post', 4, 2, 2011, array(), 'Blah.', null, 'text')
            ->withPage('_index.md')
            ->withPage('_category.md')
            ->withPage('_tag.md')
            ->withPage('textile/page.text')
            ->withPage('markdown/page.md')
            ->withPage('normal/page.html');
        $pc = $fs->getApp();
        $uriInfo = UriParser::parseUri($pc, $uri);

        if ($expectedUriInfo != null && isset($expectedUriInfo['path']))
        {
            $pagesDir = $fs->url('kitchen/_content/pages/');
            $postsDir = $fs->url('kitchen/_content/posts/');
            $expectedUriInfo['path'] = str_replace(
                array('%pages_dir%', '%posts_dir%'),
                array($pagesDir, $postsDir),
                $expectedUriInfo['path']);
        }

        $this->assertEquals($expectedUriInfo, $uriInfo, 'The URI info was not what was expected.');
    }
}
