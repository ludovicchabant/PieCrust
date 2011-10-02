<?php

require_once 'TestSuite.php';


function for_all(array $arr, $func)
{
    return array_reduce(
		$arr,
        function ($a, $v) use ($func)
		{
            return $a && call_user_func($func, $v);
        },
		true);
}

class PHPUnit_WebReport_Report
{
	public $testSuites;
	
	public function hasErrors() { return for_all($this->testSuites, function($ts) { return $ts->hasErrors(); }); }
	public function hasFailures() { return for_all($this->testSuites, function($ts) { return $ts->hasFailures(); }); }
	
	public function testCount() { return array_reduce($this->testSuites, function ($a, $v) { return $a += $v->testCount(); }); }
	public function errorCount() { return array_reduce($this->testSuites, function ($a, $v) { return $a += $v->errorCount(); }); }
	public function failureCount() { return array_reduce($this->testSuites, function ($a, $v) { return $a += $v->failureCount(); }); }
	public function assertionCount() { return array_reduce($this->testSuites, function ($a, $v) { return $a += $v->assertionCount(); }); }
	public function testTime() { return array_reduce($this->testSuites, function ($a, $v) { return $a += $v->testTime(); }); }
	
	public function __construct()
	{
		$this->testSuites = array();
	}
}
