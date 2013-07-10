<?php

namespace PieCrust\Environment;

use PieCrust\IPage;
use PieCrust\IPageObserver;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Page\Page;


/**
 * A class that stores already processed pages for recycling.
 */
class PageRepository implements IPageObserver
{
    protected $pieCrust;
    protected $enabled;
    protected $pages;
    protected $assetUrlBaseRemap;

    protected $limit;
    protected $collectSize;
    protected $loadedTimes;

    public function __construct(IPieCrust $pieCrust, $enabled = true)
    {
        $this->pieCrust = $pieCrust;
        $this->enabled = $enabled;
        $this->pages = array();
        $this->assetUrlBaseRemap = null;
        $this->limit = 1000;
        $this->collectSize = ($this->limit / 10);
        $this->loadedTimes = array();

        $log = $pieCrust->getEnvironment()->getLog();
        $log->debug("Initializing page repository with a limit of {$this->limit} loaded pages.");
    }
    
    public function isEnabled()
    {
        return $this->enabled;
    }
    
    public function enable($onOff = true)
    {
        $this->enabled = $onOff;
    }
    
    public function clearPages()
    {
        $this->pages = array();
    }
    
    public function addPage(IPage $page)
    {
        if (!$this->enabled)
            return;

        $this->pages[$page->getUri()] = $page;

        if ($page->isLoaded())
        {
            $timestamp = microtime();
            $this->loadedTimes[$page->getUri()] = $timestamp;
            $this->checkLimits($page);
        }

        $page->addObserver($this);
    }

    public function getPages()
    {
        return $this->pages;
    }
    
    public function getPage($uri)
    {
        if (!$this->enabled)
            return null;
        if (!isset($this->pages[$uri]))
            return null;
        return $this->pages[$uri];
    }
    
    public function getOrCreatePage($uri, $path, $pageType = IPage::TYPE_REGULAR, $blogKey = null)
    {
        $page = $this->getPage($uri);
        if ($page == null)
        {
            $page = new Page($this->pieCrust, $uri, $path, $pageType, $blogKey);
            if ($this->assetUrlBaseRemap != null)
                $page->setAssetUrlBaseRemap($this->assetUrlBaseRemap);
            $this->addPage($page);
        }
        else
        {
            $page->setPageNumber(1);
        }
        return $page;
    }

    public function setAssetUrlBaseRemap($remap)
    {
        $this->assetUrlBaseRemap = $remap;
    }

    // IPageObserver Members {{{

    public function onPageLoaded($page)
    {
        $timestamp = microtime();
        $this->loadedTimes[$page->getUri()] = $timestamp;
        $this->checkLimits($page);
    }

    public function onPageFormatted($page)
    {
    }

    public function onPageUnloaded($page)
    {
        unset($this->loadedTimes[$page->getUri()]);
    }
    
    // }}}

    protected function checkLimits($protectedPage)
    {
        $count = count($this->loadedTimes);
        if ($this->limit > 0 && $count > $this->limit)
        {
            // Garbage collect some of the pages that were
            // loaded the longest time ago.
            $log = $this->pieCrust->getEnvironment()->getLog();
            $log->debug("Page repository reached {$count} loaded pages, limited to {$this->limit}.");

            $collected = 0;
            ksort($this->loadedTimes);
            $loadedTimesCopy = $this->loadedTimes;
            foreach ($loadedTimesCopy as $uri => $timestamp)
            {
                $page = $this->pages[$uri];
                if ($page != $protectedPage)
                {
                    $page->unload();
                    $collected++;
                    if ($collected >= $this->collectSize)
                        break;
                }
            }

            $log->debug("Garbage collected {$collected}/{$this->collectSize} pages...");
        }
    }
}
