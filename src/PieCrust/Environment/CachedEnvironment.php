<?php

namespace PieCrust\Environment;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\IO\FlatFileSystem;
use PieCrust\Util\PageHelper;
use PieCrust\Util\UriBuilder;


/**
 * An environment that caches all the information it can.
 */
class CachedEnvironment extends Environment
{
    protected $pageRepository;
    /**
     * Gets the environment's page repository.
     */
    public function getPageRepository()
    {
        if ($this->pageRepository == null)
        {
            $this->pageRepository = new PageRepository($this->pieCrust);
        }
        return $this->pageRepository;
    }

    protected $linkCollector;
    /**
     * Gets the environment's link collector.
     */
    public function getLinkCollector()
    {
        if ($this->linkCollector == null)
        {
            $this->linkCollector = new LinkCollector($this->pieCrust);
        }
        return $this->linkCollector;
    }

    protected $pages;
    /**
     * Gets the pages.
     */
    public function getPages()
    {
        $this->ensurePagesCached();
        return $this->pages;
    }

    protected $posts;
    /**
     * Gets the posts.
     */
    public function getPosts($blogKey)
    {
        $this->ensurePostsCached($blogKey);
        return $this->posts[$blogKey];
    }

    /**
     * Creates a new instance of `CachedEnvironment`.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function ensurePagesCached()
    {
        if ($this->pages == null)
        {
            $this->getLog()->debug("Indexing pages...");

            // Start with the theme pages, if any.
            $pageInfos = array();
            $themeDir = $this->pieCrust->getThemeDir();
            if ($themeDir)
            {
                $fs = new FlatFileSystem();
                $fs->initializeForTheme($this->pieCrust);
                $themePageInfos = $fs->getPageFiles();
                foreach ($themePageInfos as $pageInfo)
                {
                    $pageInfos[$pageInfo->relativePath] = $pageInfo;
                }
            }

            // Override with the user pages.
            $fs = $this->getFileSystem();
            $userPageInfos = $fs->getPageFiles();
            foreach ($userPageInfos as $userPageInfo)
            {
                $pageInfos[$userPageInfo->relativePath] = $userPageInfo;
            }

            $this->getLog()->debug("Creating pages...");
            $pageRepository = $this->getPageRepository();

            $this->pages = array();
            foreach ($pageInfos as $pageInfo)
            {
                $page = $pageRepository->getOrCreatePage(
                    UriBuilder::buildUri($this->pieCrust, $pageInfo->relativePath),
                    $pageInfo->path
                );

                $this->pages[] = $page;
            }
        }
    }

    protected function ensurePostsCached($blogKey)
    {
        if ($this->posts == null)
        {
            $this->posts = array();
        }
        if (!isset($this->posts[$blogKey]))
        {
            $this->getLog()->debug("Indexing '{$blogKey}' posts...");
            $fs = $this->getFileSystem();
            $postInfos = $fs->getPostFiles($blogKey);

            $this->getLog()->debug("Creating '{$blogKey}' posts...");
            $pageRepository = $this->getPageRepository();

            $posts = array();
            foreach ($postInfos as $postInfo)
            {
                $uri = UriBuilder::buildPostUri($this->pieCrust, $blogKey, $postInfo);
                $page = $pageRepository->getOrCreatePage(
                    $uri,
                    $postInfo->path,
                    IPage::TYPE_POST,
                    $blogKey
                );
                $page->setDate(PageHelper::getPostDate($postInfo));

                $posts[] = $page;
            }
            $this->posts[$blogKey] = $posts;
        }
    }
}

