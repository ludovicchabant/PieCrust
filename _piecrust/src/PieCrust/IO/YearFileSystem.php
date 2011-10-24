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
                $matches = array();
				$filename = pathinfo($path, PATHINFO_BASENAME);
				if (preg_match('/^(\d{2})-(\d{2})_(.*)\.html$/', $filename, $matches) == false)
				    continue;
                
				$result[] = array(
				    'year' => $year,
				    'month' => $matches[1],
				    'day' => $matches[2],
				    'name' => $matches[3],
				    'path' => $path
				);
            }
        }

        return $result;
    }
    
    public function getPath($captureGroups)
    {
        $baseDir = $this->pieCrust->getPostsDir();
        $path = $baseDir
        	. $this->subDir
            . $captureGroups['year'] . '/'
            . $captureGroups['month'] . '-'
            . $captureGroups['day'] . '_' . $captureGroups['slug'] . '.html';
        return $path;
    }
}
