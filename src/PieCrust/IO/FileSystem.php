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
    protected $pagesDir;
    protected $postsDir;
    protected $htmlExtensions;
    
    /**
     * Builds a new instance of FileSystem.
     */
    protected function __construct($pagesDir, $postsDir, $htmlExtensions = null)
    {
        $this->pagesDir = $pagesDir;
        $this->postsDir = $postsDir;
        if ($htmlExtensions == null)
            $htmlExtensions = array('html');
        $this->htmlExtensions = $htmlExtensions;
    }

    /**
     * Gets the info about all the page files in the website.
     */
    public function getPageFiles()
    {
        if (!$this->pagesDir)
            return array();

        $pages = array();
        $iterator = new \RecursiveIteratorIterator(
            new PagesRecursiveFilterIterator(
                new \RecursiveDirectoryIterator($this->pagesDir)
            )
        );
        foreach ($iterator as $path)
        {
            $pagePath = $path->getPathname();
            // Skip files in page asset folders.
            if (preg_match('#\-assets[/\\\\]#', $pagePath))
                continue;

            $relativePath = PathHelper::getRelativePath($this->pagesDir, $pagePath);
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
        if (!$this->postsDir)
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
        if (array_key_exists('ext', $captureGroups))
        {
            $ext = $captureGroups['ext'];
        }
        else
        {
            $extCount = count($this->htmlExtensions);
            if ($extCount <= 1)
            {
                $ext = 'html';
            }
            else
            {
                $ext = '*';
                $needsRecapture = true;
            }
        }
        $slug = $captureGroups['slug']; // 'slug' is required.
        
        $path = $this->getPostPathFormat();
        $path = str_replace(
            array('%year%', '%month%', '%day%', '%slug%', '%ext%'),
            array($year, $month, $day, $slug, $ext),
            $path
        );
        $path = $this->postsDir . $path;
        
        $pathInfo = array(
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'slug' => $slug,
            'ext' => $ext,
            'path' => $path
        );
        if ($needsRecapture)
        {
            // Not all path components were specified in the URL (e.g. because the
            // post URL format doesn't capture all of them).
            // We need to find a physical file that matches everything we have,
            // and fill in the blanks.
            $possiblePaths = glob($path, GLOB_NOSORT);
            // TODO: throw different exceptions if we find 0 or more than 1 file.
            if (count($possiblePaths) != 1)
                throw new PieCrustException('404');
            
            $pathInfo['path'] = $possiblePaths[0];
            
            $pathComponentsRegex = preg_quote($this->getPostPathFormat(), '/');
            $pathComponentsRegex = str_replace(
                array('%year%', '%month%', '%day%', '%slug%', '%ext%'),
                array('(\d{4})', '(\d{2})', '(\d{2})', '(.+)', '(\w+)'),
                $pathComponentsRegex
            );
            $pathComponentsRegex = '/' . $pathComponentsRegex . '/';
            $pathComponentsMatches = array();
            if (preg_match(
                $pathComponentsRegex,
                str_replace('\\', '/', $possiblePaths[0]),
                $pathComponentsMatches) !== 1)
                throw new PieCrustException("Can't extract path components from path: " . $possiblePaths[0]);
            
            $pathInfo['year'] = $pathComponentsMatches[1];
            $pathInfo['month'] = $pathComponentsMatches[2];
            $pathInfo['day'] = $pathComponentsMatches[3];
            $pathInfo['slug'] = $pathComponentsMatches[4];
            $pathInfo['ext'] = $pathComponentsMatches[5];
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
    public static function create(IPieCrust $pieCrust, $postsSubDir = null, $themeFs = false)
    {
        $postsFs = $pieCrust->getConfig()->getValueUnchecked('site/posts_fs');
        $autoFormats = $pieCrust->getConfig()->getValueUnchecked('site/auto_formats');
        $htmlExtensions = array_keys($autoFormats);

        if ($themeFs)
        {
            $themeDir = $pieCrust->getThemeDir();
            if (!$themeDir)
                throw new PieCrustException("Can't create a theme file-system because there's no theme in the current website.");
            $pagesDir = $themeDir . PieCrustDefaults::CONTENT_PAGES_DIR;
            $postsDir = $themeDir . PieCrustDefaults::CONTENT_POSTS_DIR;
        }
        else
        {
            $pagesDir = $pieCrust->getPagesDir();
            $postsDir = $pieCrust->getPostsDir();
            if ($postsSubDir == PieCrustDefaults::DEFAULT_BLOG_KEY)
                $postsSubDir = null;
            if ($postsSubDir != null)
                $postsDir .= trim($postsSubDir, '\\/') . '/';
        }

        switch ($postsFs)
        {
        case 'hierarchy':
            return new HierarchicalFileSystem($pagesDir, $postsDir, $htmlExtensions);
        case 'shallow':
            return new ShallowFileSystem($pagesDir, $postsDir, $htmlExtensions);
        case 'flat':
            return new FlatFileSystem($pagesDir, $postsDir, $htmlExtensions);
        default:
            throw new PieCrustException("Unknown posts_fs: " . $postsFs);
        }
    }
}
