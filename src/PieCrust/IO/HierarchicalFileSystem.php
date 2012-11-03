<?php

namespace PieCrust\IO;

use DirectoryIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a hierarchical PieCrust blog file-system.
 */
class HierarchicalFileSystem extends FileSystem
{
    public function __construct($pagesDir, $postsDir)
    {
        FileSystem::__construct($pagesDir, $postsDir);
    }
    
    public function getPostFiles()
    {
        if (!$this->postsDir)
            return array();

        $result = array();
        
        $years = array();
        $yearsIterator = new DirectoryIterator($this->postsDir);
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
            $monthsIterator = new DirectoryIterator($this->postsDir . $year);
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
                $postsIterator = new DirectoryIterator($this->postsDir . $year . '/' . $month);
                foreach ($postsIterator as $post)
                {
                    if ($post->isDot() or $post->isDir())
                        continue;

                    $matches = array();
                    if (preg_match('/^(\d{2})_(.*)\.html$/', $post->getFilename(), $matches) == false)
                        continue;
                    
                    $thisDay = $matches[1];
                    $days[$post->getPathname()] = array(
                        'day' => $thisDay, 
                        'name' => $matches[2], 
                        'path' => $post->getPathname()
                    );
                }
                krsort($days);
                
                foreach ($days as $day)
                {
                    $result[] = array(
                        'year' => $year,
                        'month' => $month,
                        'day' => $day['day'],
                        'name' => $day['name'],
                        'path' => $day['path']
                    );
                }
            }
        }
        
        return $result;
    }
    
    public function getPostPathFormat()
    {
        return '%year%/%month%/%day%_%slug%.html';
    }
}
