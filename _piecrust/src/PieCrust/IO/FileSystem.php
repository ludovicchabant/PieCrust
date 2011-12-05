<?php

namespace PieCrust\IO;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * Base class for a  PieCrust file-system that provides
 * the list of blog posts in descending date order.
 *
 * It also has a couple helper functions.
 */
abstract class FileSystem
{
    protected $pieCrust;
    protected $subDir;
    
    protected function __construct(IPieCrust $pieCrust, $subDir)
    {
        $this->pieCrust = $pieCrust;
        
        if ($subDir == null) $this->subDir = '';
        else $this->subDir = trim($subDir, '\\/') . '/';
    }
    
    public abstract function getPostFiles();
    
    public function getPathInfo($captureGroups)
    {
        $postsDir = $this->pieCrust->getPostsDir();
        if (!$postsDir)
            throw new PieCrustException("Can't get the path info for a captured post URL when no post directory exists in the website.");

        $needsRecapture = false;
        if (array_key_exists('year', $captureGroups))
        {
            $year = $captureGroups['year'];
        }
        else
        {
            $year = '????';
            $needsRecapture = true;
        }
        if (array_key_exists('month', $captureGroups))
        {
            $month = $captureGroups['month'];
        }
        else
        {
            $month = '??';
            $needsRecapture = true;
        }
        if (array_key_exists('day', $captureGroups))
        {
            $day = $captureGroups['day'];
        }
        else
        {
            $day = '??';
            $needsRecapture = true;
        }
        $slug = $captureGroups['slug']; // 'slug' is required.
        
        $path = $this->getPathFormat();
        $path = str_replace(
            array('%year%', '%month%', '%day%', '%slug%'),
            array($year, $month, $day, $slug),
            $path
        );
        $path = $postsDir . $this->subDir . $path;
        
        $pathInfo = array(
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'slug' => $slug,
            'path' => $path
        );
        if ($needsRecapture)
        {
            // Not all path components were specified in the URL (e.g. because the
            // post URL format doesn't capture all of them).
            // We need to find a physical file that matches everything we have,
            // and fill in the blanks.
            $possiblePaths = glob($path);
            if (count($possiblePaths) != 1)
                throw new PieCrustException('404');
            
            $pathInfo['path'] = $possiblePaths[0];
            
            $pathComponentsRegex = preg_quote($this->getPathFormat(), '/');
            $pathComponentsRegex = str_replace(
                array('%year%', '%month%', '%day%', '%slug%'),
                array('(\d{4})', '(\d{2})', '(\d{2})', '(.+)'),
                $pathComponentsRegex
            );
            $pathComponentsRegex = '/' . $pathComponentsRegex . '/';
            $pathComponentsMatches = array();
            if (preg_match($pathComponentsRegex, str_replace('\\', '/', $possiblePaths[0]), $pathComponentsMatches) !== 1)
                throw new PieCrustException("Can't extract path components from path: " . $possiblePaths[0]);
            
            $pathInfo['year'] = $pathComponentsMatches[1];
            $pathInfo['month'] = $pathComponentsMatches[2];
            $pathInfo['day'] = $pathComponentsMatches[3];
            $pathInfo['slug'] = $pathComponentsMatches[4];
        }
        return $pathInfo;
    }
    
    public abstract function getPathFormat();
    
    public static function create(IPieCrust $pieCrust, $subDir = null)
    {
        if ($subDir == PieCrustDefaults::DEFAULT_BLOG_KEY) $subDir = null;
        $postsFs = $pieCrust->getConfig()->getValueUnchecked('site/posts_fs');
        switch ($postsFs)
        {
        case 'hierarchy':
            return new HierarchicalFileSystem($pieCrust, $subDir);
        case 'shallow':
            return new ShallowFileSystem($pieCrust, $subDir);
        case 'flat':
            return new FlatFileSystem($pieCrust, $subDir);
        default:
            throw new PieCrustException("Unknown posts_fs: " . $postsFs);
        }
    }
    
    public static function ensureDirectory($dir, $writable = false)
    {
        if (!is_dir($dir))
        {
            if (!mkdir($dir, 0777, true))
                throw new PieCrustException("Can't create directory: " . $dir);

            if ($writable && !is_writable($dir))
                if (!chmod($dir, 0777))
                    throw new PieCrustException("Can't make directory '" . $dir . "' writable.");

            return true;
        }
        return false;
    }

    public static function deleteDirectoryContents($dir, $printProgress = false, $skipPattern = '/^(\.)?empty(\.txt)?/i', $level = 0)
    {
        $skippedFiles = false;
        $files = new \FilesystemIterator($dir);
        foreach ($files as $file)
        {
            if ($skipPattern != null and preg_match($skipPattern, $file->getFilename()))
            {
                $skippedFiles = true;
                continue;
            }
            
            if ($file->isDir())
            {
                $skippedFiles |= self::deleteDirectoryContents($file->getPathname(), $printProgress, $skipPattern, $level + 1);
            }
            else
            {
                if ($printProgress) echo '.';
                if (!unlink($file))
                    throw new PieCrustException("Can't unlink file: ".$file);
            }
        }
        
        if ($level > 0 and !$skippedFiles and is_dir($dir))
        {
            if ($printProgress) echo '.';
            if (!rmdir($dir))
                throw new PieCrustException("Can't rmdir directory: ".$dir);
        }
        return $skippedFiles;
    }
    
    public static function getAbsolutePath($path)
    {
        if ($path[0] != '/' && $path[0] != '\\' && (count($path) < 2 || $path[1] != ':'))
            $path = getcwd() . '/' . $path;
        
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $absolutes = array();
        foreach ($parts as $part)
        {
            if ('.' == $part)
                continue;
            if ('..' == $part)
                array_pop($absolutes);
            else
                $absolutes[] = $part;
        }
        return implode('/', $absolutes);
    }
}
