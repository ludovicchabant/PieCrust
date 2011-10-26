<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Base class for a  PieCrust file-system that provides
 * the list of blog posts in descending date order.
 *
 * It also has a couple helper functions.
 */
abstract class FileSystem
{
    protected $pieCrust;
    
    protected function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public abstract function getPostFiles();
    
    public abstract function getPostPathComponents($filename);
    
    public abstract function getPathFormat();
    
    public function getPath(&$captureGroups)
    {
    	$year = array_key_exists('year', $captureGroups) ? $captureGroups['year'] : '????';
    	$month = array_key_exists('month', $captureGroups) ? $captureGroups['month'] : '??';
    	$day = array_key_exists('day', $captureGroups) ? $captureGroups['day'] : '??';
    	$slug = $captureGroups['slug']; // slug is required as can be *the* identifying part in many cases
    	
    	$path = $this->getPathFormat();
    	$path = str_replace('%year%', $year, $path);
    	$path = str_replace('%month%', $month, $path);
    	$path = str_replace('%day%', $day, $path);
    	$path = str_replace('%slug%', $slug, $path);
    	
    	if (strpos($path, '?') !== false)
    	{
    		$possibilities = glob($path);
    		$countOfPossibilities = count($possibilities);
    		if ($countOfPossibilities !== 1)
    			throw new PieCrustException("Path format " . $path . " yielded no or more than 1 possibility!");
    			
    		$path = $possibilities[0];
    		$components = $this->getPostPathComponents($path);
    		$captureGroups['year'] = $components['year'];
    		$captureGroups['month'] = $components['month'];
    		$captureGroups['day'] = $components['day'];
    	}

    	return $path;
    }
    
    public static function create(PieCrust $pieCrust, $subDir = null)
    {
        if ($subDir == PieCrust::DEFAULT_BLOG_KEY) $subDir = null;
        $postsFs = $pieCrust->getConfigValueUnchecked('site', 'posts_fs');
        switch ($postsFs)
        {
        case 'hierarchy':
            return new HierarchicalFileSystem($pieCrust, $subDir);
        case 'flat':
            return new FlatFileSystem($pieCrust, $subDir);
		case 'year':
			return new YearFileSystem($pieCrust, $subDir);
        default:
            throw new PieCrustException("");
        }
    }
    
    public static function ensureDirectory($dir)
    {
        if (!is_dir($dir))
        {
            if (!mkdir($dir, 0777, true))
                throw new PieCrustException("Can't create directory: ".$dir);
        }
    }
    
    public static function deleteDirectoryContents($dir, $printProgress = false, $skipPattern = '/^(\.)?empty(\.txt)?/i', $level = 0)
    {
        $skippedFiles = false;
        $files = new \FilesystemIterator($dir);
        foreach ($files as $file)
        {
            if ($skipPattern != null and preg_match($skipPattern, $file->getFilename()))
            {
                $skippedFiles = true;
                continue;
            }
            
            if ($file->isDir())
            {
                $skippedFiles |= self::deleteDirectoryContents($file->getPathname(), $printProgress, $skipPattern, $level + 1);
            }
            else
            {
                if ($printProgress) echo '.';
                if (!unlink($file))
                    throw new PieCrustException("Can't unlink file: ".$file);
            }
        }
        
        if ($level > 0 and !$skippedFiles and is_dir($dir))
        {
            if ($printProgress) echo '.';
            if (!rmdir($dir))
                throw new PieCrustException("Can't rmdir directory: ".$dir);
        }
        return $skippedFiles;
    }
    
    public static function getAbsolutePath($path)
    {
        if ($path[0] != '/' && $path[0] != '\\' && $path[1] != ':')
            $path = getcwd() . '/' . $path;
        
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $absolutes = array();
        foreach ($parts as $part)
        {
            if ('.' == $part)
                continue;
            if ('..' == $part)
                array_pop($absolutes);
            else
                $absolutes[] = $part;
        }
        return implode('/', $absolutes);
    }
}
