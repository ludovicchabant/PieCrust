<?php

require_once 'unittest_setup.php';

require_once 'vfsStream/vfsStream.php';

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
    
    /**
     * @dataProvider parsePageContentsDataProvider
     */
    public function testParsePageContents($testFilename, $expectedResultsFilename)
    {
        $structure = array(
            '_content' => array(
                'pages' => array()
            )
        );
        $root = vfsStream::create($structure);

        // Create the page that will load our test file.
        $pc = new MockPieCrust();
        $pc->setPagesDir(vfsStream::url('root/_content/pages/'));
        $pc->getConfig()->set(array(
            'site' => array(
                'root' => 'http://whatever/',
                'pretty_urls' => false,
                'default_format' => 'none',
                'default_template_engine' => 'none'
            )
        ));
        $pc->addTemplateEngine('mustache', 'MustacheTemplateEngine');
        $pc->addTemplateEngine('twig', 'TwigTemplateEngine');
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
