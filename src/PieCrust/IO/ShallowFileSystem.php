<?php

namespace PieCrust\IO;

use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a year PieCrust blog file-system - single depth where dir must be year and filename mm-dd_slug.
 */
class ShallowFileSystem extends SimpleFileSystem
{
    public function getName()
    {
        return 'shallow';
    }

    public function getPostFiles($blogKey)
    {
        $postsDir = $this->getPostsDir($blogKey);
        if (!$postsDir)
            return array();

        $years = array();
        $yearsIterator = new FilesystemIterator($postsDir);
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
            $postsIterator = new FilesystemIterator($postsDir . $year);
            foreach ($postsIterator as $path)
            {
                if ($path->isDir())
                    continue;

                $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
                if (!in_array($extension, $this->htmlExtensions))
                    continue;
        
                $matches = array();
                if (!preg_match(
                    '/^(\d{2})-(\d{2})_(.*)\.'.preg_quote($extension, '/').'$/',
                    $path->getFilename(),
                    $matches))
                {
                    $this->pieCrust->getEnvironment()->getLog()->warning(
                        "File '{$path->getPathname()}' is not formatted as 'MM-DD_slug-title.{$extension}' and is ignored. Is that a typo?"
                    );
                    continue;
                }
                $result[] = PostInfo::fromStrings(
                    $year,
                    $matches[1],
                    $matches[2],
                    $matches[3],
                    $extension,
                    $path->getPathname()
                );
            }
        }
        return $result;
    }
    
    public function getPostFilenameFormat()
    {
        return '%year%/%month%-%day%_%slug%.%ext%';
    }
}
