<?php

require_once 'PieCrust.class.php';

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
    
    protected function makeUriInfo($uri, $path, $wasPathChecked, $type = PIECRUST_PAGE_REGULAR, $key = null, $date = null, $pageNumber = 1)
    {
        return array(
                'uri' => $uri,
                'page' => $pageNumber,
                'type' => $type,
                'key' => $key,
                'date' => $date,
                'path' => $path,
                'was_path_checked' => $wasPathChecked
            );
    }
    
	public function parseUriDataProvider()
	{
        $pagesDir = $this->getRootDir() . str_replace('/', DIRECTORY_SEPARATOR, '_content/pages/');
		return array(
            array(
                '/existing-page',
                $this->makeUriInfo('existing-page', $pagesDir . 'existing-page.html', true)
            ),
            array(
                '/existing-page.ext',
                $this->makeUriInfo('existing-page.ext', $pagesDir . 'existing-page.html', true)
            ),
            array(
                '/blah',
                $this->makeUriInfo('blah', $pagesDir . PIECRUST_CATEGORY_PAGE_NAME . '.html', false, PIECRUST_PAGE_CATEGORY, 'blah')
            ),
            array(
                '/tag/blah',
                $this->makeUriInfo('tag/blah', $pagesDir . PIECRUST_TAG_PAGE_NAME . '.html', false, PIECRUST_PAGE_TAG, 'blah')
            ),
            array(
                '/blah.ext',
                $this->makeUriInfo('blah.ext', null, false)
            )
         );
	}

	/**
	 * @dataProvider parseUriDataProvider
	 */
	public function testParseUri($uri, $expectedUriInfo)
    {
        $pc = new PieCrust(array('root' => $this->getRootDir(), 'debug' => true));
        
        $format = $pc->getConfigValue('site', 'category_url');
        $pattern = Paginator::buildCategoryUrlPattern($format);
        $this->assertTrue(preg_match($pattern, 'blah.ext') == 0, 'WTF?');
        
		$uriInfo = Page::parseUri($pc, $uri);
        $this->assertEquals($expectedUriInfo, $uriInfo, 'The URI info was not what was expected.');
    }
}
