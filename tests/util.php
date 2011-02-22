<?php

error_reporting(E_ALL ^ E_NOTICE);

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

function ensure_cache($cacheDir, $ensureClean = true)
{
	if ($cacheDir == null or $cacheDir == '')
		throw new Exception('Need a valid cache directory.');
		
	if ($ensureClean and is_dir($cacheDir))
	{
		rmdir_recursive($cacheDir);
	}
	if (!is_dir($cacheDir))
	{
		mkdir($cacheDir);
	}
}
