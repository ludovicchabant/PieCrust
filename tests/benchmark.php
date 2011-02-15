<?php

// This requires PEAR Benchmark package.
require_once 'Benchmark/Timer.php';
require_once 'Benchmark/Iterate.php';

// Include the PieCrust app but with a root directory set
// to the test website's root dir.
define('PIECRUST_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once '../website/_piecrust/PieCrust.class.php';



// Utility methods.
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

function average($values)
{
	if (!is_array($values))
		return false;
		
	if (count($values) > 1 )
	{
		return (array_sum($values) / count($values));
	}
	else
	{
		return current($values);
	}
}

function median($values)
{
	if (!is_array($values))
		return false;
		
	sort($values);
	$count = count($values);
	$middle = $count / 2;
	if ($count % 2 == 0)
	{
		return ($values[$middle] + $values[$middle-1])/2;
	}
	else
	{
		return $values[$middle];
	}
}

define('BENCHMARKS_CACHE_DIR', PIECRUST_ROOT_DIR . '_cache');

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

function run_query($pieCrust, $uri = '/test', $bench = null)
{
	$page = new Page($pieCrust, $uri);
	if ($bench != null)
		$bench->setMarker('Created page');
	
	$renderer = new PageRenderer($pieCrust);
	if ($bench != null)
		$bench->setMarker('Created renderer');
	
	$page = $renderer->get($page);
	if ($bench != null)
		$bench->setMarker('Rendered page');
	
	return $page;
}
