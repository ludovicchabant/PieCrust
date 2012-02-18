<?php

require_once 'unittest_setup.php';

require_once 'vfsStream/vfsStream.php';

use PieCrust\PieCrust;
use PieCrust\Page\Page;


class TemplateRenderingTest extends PHPUnit_Framework_TestCase
{
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
        $structure = array(
            '_content' => array(
                'pages' => array(),
                'templates' => array(),
                'config.yml' => ''
            )
        );
        $root = vfsStream::create($structure);

        // Render our template.
        $pc = new PieCrust(array('cache' => false, 'root' => vfsStream::url('root')));
        $pc->getConfig()->setValue('site/root', 'http://whatever/');
        $pc->setTemplatesDirs(PIECRUST_UNITTESTS_TEST_DATA_DIR . '/templates');
        
        $testInfo = pathinfo($testFilename);
        $engine = $pc->getTemplateEngine($testInfo['extension']);
        $this->assertNotNull($engine, "Couldn't find a template engine for extension: ".$testInfo['extension']);
        $this->assertEquals($testInfo['extension'], $engine->getExtension());
        ob_start();
        try
        {
            $data = $pc->getConfig()->get();
            $data['page'] = array(
                    'title' => 'The title of the page'
                );
            $engine->renderFile($testInfo['basename'], $data);
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
}
