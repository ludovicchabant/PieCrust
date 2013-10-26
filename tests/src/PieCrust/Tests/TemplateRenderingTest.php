<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Page\Page;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Mock\MockFileSystem;


class TemplateRenderingTest extends PieCrustTestCase
{
    public function renderTemplateDataProvider()
    {
        $data = array();
        
        $files = new \GlobIterator(
            PIECRUST_UNITTESTS_DATA_DIR . 'templates/*', 
            \GlobIterator::CURRENT_AS_FILEINFO | \GlobIterator::SKIP_DOTS
        );
        foreach ($files as $file)
        {
            $info = pathinfo($file);
            if ($info['extension'] == 'html')
                continue;
            
            $outFile = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.html';
            if (!is_file($outFile))
                continue;
            
            $data[] = array($file, $outFile);
        }
        
        return $data;
    }
    
    /**
     * @dataProvider renderTemplateDataProvider
     */
    public function testTemplateRendering($testFilename, $expectedResultsFilename)
    {
        // Render our template.
        $fs = MockFileSystem::create()->withPagesDir();
        $pc = $fs->getApp(array('cache' => false));
        $pc->getConfig()->setValue('site/root', 'http://whatever/');
        $pc->setTemplatesDirs(PIECRUST_UNITTESTS_DATA_DIR . 'templates');
        
        $testInfo = pathinfo($testFilename);
        $engine = PieCrustHelper::getTemplateEngine($pc, $testInfo['extension']);
        $this->assertNotNull($engine, "Couldn't find a template engine for extension: ".$testInfo['extension']);
        $this->assertEquals($testInfo['extension'], $engine->getExtension());
        ob_start();
        try
        {
            $data = $pc->getConfig()->get();
            $data['page'] = array(
                    'title' => 'The title of the page'
                );
            $engine->renderFile(array($testInfo['basename']), $data);
        }
        catch (Exception $e)
        {
            ob_end_clean();
            throw $e;
        }
        $actualResults = ob_get_clean();
        $actualResults = str_replace("\r\n", "\n", $actualResults);
        
        // Compare to what we are expecting.
        $expectedResults = file_get_contents($expectedResultsFilename);
        $expectedResults = str_replace("\r\n", "\n", $expectedResults);
        $this->assertEquals($expectedResults, $actualResults, 'The rendered template is not what we expected.');
    }

    public function testOverrideTemplate()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'templates_dirs' => '_content/override'
                )
            ))
            ->withPage(
                'blah', 
                array('layout' => 'default', 'format' => 'none'), 
                'Blah blah blah.'
            )
            ->withTemplate('default', 'DEFAULT TEMPLATE: {{content}}')
            ->withCustomTemplate('default', 'override', 'OVERRIDE TEMPLATE: {{content}}');
        $pc = $fs->getApp();

        $baker = new PieCrustBaker($pc);
        $baker->bake();

        $this->assertFileExists($fs->url('kitchen/_counter/blah.html'));
        $this->assertEquals(
            'OVERRIDE TEMPLATE: Blah blah blah.',
            file_get_contents($fs->url('kitchen/_counter/blah.html'))
        );
    }
}
