<?php

require_once 'TestEnvironment.inc.php';
require_once 'IFileProcessor.class.php';
require_once 'SimpleFileProcessor.class.php';


class SimpleFileProcessorTest extends PHPUnit_Framework_TestCase
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
        $pc = new PieCrust(array('root' => $this->getRootDir(), 'debug' => true));
        $sfp = new SimpleFileProcessor('test', $inputExtensions, $outputExtensions);
        $outputPath = $sfp->getOutputFilenames($inputPath);
        $this->assertEquals($expectedOutputPath, $outputPath);
    }
}
