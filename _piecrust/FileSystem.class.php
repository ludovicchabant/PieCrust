<?php

require_once 'FlatFileSystem.class.php';
require_once 'HierarchicalFileSystem.class.php';


/**
 * Base class for a  PieCrust file-system that provides
 * the list of blog posts in descending date order.
 *
 * It also has a couple helper functions.
 */
abstract class FileSystem
{
    protected $pieCrust;
    
    protected function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public abstract function getPostFiles();
    
    public abstract function getPath($captureGroups);
    
    public static function create(PieCrust $pieCrust, $subDir = null)
    {
        if ($subDir == PIECRUST_DEFAULT_BLOG_KEY) $subDir = null;
        $postsFs = $pieCrust->getConfigValueUnchecked('site', 'posts_fs');
        switch ($postsFs)
        {
        case 'hierarchy':
            return new HierarchicalFileSystem($pieCrust, $subDir);
        case 'flat':
            return new FlatFileSystem($pieCrust, $subDir);
        default:
            throw new PieCrustException("");
        }
    }
    
    public static function ensureDirectory($dir)
    {
        if (!is_dir($dir))
        {
            return mkdir($dir, 0777, true);
        }
        return true;
    }
    
    public static function deleteDirectoryContents($dir, $printProgress = false, $skipPattern = '/^(\.)?empty(\.txt)?/i', $level = 0)
    {
        $skippedFiles = false;
        $files = new FilesystemIterator($dir);
        foreach ($files as $file)
        {
            if ($skipPattern != null and preg_match($skipPattern, $file->getFilename()))
            {
                $skippedFiles = true;
                continue;
            }
            
            if($file->isDir())
            {
                $skippedFiles |= self::deleteDirectoryContents($file->getPathname(), $printProgress, $skipPattern, $level + 1);
            }
            else
            {
                if ($printProgress) echo '.';
                unlink($file);
            }
        }
        
        if ($level > 0 and !$skippedFiles and is_dir($dir))
        {
            if ($printProgress) echo '.';
            rmdir($dir);
        }
        return $skippedFiles;
    }
}
