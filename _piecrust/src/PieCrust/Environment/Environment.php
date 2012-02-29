<?php

namespace PieCrust\Environment;

use \ArrayAccess;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * A class that stores information about the environment
 * a PieCrust app is running in.
 */
class Environment
{
    protected $pieCrust;

    protected $pageRepository;
    /**
     * Gets the environment's page repository.
     */
    public function getPageRepository()
    {
        return $this->pageRepository;
    }

    /**
     * Create the environment's page repository.
     */
    public function createPageRepository()
    {
        $this->pageRepository = new PageRepository($this->pieCrust);
    }

    protected $linkCollector;
    /**
     * Gets the environment's link collector.
     */
    public function getLinkCollector()
    {
        return $this->linkCollector;
    }

    /**
     * Create the environment's link collector.
     */
    public function createLinkCollector()
    {
        $this->linkCollector = new LinkCollector();
    }

    protected $cachedPostsInfos;
    /**
     * Gets the cached posts infos.
     */
    public function getCachedPostsInfos($blogKey = null)
    {
        if ($blogKey != null)
        {
            if (!isset($this->cachedPostsInfos[$blogKey]))
                return null;
            return $this->cachedPostsInfos[$blogKey];
        }
        return $this->cachedPostsInfos;
    }

    /**
     * Sets the posts infos for a blog.
     */
    public function setCachedPostsInfos($blogKey, array $posts)
    {
        $this->cachedPostsInfos[$blogKey] = $posts;
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

    protected $urlPrefix;
    protected $urlSuffix;
    /**
     * Gets the URL decorators for the current application.
     */
    public function getUrlDecorators()
    {
        return array($this->urlPrefix, $this->urlSuffix);
    }

    /**
     * Sets the URL decorators for the current application.
     */
    public function setUrlDecorators($urlPrefix, $urlSuffix)
    {
        $this->urlPrefix = $urlPrefix;
        $this->urlSuffix = $urlSuffix;
    }

    /**
     * Creates a new instance of Environment.
     */
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->cachedPostsInfos = array();
    }
}

