<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\Page\Page;
use PieCrust\PieCrust;


class TestPage extends Page
{
    public static function create(PieCrust $pieCrust, $uri, $path)
    {
        return new TestPage($pieCrust, $uri, $path);
    }
    
    public function validateConfig($config)
    {
        if (!is_array($config))
        {
            $config = array();
        }
        return $this->buildValidatedConfig($config);
    }
}

class PageContentsParsingTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        date_default_timezone_set('America/Los_Angeles');
    }
    
    public function parsePageContentsDataProvider()
    {
        $data = array();
        
        $htmlFiles = new GlobIterator(PIECRUST_UNITTESTS_TEST_DATA_DIR . '/*.html', (GlobIterator::CURRENT_AS_FILEINFO | GlobIterator::SKIP_DOTS));
        foreach ($htmlFiles as $htmlFile)
        {
            $info = pathinfo($htmlFile);
            $ymlFile = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.yml';
            if (!is_file($ymlFile)) continue;
            
            $data[] = array($htmlFile, $ymlFile);
        }
        
        return $data;
    }
    
    /**
     * @dataProvider parsePageContentsDataProvider
     */
    public function testParsePageContents($testFilename, $expectedResultsFilename)
    {
        // Create the page that will load our test file.
        $pc = new PieCrust(array('cache' => false, 'url_base' => 'http://doesnt-matter', 'root' => PIECRUST_UNITTESTS_EMPTY_ROOT_DIR));
        $pc->setConfig(array(
            'site' => array('default_format' => 'none')
        ));
        $p = TestPage::create($pc, '/test', $testFilename);
        
        // Get the stuff we are expecting.
        $yamlParser = new sfYamlParser();
        $expectedResults = $yamlParser->parse(file_get_contents($expectedResultsFilename));
        $expectedConfig = $p->validateConfig($expectedResults['config']);
        foreach ($expectedResults as $key => $content) // Add the segment names.
        {
            if ($key == 'config') continue;
            $expectedConfig['segments'][] = $key;
        }
        
        // Start asserting!
        $this->assertEquals($expectedConfig, $p->getConfig(), 'The configurations are not equivalent.');
        $actualSegments = $p->getContentSegments();
        foreach ($expectedResults as $key => $content)
        {
            if ($key == 'config') continue;
            
            $this->assertArrayHasKey($key, $actualSegments, 'Expected a content segment named: ' . $key);
            $this->assertEquals($content, $actualSegments[$key], 'The content segments are not equivalent.');
        }
    }
}
