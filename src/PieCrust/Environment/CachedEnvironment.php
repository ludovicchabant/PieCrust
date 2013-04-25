<?php

namespace PieCrust\Environment;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
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

    protected $pageInfos;
    /**
     * Gets the page infos.
     */
    public function getPageInfos()
    {
        $this->ensurePageInfosCached();
        return $this->pageInfos;
    }

    protected $postInfos;
    /**
     * Gets the post infos.
     */
    public function getPostInfos($blogKey)
    {
        $this->ensurePostInfosCached($blogKey);
        return $this->postInfos[$blogKey];
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

    protected function ensurePageInfosCached()
    {
        if ($this->pageInfos == null)
        {
            // TODO: optimize this, it's quite stupid.

            // Start with the built-in pages.
            $resPagesDir = PieCrustDefaults::RES_DIR() . 'pages/';
            $fs = new FlatFileSystem($resPagesDir, null);
            $this->pageInfos = $fs->getPageFiles();

            // Override with the theme pages.
            if ($this->pieCrust->getThemeDir())
            {
                $fs = FileSystem::create($this->pieCrust, null, true);
                $themePageInfos = $fs->getPageFiles();
                foreach ($themePageInfos as $themePageInfo)
                {
                    $isOverridden = false;
                    foreach ($this->pageInfos as &$pageInfo)
                    {
                        if ($pageInfo['relative_path'] == $themePageInfo['relative_path'])
                        {
                            $isOverridden = true;
                            $pageInfo['path'] = $themePageInfo['path'];
                            break;
                        }
                    }
                    if (!$isOverridden)
                    {
                        $this->pageInfos[] = $themePageInfo;
                    }
                }
            }

            // And finally override with the user pages.
            $fs = FileSystem::create($this->pieCrust, null);
            $userPageInfos = $fs->getPageFiles();
            foreach ($userPageInfos as $userPageInfo)
            {
                $isOverridden = false;
                foreach ($this->pageInfos as &$pageInfo)
                {
                    if ($pageInfo['relative_path'] == $userPageInfo['relative_path'])
                    {
                        $isOverridden = true;
                        $pageInfo['path'] = $userPageInfo['path'];
                        break;
                    }
                }
                if (!$isOverridden)
                {
                    $this->pageInfos[] = $userPageInfo;
                }
            }
        }
    }

    protected function ensurePostInfosCached($blogKey)
    {
        if ($this->postInfos == null)
        {
            $this->postInfos = array();
        }
        if (!isset($this->postInfos[$blogKey]))
        {
            $fs = FileSystem::create($this->pieCrust, $blogKey);
            $postInfos = $fs->getPostFiles();
            $this->postInfos[$blogKey] = $postInfos;
        }
    }

    protected function ensurePagesCached()
    {
        if ($this->pages == null)
        {
            $pageRepository = $this->getPageRepository();
            $pageInfos = $this->getPageInfos();

            $this->pages = array();
            foreach ($pageInfos as $pageInfo)
            {
                $page = $pageRepository->getOrCreatePage(
                    UriBuilder::buildUri($this->pieCrust, $pageInfo['relative_path']),
                    $pageInfo['path']
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
            $pageRepository = $this->getPageRepository();
            $postInfos = $this->getPostInfos($blogKey);

            $posts = array();
            foreach ($postInfos as $postInfo)
            {
                $uri = UriBuilder::buildPostUri($this->pieCrust, $blogKey, $postInfo);
                $page = $pageRepository->getOrCreatePage(
                    $uri,
                    $postInfo['path'],
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

