<?php

namespace PieCrust\IO;

use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a hierarchical PieCrust blog file-system.
 */
class HierarchicalFileSystem extends SimpleFileSystem
{
    public function getName()
    {
        return 'hierarchy';
    }

    public function getPostFiles($blogKey)
    {
        $postsDir = $this->getPostsDir($blogKey);
        if (!$postsDir)
            return array();

        $result = array();
        
        $years = array();
        $yearsIterator = new FilesystemIterator($postsDir);
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
            $monthsIterator = new FilesystemIterator($postsDir . $year);
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
                $postsIterator = new FilesystemIterator($postsDir . $year . '/' . $month);
                foreach ($postsIterator as $path)
                {
                    if ($path->isDir())
                        continue;

                    $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
                    if (!in_array($extension, $this->htmlExtensions))
                        continue;

                    $matches = array();
                    if (!preg_match(
                        '/^(\d{2})_(.*)\.'.preg_quote($extension, '/').'$/',
                        $path->getFilename(),
                        $matches))
                    {
                        $this->pieCrust->getEnvironment()->getLog()->warning(
                            "File '{$path->getPathname()}' is not formatted as 'DD_slug-title.{$extension}' and is ignored. Is that a typo?"
                        );
                        continue;
                    }
                    $result[] = PostInfo::fromStrings(
                        $year,
                        $month,
                        $matches[1],
                        $matches[2],
                        $extension,
                        $path->getPathname()
                    );
                }
            }
        }
        
        return $result;
    }
    
    public function getPostFilenameFormat()
    {
        return '%year%/%month%/%day%_%slug%.%ext%';
    }
}
