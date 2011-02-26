<?php

require_once 'TestStatistics.php';
require_once 'TestCase.php';

class PHPUnit_WebReport_TestSuite extends PHPUnit_WebReport_TestStatistics
{
	public $testSuites;
	public $testCases;
	
	public function __construct()
	{
		$this->testSuites = array();
		$this->testCases = array();
	}
}
