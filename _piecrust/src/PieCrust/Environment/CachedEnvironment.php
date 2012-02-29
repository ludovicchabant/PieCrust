<?php

namespace PieCrust\Environment;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;


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
}

