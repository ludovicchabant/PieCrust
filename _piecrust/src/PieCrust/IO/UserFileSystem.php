<?php

namespace PieCrust\IO;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * Describes a user defined PieCrust blog file-system - user can have any hierarchy she wants but requires file names structured the same as FlatFileSystem.
 */
class UserFileSystem extends FileSystem
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
        $result = array();
        
        $dirsIterator = new \RecursiveDirectoryIterator($this->pieCrust->getPostsDir() . $this->subDir);
        $iterator = new \RecursiveIteratorIterator($dirsIterator);
        foreach ($iterator as $path) {
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

		$result = $this->multiSort($result, 'year', 'month', 'day');        
        return $result;
    }
    
    public function getPath($captureGroups)
    {
        $baseDir = $this->pieCrust->getPostsDir();
        $path = $baseDir
            . $this->subDir
            . $captureGroups['year'] . '/'
            . $captureGroups['month'] . '/'
            . $captureGroups['day'] . '_' . $captureGroups['slug'] . '.html';
        return $path;
    }
    
    // copied from http://si.php.net/manual/en/function.usort.php, but reversing comparison!
    public function multiSort()
    { 
        //get args of the function 
        $args = func_get_args(); 
        $c = count($args); 
        if ($c < 2) 
        { 
            return false; 
        } 
        
        //get the array to sort 
        $array = array_splice($args, 0, 1); 
        $array = $array[0]; 
        //sort with an anoymous function using args 
        usort($array, function($a, $b) use($args) 
        {
            $i = 0; 
            $c = count($args); 
            $cmp = 0; 
            while($cmp == 0 && $i < $c) 
            { 
                $cmp = strnatcmp($a[$args[$i]], $b[$args[$i]]); 
                $i++; 
            }
            return -$cmp;    
        }); 
    
        return $array;     
    } 
}
