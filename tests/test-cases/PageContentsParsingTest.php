<?php

define('PIECRUST_ROOT_DIR', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('PIECRUST_TEST_DATA_DIR', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'test-data' . DIRECTORY_SEPARATOR);

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'util.php';
require_once 'PieCrust.class.php';

class TestPage extends Page
{
	public static function create(PieCrust $pieCrust, $uri, $path)
	{
		$page = new TestPage($pieCrust, null);
		$page->uri = $uri;
		$page->path = $path;
		return $page;
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
	public function parsePageContentsDataProvider()
	{
		$data = array();
		
		$htmlFiles = new GlobIterator(PIECRUST_TEST_DATA_DIR . '*.html', (GlobIterator::CURRENT_AS_FILEINFO | GlobIterator::SKIP_DOTS));
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
        $pc = new PieCrust();
		$pc->setConfig(array(
			'site' => array('default_format' => 'none')
		));
		$p = TestPage::create($pc, '/test', $testFilename);
		
		// Get the stuff we are expecting.
		$yamlParser = new sfYamlParser();
		$expectedResults = $yamlParser->parse(file_get_contents($expectedResultsFilename));
		$expectedConfig = $p->validateConfig($expectedResults['config']);
		
		// Assert!
		$this->assertEquals($expectedConfig, $p->getConfig());
		$expectedContents = $expectedResults['contents'];
		$actualContents = $p->getContents();
		//die("/".$expectedContents."/".$actualContents."/");
		$this->assertEquals($expectedContents, $actualContents);
    }
}
