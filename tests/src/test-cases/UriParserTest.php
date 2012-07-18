<?php

use org\bovigo\vfs\vfsStream;
use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Page\Page;
use PieCrust\Util\UriParser;


class UriParserTest extends PHPUnit_Framework_TestCase
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
        $pagesDir = vfsStream::url('root/kitchen/_content/pages/');
        $postsDir = vfsStream::url('root/kitchen/_content/posts/');
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
                $this->makeUriInfo('cat/something', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', false, 1, Page::TYPE_CATEGORY, 'blog', 'something')
            ),
            array(
                array(),
                '/cat/something/2',
                $this->makeUriInfo('cat/something', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', false, 2, Page::TYPE_CATEGORY, 'blog', 'something')
            ),
            array(
                array(),
                '/cat/some-thing_',
                $this->makeUriInfo('cat/some-thing_', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', false, 1, Page::TYPE_CATEGORY, 'blog', 'some-thing_')
            ),
            array(
                array(),
                '/tag/blah',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', false, 1, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/blah/2',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', false, 2, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/bl_ah-h',
                $this->makeUriInfo('tag/bl_ah-h', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', false, 1, Page::TYPE_TAG, 'blog', 'bl_ah-h')
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

        $fs = MockFileSystem::create()
            ->withConfig($config)
            ->withPostsDir()
            ->withPage('_index')
            ->withPage('existing')
            ->withPage('ex-is-ting')
            ->withPage('exist_ing')
            ->withPage('extended.foo')
            ->withPage('ext-ended.foo')
            ->withPage('extend_ed.foo');

        $pc = new PieCrust(array('root' => $fs->siteRootUrl(), 'debug' => true, 'cache' => false));
        $uriInfo = UriParser::parseUri($pc, $uri);
        $this->assertEquals($expectedUriInfo, $uriInfo, 'The URI info was not what was expected.');
    }

    public function testParseRegularOnlyUri()
    {
        $fs = MockFileSystem::create()
            ->withPage('existing-page');

        $pc = new PieCrust(array('root' => $fs->siteRootUrl(), 'cache' => false));
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

        $pc = new PieCrust(array('root' => $fs->siteRootUrl(), 'cache' => false));
        $uriInfo = UriParser::parseUri($pc, '/non-existing-page', UriParser::PAGE_URI_REGULAR);
        $this->assertNull($uriInfo);
    }
}
