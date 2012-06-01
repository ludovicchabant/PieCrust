<?php

namespace PieCrust\IO;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;


/**
 * Base class for a  PieCrust file-system that provides
 * the list of blog posts in descending date order, and
 * the list of pages.
 *
 * It also has a couple helper functions.
 */
abstract class FileSystem
{
    protected $pieCrust;
    protected $postsSubDir;
    
    /**
     * Builds a new instance of FileSystem.
     */
    protected function __construct(IPieCrust $pieCrust, $postsSubDir)
    {
        $this->pieCrust = $pieCrust;
        
        if ($postsSubDir == null)
            $this->postsSubDir = '';
        else 
            $this->postsSubDir = trim($postsSubDir, '\\/') . '/';
    }

    /**
     * Gets the info about all the page files in the website.
     */
    public function getPageFiles()
    {
        $pagesDir = $this->pieCrust->getPagesDir();
        if (!$pagesDir)
            return array();

        $pages = array();
        $directory = new \RecursiveDirectoryIterator($pagesDir);
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $path)
        {
            if ($iterator->isDot())
                continue;

            $pagePath = $path->getPathname();
            $relativePath = PathHelper::getRelativePagePath($this->pieCrust, $pagePath, IPage::TYPE_REGULAR);
            $relativePathInfo = pathinfo($relativePath);
            if ($relativePathInfo['filename'] == PieCrustDefaults::CATEGORY_PAGE_NAME or
                $relativePathInfo['filename'] == PieCrustDefaults::TAG_PAGE_NAME)
            {
                continue;
            }

            $pages[] = array(
                'path' => $pagePath, 
                'relative_path' => $relativePath
            );
        }

        return $pages;
    }
    
    /**
     * Gets the info about all the post files in the website.
     *
     * File infos are expected to be sorted in reverse chronological
     * order based on the day of the post.
     */
    public abstract function getPostFiles();
    
    /**
     * Gets the complete info for a post file based on an incomplete
     * one (e.g. when the URL to a post doesn't contain all the
     * information to locate it on disk).
     */
    public function getPostPathInfo($captureGroups)
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
        
        $path = $this->getPostPathFormat();
        $path = str_replace(
            array('%year%', '%month%', '%day%', '%slug%'),
            array($year, $month, $day, $slug),
            $path
        );
        $path = $postsDir . $this->postsSubDir . $path;
        
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
            
            $pathComponentsRegex = preg_quote($this->getPostPathFormat(), '/');
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
    
    /**
     * Gets the posts path format.
     */
    public abstract function getPostPathFormat();
    
    /**
     * Creates the appropriate implementation of `FileSystem` based
     * on the configuration of the website.
     */
    public static function create(IPieCrust $pieCrust, $postsSubDir = null)
    {
        if ($postsSubDir == PieCrustDefaults::DEFAULT_BLOG_KEY)
            $postsSubDir = null;
        $postsFs = $pieCrust->getConfig()->getValueUnchecked('site/posts_fs');
        switch ($postsFs)
        {
        case 'hierarchy':
            return new HierarchicalFileSystem($pieCrust, $postsSubDir);
        case 'shallow':
            return new ShallowFileSystem($pieCrust, $postsSubDir);
        case 'flat':
            return new FlatFileSystem($pieCrust, $postsSubDir);
        default:
            throw new PieCrustException("Unknown posts_fs: " . $postsFs);
        }
    }
}
