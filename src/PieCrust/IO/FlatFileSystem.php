<?php

namespace PieCrust\IO;

use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a flat PieCrust blog file-system.
 */
class FlatFileSystem extends FileSystem
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
        $pathsIterator = new FilesystemIterator($this->postsDir);
        foreach ($pathsIterator as $path)
        {
            if ($path->isDir())
                continue;

            $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
            if (!in_array($extension, $this->htmlExtensions))
                continue;
        
            $matches = array();
            if (preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})_(.*)\.'.preg_quote($extension, '/').'$/', 
                $path->getFilename(), 
                $matches) === false)
                continue;
            
            $result[] = array(
                'year' => $matches[1],
                'month' => $matches[2],
                'day' => $matches[3],
                'name' => $matches[4],
                'ext' => $extension,
                'path' => $path->getPathname()
            );
        }
        return $result;
    }
    
    public function getPostPathFormat()
    {
        return '%year%-%month%-%day%_%slug%.%ext%';
    }
}
