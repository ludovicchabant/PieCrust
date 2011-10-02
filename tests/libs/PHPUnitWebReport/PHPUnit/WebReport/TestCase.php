<?php

require_once 'TestStatistics.php';

class PHPUnit_WebReport_TestCase
{
	public $errors;
	public $failures;
	
	public function hasErrors() { return count($this->errors) > 0; }
	public function hasFailures() { return count($this->failures) > 0; }
	
	public function __construct()
	{
		$this->errors = array();
		$this->failures = array();
	}
}

