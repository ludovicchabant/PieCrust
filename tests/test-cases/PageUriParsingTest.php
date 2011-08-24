<?php

require_once 'TestEnvironment.inc.php';
require_once 'PieCrust.class.php';
require_once 'UriParser.class.php';
require_once 'UriBuilder.class.php';


class PageUriParsingTest extends PHPUnit_Framework_TestCase
{
    protected $rootDir;
    
    public function getRootDir()
    {
        if ($this->rootDir === null)
        {
            $this->rootDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'test-data' . DIRECTORY_SEPARATOR;
        }
        return $this->rootDir;
    }
    
    protected function makeUriInfo($uri, $path, $wasPathChecked, $type = PIECRUST_PAGE_REGULAR, $blogKey = null, $key = null, $date = null, $pageNumber = 1)
    {
        return array(
                'uri' => $uri,
                'page' => $pageNumber,
                'type' => $type,
                'blogKey' => $blogKey,
                'key' => $key,
                'date' => $date,
                'path' => str_replace('/', DIRECTORY_SEPARATOR, $path),
                'was_path_checked' => $wasPathChecked
            );
    }
    
    public function parseUriDataProvider()
    {
        $pagesDir = $this->getRootDir() . str_replace('/', DIRECTORY_SEPARATOR, '_content/pages/');
        $postsDir = $this->getRootDir() . str_replace('/', DIRECTORY_SEPARATOR, '_content/posts/');
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
                $this->makeUriInfo('blah', $pagesDir . PIECRUST_CATEGORY_PAGE_NAME . '.html', false, PIECRUST_PAGE_CATEGORY, 'blog', 'blah')
            ),
            array(
                array(),
                '/tag/blah',
                $this->makeUriInfo('tag/blah', $pagesDir . PIECRUST_TAG_PAGE_NAME . '.html', false, PIECRUST_PAGE_TAG, 'blog', 'blah')
            ),
            array(
                array(),
                '/blah.ext',
                null
            ),
            array(
                array(),
                '2011/02/03/some-post',
                $this->makeUriInfo('2011/02/03/some-post', $postsDir . '2011-02-03_some-post.html', false, PIECRUST_PAGE_POST, 'blog', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(
                    'site' => array('blogs' => array('blogone', 'blogtwo'))
                ),
                '/blogone/2011/02/03/some-post',
                $this->makeUriInfo('blogone/2011/02/03/some-post', $postsDir . 'blogone/2011-02-03_some-post.html', false, PIECRUST_PAGE_POST, 'blogone', null, mktime(0, 0, 0, 2, 3, 2011))
            ),
            array(
                array(
                    'site' => array('blogs' => array('blogone', 'blogtwo'))
                ),
                '/blogtwo/2011/02/03/some-post',
                $this->makeUriInfo('blogtwo/2011/02/03/some-post', $postsDir . 'blogtwo/2011-02-03_some-post.html', false, PIECRUST_PAGE_POST, 'blogtwo', null, mktime(0, 0, 0, 2, 3, 2011))
            )
         );
    }

    /**
     * @dataProvider parseUriDataProvider
     */
    public function testParseUri($config, $uri, $expectedUriInfo)
    {
        $pc = new PieCrust(array('root' => $this->getRootDir(), 'debug' => true));
        $pc->setConfig($config);
        
        $uriInfo = UriParser::parseUri($pc, $uri);
        $this->assertEquals($expectedUriInfo, $uriInfo, 'The URI info was not what was expected.');
    }
}
