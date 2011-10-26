<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a hierarchical PieCrust blog file-system.
 */
class HierarchicalFileSystem extends FileSystem
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
            $months = array();
            $monthsIterator = new \DirectoryIterator($this->pieCrust->getPostsDir() . $this->subDir . $year);
            foreach ($monthsIterator as $month)
            {
                if (preg_match('/^\d{2}$/', $month->getFilename()) == false)
                    continue;
                
                $thisMonth = $month->getFilename();
                $months[] = $thisMonth;
            }
            rsort($months);
                
            foreach ($months as $month)
            {
                $days = array();
                $postsIterator = new \DirectoryIterator($this->pieCrust->getPostsDir() . $this->subDir . $year . '/' . $month);
                foreach ($postsIterator as $post)
                {
                	$path = $post->getPathname();
                	$matches = $this->getPostPathComponents($path);
                	if ($matches == false) continue;
                	$thisDay = $matches['day'];
                	$days[$thisDay] = $matches;
                }
                krsort($days);
                
                foreach ($days as $day => $info)
                {
                	$result[] = $info;
                }
            }
        }
        
        return $result;
    }

	public function getPostPathComponents($path) {
		$matches = array();
		
		if (preg_match('/(\d{4})\/(\d{2})\/(\d{2})_(.*)\.html$/', $path, $matches) == false)
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
		$format = $baseDir . $this->subDir . '%year%/%month%/%day%_%slug%.html';
		return $format;
	}
}
