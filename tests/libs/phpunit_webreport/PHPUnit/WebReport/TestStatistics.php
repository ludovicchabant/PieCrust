<?php

class PHPUnit_WebReport_TestStatistics
{
	public $stats;
	public $name;
	
	public function hasErrors() { return $this->stats['errors'] > 0; }
	public function hasFailures() { return $this->stats['failures'] > 0; }
	
	public function testCount() { return $this->stats['tests']; }
	public function errorCount() { return $this->stats['errors']; }
	public function failureCount() { return $this->stats['failures']; }
	public function assertionCount() { return $this->stats['assertions']; }
	public function testTime() { return $this->stats['time']; }
	
	public function __construct()
	{
		$this->stats = array(
			'tests' => 0,
			'failures' => 0,
			'errors' => 0,
			'assertions' => 0,
			'time' => 0
		);
	}
}
