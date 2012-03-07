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
    public function __construct(IPieCrust $pieCrust, $postsSubDir)
    {
        FileSystem::__construct($pieCrust, $postsSubDir);
    }
    
    public function getPostFiles()
    {
        if (!$this->pieCrust->getPostsDir())
            return array();

        $paths = array();
        $pathsIterator = new FilesystemIterator($this->pieCrust->getPostsDir() . $this->postsSubDir);
        foreach ($pathsIterator as $p)
        {
            if ($p->getExtension() != 'html')
                continue;
            $paths[] = $p->getPathname();
        }
        rsort($paths);
        
        $result = array();
        foreach ($paths as $path)
        {
            $matches = array();
            
            if (preg_match('/(\d{4})-(\d{2})-(\d{2})_(.*)\.html$/', $path, $matches) == false)
                continue;
            
            $result[] = array(
                'year' => $matches[1],
                'month' => $matches[2],
                'day' => $matches[3],
                'name' => $matches[4],
                'path' => $path
            );
        }
        return $result;
    }
    
    public function getPostPathFormat()
    {
        return '%year%-%month%-%day%_%slug%.html';
    }
}
