<?php

namespace PieCrust\Environment;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Page\Page;


/**
 * A class that stores already processed pages for recycling.
 */
class PageRepository
{
    protected $pieCrust;
    protected $enabled;
    protected $pages;
    protected $pageUris;
    protected $assetUrlBaseRemap;
    protected $limit;
    protected $collectSize;

    public function __construct(IPieCrust $pieCrust, $enabled = true)
    {
        $this->pieCrust = $pieCrust;
        $this->enabled = $enabled;
        $this->pages = array();
        $this->pageUris = array();
        $this->assetUrlBaseRemap = null;
        $this->limit = 10000;
        $this->collectSize = ($this->limit / 10);

        $log = $pieCrust->getEnvironment()->getLog();
        $log->debug("Initializing page repository with a limit of {$this->limit} pages.");
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
        $this->pageUris = array();
    }
    
    public function addPage(IPage $page)
    {
        if (!$this->enabled)
            return;
        $this->pages[$page->getUri()] = $page;
        $this->pageUris[] = $page->getUri();

        $count = count($this->pages);
        if ($count > $this->limit)
        {
            // Garbage collect some of the pages...
            $log = $this->pieCrust->getEnvironment()->getLog();
            $log->debug("Page repository reached {$count} pages, limited to {$this->limit}.");
            $log->debug("Garbage collecting {$this->collectSize} pages...");
            for ($i = 0; $i < $this->collectSize; ++$i)
            {
                $uri = $this->pageUris[0];
                array_shift($this->pageUris);
                unset($this->pages[$uri]);
            }
        }
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
}
