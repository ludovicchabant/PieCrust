<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a year PieCrust blog file-system - single depth where dir must be year and filename mm-dd_slug.
 */
class YearFileSystem extends FileSystem
{
    protected $subDir;
    
    public function __construct(PieCrust $pieCrust, $subDir)
    {
        FileSystem::__construct($pieCrust);
        
        if ($subDir == null) $this->subDir = '';
        else $this->subDir = trim($subDir, '\\/') . '/';
    }
    
    public function getPostFiles()
    {
        $result = array();
        
        $years = array();
        $yearsIterator = new \DirectoryIterator($this->pieCrust->getPostsDir() . $this->subDir);
        foreach ($yearsIterator as $year)
        {
            if (preg_match('/^\d{4}$/', $year->getFilename()) == false)
                continue;
            
            $thisYear = $year->getFilename();
            $years[] = $thisYear;
        }
        rsort($years);
        
        foreach ($years as $year)
        {
            $posts = array();
			$pathPattern = $this->pieCrust->getPostsDir() . $this->subDir . '/' . $year . '/' . '*.html';
			$paths = glob($pathPattern, GLOB_ERR);
			if ($paths === false)
			{
			    throw new PieCrustException('An error occured while reading the posts directory.');
			}
			rsort($paths);

            foreach ($paths as $path)
            {
                $matches = $this->getPostPathComponents($path);
                if ($matches == false) continue;
				$result[] = $matches;
            }
        }

        return $result;
    }
    
    public function getPostPathComponents($path) {
    	$matches = array();
    	
    	if (preg_match('/(\d{4})\/(\d{2})-(\d{2})_(.*)\.html$/', $path, $matches) == false)
    	    return false;
    	
    	$result = array(
    		'year' => $matches[1],
    	    'month' => $matches[2],
    	    'day' => $matches[3],
    	    'name' => $matches[4],
    	    'path' => $path
    	);
    	
    	return $result;
    }
    
    public function getPathFormat()
    {
    	$baseDir = $this->pieCrust->getPostsDir();
    	$format = $baseDir . $this->subDir . '%year%/%month%-%day%_%slug%.html';
    	return $format;
    }
}
