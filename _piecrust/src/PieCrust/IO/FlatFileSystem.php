<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a flat PieCrust blog file-system.
 */
class FlatFileSystem extends FileSystem
{
    public function __construct(PieCrust $pieCrust, $subDir)
    {
        FileSystem::__construct($pieCrust, $subDir);
    }
    
    public function getPostFiles()
    {
        $pathPattern = $this->pieCrust->getPostsDir() . $this->subDir . '*.html';
        $paths = glob($pathPattern, GLOB_ERR);
        if ($paths === false)
        {
            throw new PieCrustException('An error occured while reading the posts directory.');
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
    
    public function getPathFormat()
    {
        return '%year%-%month%-%day%_%slug%.html';
    }
}
