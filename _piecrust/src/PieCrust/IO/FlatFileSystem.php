<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a flat PieCrust blog file-system.
 */
class FlatFileSystem extends FileSystem
{
    protected $subDir;
    
    public function __construct(PieCrust $pieCrust, $subDir)
    {
        FileSystem::__construct($pieCrust);
        
        if ($subDir == null) $this->subDir = '';
        else $this->subDir = trim($subDir, '\\/') . '/';
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
            
            $filename = pathinfo($path, PATHINFO_BASENAME);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})_(.*)\.html$/', $filename, $matches) == false)
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
    
    public function getPath($captureGroups)
    {
        $baseDir = $this->pieCrust->getPostsDir();
        $path = $baseDir
            . $this->subDir
            . $captureGroups['year'] . '-'
            . $captureGroups['month'] . '-'
            . $captureGroups['day'] . '_' . $captureGroups['slug'] . '.html';
        return $path;
    }
}
