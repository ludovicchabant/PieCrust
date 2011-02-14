<?php

require_once 'Benchmark/Timer.php';
require_once 'Benchmark/Iterate.php';

define('PIECRUST_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once '../website/_piecrust/PieCrust.class.php';

define('BENCHMARKS_CACHE_DIR', PIECRUST_ROOT_DIR . '_cache');

function rmdir_recursive($dir, $level = 0)
{
	$dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR; 
    $files = glob($dir . '*', GLOB_MARK);
    foreach ($files as $file)
    {
    	if ($file == '.' or $file == '..' or $file = '.empty')
    	{
    		continue;
    	}
    	
        if(substr($file, -1) == '/')
        {
            rmdir_recursive($file, $level + 1);
        }
        else
        {
            unlink($file);
        }
    } 
    
    if ($level > 0 and is_dir($dir))
    {
    	rmdir($dir);
    }
}

function ensure_cache($ensureClean = true)
{
	if ($ensureClean and is_dir(BENCHMARKS_CACHE_DIR))
	{
		rmdir_recursive(BENCHMARKS_CACHE_DIR);
	}
	if (!is_dir(BENCHMARKS_CACHE_DIR))
	{
		mkdir(BENCHMARKS_CACHE_DIR);
	}
}

function run_query($bench, $pieCrust, $uri = '/test')
{
	$page = new Page($pieCrust, $uri);
	//$bench->setMarker('Created page');
	
	$renderer = new PageRenderer($pieCrust);
	//$bench->setMarker('Created renderer');
	
	$page = $renderer->get($page);
	//$bench->setMarker('Rendered page');
	
	return $page;
}

function profiling_tick_function($display = false)
{
    static $times;

    switch ($display)
    {
    case false:
        // add the current time to the list of recorded times
        $times[] = microtime();
        break;
    case true:
        // return elapsed times in microseconds
        $start = array_shift($times);

        $start_mt = explode(' ', $start); 
        $start_total = doubleval($start_mt[0]) + $start_mt[1]; 

        foreach ($times as $stop)
        { 
            $stop_mt = explode(' ', $stop); 
            $stop_total = doubleval($stop_mt[0]) + $stop_mt[1]; 
            $elapsed[] = $stop_total - $start_total; 
        }

        unset($times);
        return $elapsed;
        break;
    }
}


