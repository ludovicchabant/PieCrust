<?php

namespace PieCrust\IO;

use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a year PieCrust blog file-system - single depth where dir must be year and filename mm-dd_slug.
 */
class ShallowFileSystem extends FileSystem
{
    public function __construct($pagesDir, $postsDir, $htmlExtensions = null)
    {
        FileSystem::__construct($pagesDir, $postsDir, $htmlExtensions);
    }
    
    public function getPostFiles()
    {
        if (!$this->postsDir)
            return array();

        $years = array();
        $yearsIterator = new FilesystemIterator($this->postsDir);
        foreach ($yearsIterator as $year)
        {
            if (!$year->isDir())
                continue;

            if (preg_match('/^\d{4}$/', $year->getFilename()) == false)
                continue;
            
            $thisYear = $year->getFilename();
            $years[] = $thisYear;
        }
        
        $result = array();
        foreach ($years as $year)
        {
            $postsIterator = new FilesystemIterator($this->postsDir . $year);
            foreach ($postsIterator as $path)
            {
                if ($path->isDir())
                    continue;

                $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
                if (!in_array($extension, $this->htmlExtensions))
                    continue;
        
                $matches = array();
                if (preg_match(
                    '/^(\d{2})-(\d{2})_(.*)\.'.preg_quote($extension, '/').'$/',
                    $path->getFilename(),
                    $matches) === false)
                    continue;
                $result[] = array(
                    'year' => $year,
                    'month' => $matches[1],
                    'day' => $matches[2],
                    'name' => $matches[3],
                    'ext' => $extension,
                    'path' => $path->getPathname()
                );
            }
        }
        return $result;
    }
    
    public function getPostPathFormat()
    {
        return '%year%/%month%-%day%_%slug%.%ext%';
    }
}
