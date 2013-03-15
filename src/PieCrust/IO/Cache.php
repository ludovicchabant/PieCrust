<?php

namespace PieCrust\IO;

use PieCrust\PieCrustDefaults;


/**
 * A simple generic file-system cache class.
 *
 */
class Cache
{
    protected $baseDir;
    protected $commentTags;
    
    public function __construct($baseDir, $writeCommentTags = false)
    {
        if (!is_dir($baseDir))
        {
            mkdir($baseDir, 0777, true);
        }
        
        $this->baseDir = rtrim($baseDir, '/\\') . '/';

        if ($writeCommentTags)
        {
            $this->commentTags = array(
                    'html' => array('<!-- ', ' -->'),
                    'yml' => array('# ', ''),
                    'json' => null
                );
        }
        else
        {
            $this->commentTags = null;
        }
    }
    
    public function isValid($uri, $extension, $time)
    {
        $cacheTime = $this->getCacheTime($uri, $extension);
        if ($cacheTime == false)
            return false;

        if (is_array($time))
        {
            foreach ($time as $t)
            {
                if ($cacheTime < $time)
                    return false;
            }
            return true;
        }
        else
        {
            return $cacheTime >= $time;
        }
    }
    
    public function getCacheTime($uri, $extension)
    {
        $cachePath = $this->getCachePath($uri, $extension);
        if (!file_exists($cachePath))
            return false;
        return filemtime($cachePath);
    }

    public function has($uri, $extension)
    {
        $cachePath = $this->getCachePath($uri, $extension);
        return is_file($cachePath);
    }
    
    public function read($uri, $extension)
    {
        $cachePath = $this->getCachePath($uri, $extension);
        return file_get_contents($cachePath);
    }
    
    public function write($uri, $extension, $contents)
    {
        $cachePath = $this->getCachePath($uri, $extension);
        if (!is_dir(dirname($cachePath)))
        {
            mkdir(dirname($cachePath), 0777, true);
        }
        
        if ($this->commentTags)
        {
            $commentTags = $this->commentTags[$extension];
            if ($commentTags != null)
                $header = $commentTags[0] . 'PieCrust ' . PieCrustDefaults::VERSION . ' - cached ' . date('Y-m-d H:i:s:u') . $commentTags[1] . "\n";
            else
                $header = '';
            $contents = $header . $contents;
        }

        file_put_contents($cachePath, $contents);
    }
    
    protected function getCachePath($uri, $extension)
    {
        return $this->baseDir . $uri . ($extension == null ? '' : ('.' . $extension));
    }
}

