<?php

namespace PieCrust\IO;

use \FilesystemIterator;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a flat PieCrust blog file-system.
 */
class FlatFileSystem extends SimpleFileSystem
{
    public function getName()
    {
        return 'flat';
    }

    public function getPostFiles($blogKey)
    {
        $postsDir = $this->getPostsDir($blogKey);
        if (!$postsDir)
            return array();

        $result = array();
        $pathsIterator = new FilesystemIterator($postsDir);
        foreach ($pathsIterator as $path)
        {
            if ($path->isDir())
                continue;

            $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
            if (!in_array($extension, $this->htmlExtensions))
                continue;
        
            $matches = array();
            $res = preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})_(.*)\.'.preg_quote($extension, '/').'$/', 
                $path->getFilename(), 
                $matches
            );
            if (!$res)
            {
                $this->pieCrust->getEnvironment()->getLog()->warning(
                    "File '{$path->getPathname()}' is not formatted as 'YYYY-MM-DD_slug-title.{$extension}' and is ignored. Is that a typo?"
                );
                continue;
            }
            
            $result[] = PostInfo::fromStrings(
                $matches[1],
                $matches[2],
                $matches[3],
                $matches[4],
                $extension,
                $path->getPathname()
            );
        }
        return $result;
    }
    
    public function getPostFilenameFormat()
    {
        return '%year%-%month%-%day%_%slug%.%ext%';
    }
}
