<?php

namespace PieCrust\IO;

use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a hierarchical PieCrust blog file-system.
 */
class HierarchicalFileSystem extends FileSystem
{
    public function __construct($pagesDir, $postsDir, $htmlExtensions = null)
    {
        FileSystem::__construct($pagesDir, $postsDir, $htmlExtensions);
    }
    
    public function getPostFiles()
    {
        if (!$this->postsDir)
            return array();

        $result = array();
        
        $years = array();
        $yearsIterator = new FilesystemIterator($this->postsDir);
        foreach ($yearsIterator as $year)
        {
            if (!$year->isDir())
                continue;

            if (preg_match('/^\d{4}$/', $year->getFilename()) === false)
                continue;
            
            $thisYear = $year->getFilename();
            $years[] = $thisYear;
        }
        
        foreach ($years as $year)
        {
            $months = array();
            $monthsIterator = new FilesystemIterator($this->postsDir . $year);
            foreach ($monthsIterator as $month)
            {
                if (!$month->isDir())
                    continue;

                if (preg_match('/^\d{2}$/', $month->getFilename()) === false)
                    continue;
                
                $thisMonth = $month->getFilename();
                $months[] = $thisMonth;
            }
            
            foreach ($months as $month)
            {
                $postsIterator = new FilesystemIterator($this->postsDir . $year . '/' . $month);
                foreach ($postsIterator as $path)
                {
                    if ($path->isDir())
                        continue;

                    $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
                    if (!in_array($extension, $this->htmlExtensions))
                        continue;

                    $matches = array();
                    if (preg_match(
                        '/^(\d{2})_(.*)\.'.preg_quote($extension, '/').'$/',
                        $path->getFilename(),
                        $matches) === false)
                        continue;
                    
                    $result[] = array(
                        'year' => $year,
                        'month' => $month,
                        'day' => $matches[1],
                        'name' => $matches[2],
                        'ext' => $extension,
                        'path' => $path->getPathname()
                    );
                }
            }
        }
        
        return $result;
    }
    
    public function getPostPathFormat()
    {
        return '%year%/%month%/%day%_%slug%.%ext%';
    }
}
