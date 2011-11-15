<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\PieCrust;
use PieCrust\IPieCrust;
use PieCrust\Page\Page;
use PieCrust\Page\PageConfiguration;


class PageContentsParsingTest extends PHPUnit_Framework_TestCase
{   
    public function parsePageContentsDataProvider()
    {
        $data = array();
        
        $htmlFiles = new GlobIterator(PIECRUST_UNITTESTS_TEST_DATA_DIR . '/pages/*.html', (GlobIterator::CURRENT_AS_FILEINFO | GlobIterator::SKIP_DOTS));
        foreach ($htmlFiles as $htmlFile)
        {
            $info = pathinfo($htmlFile);
            $ymlFile = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.yml';
            if (!is_file($ymlFile)) continue;
            
            $data[] = array($htmlFile, $ymlFile);
        }
        
        return $data;
    }
    
    public function formatUriCallback($uri)
    {
        return 'http://whatever/?/'.$uri;
    }
    
    /**
     * @dataProvider parsePageContentsDataProvider
     */
    public function testParsePageContents($testFilename, $expectedResultsFilename)
    {
        // Create the page that will load our test file.
        $pc = $this->getMock('MockPieCrust', array('formatUri'));
        $pc->setPagesDir(PIECRUST_UNITTESTS_EMPTY_ROOT_DIR . 'pages/');
        $pc->setTemplatesDirs(array());
        $pc->getConfig()->set(array(
            'site' => array(
                'root' => 'http://whatever',
                'default_format' => 'none',
                'default_template_engine' => 'none'
            )
        ));
        $pc->addTemplateEngine('dwoo', 'DwooTemplateEngine');
        $pc->addTemplateEngine('haml', 'HamlTemplateEngine');
        $pc->addTemplateEngine('mustache', 'MustacheTemplateEngine');
        $pc->addTemplateEngine('twig', 'TwigTemplateEngine');
        $pc->expects($this->any())
           ->method('formatUri')
           ->will($this->returnCallback(array($this, 'formatUriCallback')));
        $p = new Page($pc, '/test', $testFilename);
        
        // Get the stuff we are expecting.
        $yamlParser = new sfYamlParser();
        $expectedResults = $yamlParser->parse(file_get_contents($expectedResultsFilename));
        $expectedConfig = PageConfiguration::getValidatedConfig($p, $expectedResults['config']);
        foreach ($expectedResults as $key => $content) // Add the segment names.
        {
            if ($key == 'config') continue;
            $expectedConfig['segments'][] = $key;
        }
        
        // Start asserting!
        $actualConfig = $p->getConfig()->get();
        $this->assertEquals($expectedConfig, $actualConfig, 'The configurations are not equivalent.');
        $actualSegments = $p->getContentSegments();
        foreach ($expectedResults as $key => $content)
        {
            if ($key == 'config') continue;
            
            $this->assertContains($key, $actualConfig['segments'], 'Expected a declared content segment named: ' . $key);
            $this->assertArrayHasKey($key, $actualSegments, 'Expected a content segment named: ' . $key);
            $this->assertEquals($content, $actualSegments[$key], 'The content segments are not equivalent.');
        }
    }
}
