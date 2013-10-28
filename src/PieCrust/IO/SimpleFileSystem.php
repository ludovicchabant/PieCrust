<?php

namespace PieCrust\IO;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;


/**
 * Base class for simple file systems that have pages in one folder
 * and posts in another folder.
 */
abstract class SimpleFileSystem extends FileSystem
{
    protected $pagesDir;
    protected $postsDir;
    
    public function initialize(IPieCrust $pieCrust)
    {
        parent::initialize($pieCrust);

        $this->pagesDir = $pieCrust->getPagesDir();
        $this->postsDir = $pieCrust->getPostsDir();
    }

    public function initializeForTheme(IPieCrust $pieCrust)
    {
        parent::initialize($pieCrust);

        $themeDir = $pieCrust->getThemeDir();
        if (!$themeDir)
            throw new PieCrustException("The given website doesn't have a theme.");
        $this->pagesDir = $themeDir . PieCrustDefaults::CONTENT_PAGES_DIR;
        $this->postsDir = $themeDir . PieCrustDefaults::CONTENT_POSTS_DIR;
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

            $pages[] = new PageInfo($this->pagesDir, $pagePath);
        }

        return $pages;
    }

    public function getPostPathFormat($blogKey)
    {
        return $this->getPostsDir($blogKey) . $this->getPostFilenameFormat();
    }

    protected function getPostsDir($blogKey)
    {
        if ($blogKey == PieCrustDefaults::DEFAULT_BLOG_KEY or $this->postsDir == null)
        {
            return $this->postsDir;
        }
        return $this->postsDir . $blogKey . '/';
    }

    protected abstract function getPostFilenameFormat();
}

