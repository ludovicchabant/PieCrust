<?php

// Setup the environment.
error_reporting(E_ALL ^ E_NOTICE);

set_include_path(get_include_path() . PATH_SEPARATOR . 
				 (dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'website' . DIRECTORY_SEPARATOR . '_piecrust'));


// Utility methods.
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

require_once 'FileSystem.class.php';

function ensure_cache($cacheDir, $ensureClean = true)
{
	if ($cacheDir == null or $cacheDir == '')
		throw new Exception('Need a valid cache directory.');
		
	if ($ensureClean and is_dir($cacheDir))
	{
		FileSystem::deleteDirectory($cacheDir);
	}
	FileSystem::ensureDirectory($cacheDir);
}
