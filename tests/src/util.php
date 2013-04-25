<?php

use PieCrust\Util\PathHelper;

/**
 * Useful paths.
 */
define('PIECRUST_UNITTESTS_DATA_DIR', dirname(__DIR__) . '/data/');
define('PIECRUST_UNITTESTS_MOCK_DIR', dirname(__DIR__) . '/tmp/mock/');
define('PIECRUST_BENCHMARKS_ROOT_DIR', dirname(__DIR__) . '/data/benchmark/');
define('PIECRUST_BENCHMARKS_CACHE_DIR', dirname(__DIR__) . '/tmp/cache/');
 

/**
 * Useful functions.
 */
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
        PathHelper::deleteDirectoryContents($cacheDir);
    }
    PathHelper::ensureDirectory($cacheDir);
}

