<?php

namespace PieCrust\Tests;

use PieCrust\Baker\Processors\IProcessor;
use PieCrust\Baker\Processors\SimpleFileProcessor;


class SimpleFileProcessorTest extends PieCrustTestCase
{
    public function processFileDataProvider()
    {
        return array(
            array(
                'less', 'css', 'path/to/some/file.less', 'file.css'
            ),
            array(
                array('sass', 'scss'), 'css', '/path/to/some/file.sass', 'file.css'
            ),
            array(
                array('sass', 'scss'), 'css', '/path/to/some/file.scss', 'file.css'
            ),
            array(
                array('one', 'two'), array('1', '2'), '/path/to/some/file.one', 'file.1'
            ),
            array(
                array('one', 'two'), array('1', '2'), '/path/to/some/file.two', 'file.2'
            )
        );
    }
    
    /**
     * @dataProvider processFileDataProvider
     */
    public function testProcessFile($inputExtensions, $outputExtensions, $inputPath, $expectedOutputPath)
    {
        $sfp = new SimpleFileProcessor('test', $inputExtensions, $outputExtensions);
        $outputPath = $sfp->getOutputFilenames($inputPath);
        $this->assertEquals($expectedOutputPath, $outputPath);
    }
}
