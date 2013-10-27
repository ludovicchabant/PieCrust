<?php

namespace PieCrust\Tests;

use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\Page\Page;
use PieCrust\Page\PageConfiguration;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Util\PathHelper;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPage;
use PieCrust\Mock\MockPieCrust;


class PageContentsParsingTest extends PieCrustTestCase
{   
    public function parsePageContentsDataProvider()
    {
        $data = array();
        
        $htmlFiles = new \GlobIterator(
            PIECRUST_UNITTESTS_DATA_DIR . 'pages/*.html', 
            \GlobIterator::CURRENT_AS_FILEINFO | \GlobIterator::SKIP_DOTS
        );
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

    public function testSimpleFilterParsing()
    {
        $filterInfo = array('has_foo' => 'blah');
        $filter = new PaginationFilter();

        $this->assertFalse($filter->hasClauses());
        $filter->addClauses($filterInfo);
        $this->assertTrue($filter->hasClauses());
        
        $page = new MockPage();
        $this->assertFalse($filter->postMatches($page));
        $page->getConfig()->setValue('foo', array('nieuh'));
        $this->assertFalse($filter->postMatches($page));
        $page->getConfig()->setValue('foo', 'blah');
        $this->assertFalse($filter->postMatches($page));
        $page->getConfig()->setValue('foo', array('nieuh', 'blah'));
        $this->assertTrue($filter->postMatches($page));
    }

    public function testAndFilterParsing()
    {
        $filterInfo = array('and' => array(
            'has_foo'=> 'blah',
            'has_bar'=> 'nieuh'
        ));
        $filter = new PaginationFilter();
        $filter->addClauses($filterInfo);
        
        $page = new MockPage();
        $this->assertFalse($filter->postMatches($page));
        $page->getConfig()->setValue('foo', array('nieuh'));
        $this->assertFalse($filter->postMatches($page));
        $page->getConfig()->setValue('foo', array('nieuh', 'blah'));
        $this->assertFalse($filter->postMatches($page));
        $page->getConfig()->setValue('foo', array('blah'));
        $page->getConfig()->setValue('bar', array('nieuh'));
        $this->assertTrue($filter->postMatches($page));
    }

    public static function autoFormatExtensionsDataProvider()
    {
        return array(
            array(
                'foo',
                'markdown',
                "<h1>FOO!</h1>"
            ),
            array(
                'bar/baz',
                'textile',
                "<h1>Baz!</h1>"
            ),
            array(
                '2012/08/16/some-post',
                'markdown',
                "<h1>SOME POST</h1>"
            ),
            array(
                '2012/08/17/other-post',
                'textile',
                "<h1>Other Post</h1>"
            )
        );
    }

    /**
     * @dataProvider autoFormatExtensionsDataProvider
     */
    public function testAutoFormatExtensions($uri, $expectedFormat, $expectedContents)
    {
        $fs = MockFileSystem::create(true, true)
            ->withConfig(array(
                'site' => array(
                    'auto_formats' => array(
                        'md' => 'markdown',
                        'text' => 'textile'
                    )
                )
            ))
            ->withPage('foo.md', array(), "FOO!\n====\n")
            ->withPage('bar/baz.text', array(), "h1. Baz!\n")
            ->withPost('some-post', 16, 8, 2012, array(), "SOME POST\n=========\n", null, 'md')
            ->withPost('other-post', 17, 8, 2012, array(), "h1. Other Post\n", null, 'text');
        $app = $fs->getApp();

        $page = Page::createFromUri($app, $uri, false);
        $this->assertEquals($expectedFormat, $page->getConfig()->getValue('format'));
        $this->assertEquals($expectedContents, trim($page->getContentSegment()));
    }

    public function testMarkdownConfig()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'markdown' => array(
                    'config' => array('predef_urls' => array(
                        'ref1' => 'http://bolt80.com',
                        'ref2' => 'http://php.net'
                    ))
                )
            ))
            ->withPage(
                'foo.html', 
                array(), 
                "[FOO][ref1] and [BAR][ref2]"
            );
        $app = $fs->getApp();

        $expectedContents = <<<EOD
<p><a href="http://bolt80.com">FOO</a> and <a href="http://php.net">BAR</a></p>
EOD;

        $page = Page::createFromUri($app, 'foo', false);
        $this->assertEquals($expectedContents, trim($page->getContentSegment()));
    }
}
