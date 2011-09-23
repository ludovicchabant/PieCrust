<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\Page\Page;
use PieCrust\PieCrust;


class TemplateRenderingTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        date_default_timezone_set('America/Los_Angeles');
    }
    
    public function renderTemplateDataProvider()
    {
        $data = array();
        
        $files = new GlobIterator(PIECRUST_UNITTESTS_TEST_DATA_DIR . '/templates/*', (GlobIterator::CURRENT_AS_FILEINFO | GlobIterator::SKIP_DOTS));
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
        $pc = new PieCrust(array('cache' => false, 'url_base' => 'http://whatever', 'root' => PIECRUST_UNITTESTS_EMPTY_ROOT_DIR));
        $pc->setConfig(array());
        $pc->setTemplatesDirs(PIECRUST_UNITTESTS_TEST_DATA_DIR . '/templates');
        $testInfo = pathinfo($testFilename);
        $engine = $pc->getTemplateEngine($testInfo['extension']);
        $this->assertNotNull($engine, "Couldn't find a template engine for extension: ".$testInfo['extension']);
        ob_start();
        try
        {
            $data = $pc->getSiteData();
            $data['page'] = array(
                    'title' => 'The title of the page'
                );
            $engine->renderFile($testInfo['basename'], $data);
        }
        catch (Exception $e)
        {
            ob_end_clean();
            $this->assertTrue(false, $e->getMessage());
        }
        $actualResults = ob_get_clean();
        $actualResults = str_replace("\r\n", "\n", $actualResults);
        
        // Compare to what we are expecting.
        $expectedResults = file_get_contents($expectedResultsFilename);
        $expectedResults = str_replace("\r\n", "\n", $expectedResults);
        $this->assertEquals($expectedResults, $actualResults, 'The rendered template is not what we expected.');
    }
}
