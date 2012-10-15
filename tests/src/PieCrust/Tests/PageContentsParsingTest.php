<?php

use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\Page\Page;
use PieCrust\Page\PageConfiguration;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


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
        $fs = MockFileSystem::create()->withPagesDir();

        // Create the page that will load our test file.
        $pc = new MockPieCrust();
        $pc->setPagesDir($fs->url('kitchen/_content/pages/'));
        $pc->getConfig()->set(array(
            'site' => array(
                'root' => 'http://whatever/',
                'pretty_urls' => false,
                'default_format' => 'none',
                'default_template_engine' => 'none'
            )
        ));
        // Default template engines.
        $pc->addTemplateEngine('mustache', 'MustacheTemplateEngine');
        $pc->addTemplateEngine('twig', 'TwigTemplateEngine');

        // Get the stuff we are expecting.
        $expectedResults = Yaml::parse(file_get_contents($expectedResultsFilename));

        // Add needed template engines/formatters.
        if (isset($expectedResults['needs']))
        {
            $needs = $expectedResults['needs'];
            if (isset($needs['formatter']))
            {
                foreach ($needs['formatter'] as $name => $f)
                {
                    $pc->addFormatter($name, $f);
                }
            }
            if (isset($needs['template_engine']))
            {
                foreach ($needs['template_engine'] as $name => $te)
                {
                    $pc->addTemplateEngine($name, $te);
                }
            }
            unset($expectedResults['needs']);
        }
        
        // Build a test page and get the full expected config.
        $p = new Page($pc, '/test', $testFilename);
        $expectedConfig = PageConfiguration::getValidatedConfig($p, $expectedResults['config']);
        unset($expectedResults['config']);
        foreach ($expectedResults as $key => $content) // Add the segment names.
        {
            $expectedConfig['segments'][] = $key;
        }
        
        // Start asserting!
        $actualConfig = $p->getConfig()->get();
        $this->assertEquals($expectedConfig, $actualConfig, 'The configurations are not equivalent.');
        $actualSegments = $p->getContentSegments();
        foreach ($expectedResults as $key => $content)
        {
            $this->assertContains($key, $actualConfig['segments'], 'Expected a declared content segment named: ' . $key);
            $this->assertArrayHasKey($key, $actualSegments, 'Expected a content segment named: ' . $key);
            $this->assertEquals($content, $actualSegments[$key], 'The content segments are not equivalent.');
        }
    }
}
