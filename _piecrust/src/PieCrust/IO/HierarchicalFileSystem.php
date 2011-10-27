<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a hierarchical PieCrust blog file-system.
 */
class HierarchicalFileSystem extends FileSystem
{
    public function __construct(PieCrust $pieCrust, $subDir)
    {
        FileSystem::__construct($pieCrust, $subDir);
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
                    $matches = array();
                    if (preg_match('/^(\d{2})_(.*)\.html$/', $post->getFilename(), $matches) == false)
                        continue;
                    
                    $thisDay = $matches[1];
                    $days[$thisDay] = array('name' => $matches[2], 'path' => $post->getPathname());
                }
                krsort($days);
                
                foreach ($days as $day => $info)
                {
                    $result[] = array(
                        'year' => $year,
                        'month' => $month,
                        'day' => $day,
                        'name' => $info['name'],
                        'path' => $info['path']
                    );
                }
            }
        }
        
        return $result;
    }
    
    public function getPathFormat()
    {
        return '%year%/%month%/%day%_%slug%.html';
    }
}
