<?php

namespace PieCrust\Environment;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
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
            $this->linkCollector = new LinkCollector();
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

    protected $lastRunInfo;
    /**
     * Gets the info about the last executed request.
     */
    public function getLastRunInfo()
    {
        return $this->lastRunInfo;
    }

    /**
     * Sets the info about the last executed request.
     */
    public function setLastRunInfo($runInfo)
    {
        $this->lastRunInfo = $runInfo;
    }

    /**
     * Creates a new instance of Environment.
     */
    public function __construct(IPieCrust $pieCrust)
    {
        parent::__construct($pieCrust);
    }

    protected function ensurePageInfosCached()
    {
        if ($this->pageInfos == null)
        {
            $fs = FileSystem::create($this->pieCrust, null);
            $this->pageInfos = $fs->getPageFiles();
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
                    UriBuilder::buildUri($pageInfo['relative_path']),
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
            $postUrlFormat = $this->pieCrust->getConfig()->getValue($blogKey.'/post_url');

            $posts = array();
            foreach ($postInfos as $postInfo)
            {
                $uri = UriBuilder::buildPostUri($postUrlFormat, $postInfo);
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

