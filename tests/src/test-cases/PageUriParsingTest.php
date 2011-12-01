<?php

require_once 'unittest_setup.php';

use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Page\Page;
use PieCrust\Util\UriParser;
use PieCrust\Util\UriBuilder;


class PageUriParsingTest extends PHPUnit_Framework_TestCase
{
    protected function makeUriInfo($uri, $path, $wasPathChecked, $type = Page::TYPE_REGULAR, $blogKey = null, $key = null, $date = null, $pageNumber = 1)
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
        $pagesDir = PIECRUST_UNITTESTS_TEST_WEBSITE_ROOT_DIR . '_content/pages/';
        $postsDir = PIECRUST_UNITTESTS_TEST_WEBSITE_ROOT_DIR . '_content/posts/';
        return array(
            array(
                array(),
                '/existing-page',
                $this->makeUriInfo('existing-page', $pagesDir . 'existing-page.html', true)
            ),
            array(
                array(),
                '/existing-page.ext',
                $this->makeUriInfo('existing-page.ext', $pagesDir . 'existing-page.html', true)
            ),
            array(
                array(),
                '/blah',
                $this->makeUriInfo('blah', $pagesDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html', false, Page::TYPE_CATEGORY, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/blah',
                $this->makeUriInfo('tag/blah', $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html', false, Page::TYPE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/blah.ext',
                null
            ),
            array(
                array(),
                '2011/02/03/some-post',
                $this->makeUriInfo('2011/02/03/some-post', $postsDir . '2011-02-03_some-post.html', false, Page::TYPE_POST, 'blog', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(
                    'site' => array('blogs' => array('blogone', 'blogtwo'))
                ),
                '/blogone/2011/02/03/some-post',
                $this->makeUriInfo('blogone/2011/02/03/some-post', $postsDir . 'blogone/2011-02-03_some-post.html', false, Page::TYPE_POST, 'blogone', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(
                    'site' => array('blogs' => array('blogone', 'blogtwo'))
                ),
                '/blogtwo/2011/02/03/some-post',
                $this->makeUriInfo('blogtwo/2011/02/03/some-post', $postsDir . 'blogtwo/2011-02-03_some-post.html', false, Page::TYPE_POST, 'blogtwo', null, mktime(0, 0, 0, 2, 3, 2011))
            )
         );
    }

    /**
     * @dataProvider parseUriDataProvider
     */
    public function testParseUri($config, $uri, $expectedUriInfo)
    {
        $pc = new PieCrust(array('root' => PIECRUST_UNITTESTS_TEST_WEBSITE_ROOT_DIR, 'debug' => true, 'cache' => false));
        $pc->getConfig()->set($config);
        $pc->getConfig()->setValue('site/root', 'http://whatever/');
        
        $uriInfo = UriParser::parseUri($pc, $uri);
        $this->assertEquals($expectedUriInfo, $uriInfo, 'The URI info was not what was expected.');
    }
}
