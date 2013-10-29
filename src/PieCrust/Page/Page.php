<?php

namespace PieCrust\Page;

use \Exception;
use \InvalidArgumentException;
use PieCrust\IPage;
use PieCrust\IPageObserver;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Data\DataBuilder;
use PieCrust\IO\Cache;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriParser;
use PieCrust\Util\UriBuilder;


/**
 * A class that represents a page (article or post) in PieCrust.
 *
 */
class Page implements IPage
{
    protected $pieCrust;
    /**
     * Gets the PieCrust app this page belongs to.
     */
    public function getApp()
    {
        return $this->pieCrust;
    }
    
    protected $path;
    /**
     * Gets the file-system path to the page's file.
     */
    public function getPath()
    {
        return $this->path;
    }
    
    protected $uri;
    /**
     * Gets the PieCrust URI to the page.
     */
    public function getUri()
    {
        return $this->uri;
    }
    
    protected $blogKey;
    /**
     * Gets the blog key for this page.
     */
    public function getBlogKey()
    {
        return $this->blogKey;
    }
    
    protected $pageNumber;
    /**
     * Gets the page number (for pages that display a large number of posts).
     */
    public function getPageNumber()
    {
        return $this->pageNumber;
    }
    
    /**
     * Sets the page number.
     */
    public function setPageNumber($pageNumber)
    {
        if ($pageNumber != $this->pageNumber)
        {
            $this->pageNumber = $pageNumber;
            $this->unload();
        }
    }
    
    protected $key;
    /**
     * Gets the page key (e.g. the tag or category)
     */
    public function getPageKey()
    {
        return $this->key;
    }

    /**
     * Sets the page key (e.g. the tag or category)
     */
    public function setPageKey($key)
    {
        if ($key != $this->key)
        {
            $this->key = $key;
            $this->unload();
        }
    }
    
    protected $date;
    protected $dateIsLocked;
    /**
     * Gets the date (and possibly time) this page was created.
     *
     * This loads the page's configuration, unless the date was already
     * specifically set with `setDate` (which is the case for blog posts
     * for example).
     */
    public function getDate($withTime = false)
    {
        if ($this->date === null or ($withTime && !$this->dateIsLocked))
        {
            // Get the date from the file on disk if it was never set.
            // Override with the date from the config if authorized to do so.
            $dateFromConfig = $this->getConfig()->getValue('date');
            if ($dateFromConfig != null)
                $this->date = strtotime($dateFromConfig);
            else if ($this->date === null)
                $this->date = filemtime($this->path);

            // Add/adjust the time of day if needed.
            $timeFromConfig = $this->getConfig()->getValue('time');
            if ($timeFromConfig != null)
                $this->date = strtotime($timeFromConfig, $this->date);

            // Lock the date.
            $this->dateIsLocked = true;
        }
        return $this->date;
    }
    
    /**
     * Sets the date this page was created.
     */
    public function setDate($date, $isLocked = false)
    {
        if ($date !== null && $this->date != $date)
        {
            $this->dateIsLocked = $isLocked;

            if (is_int($date))
            {
                $this->date = $date;
            }
            else if (is_string($date))
            {
                $this->date = strtotime($date);
            }
            else
            {
                throw new PieCrustException("The date must be an number or a string.");
            }
        }
    }
    
    protected $type;
    /**
     * Gets the page type.
     */
    public function getPageType()
    {
        return $this->type;
    }
    
    protected $wasCached;
    /**
     * Gets whether this page's contents were cached.
     */
    public function wasCached()
    {
        return $this->wasCached;
    }
    
    protected $config;
    /**
     * Gets the page's configuration from its YAML header.
     */
    public function getConfig()
    {
        $this->ensureConfigLoaded();
        return $this->config;
    }
    
    protected $contents;
    protected $formattedContents;
    /**
     * Gets the page's formatted content.
     */
    public function getContentSegment($segment = 'content')
    {
        $this->ensureContentsFormatted();
        return $this->formattedContents[$segment];
    }
    
    /**
     * Returns whether a given content segment exists.
     */
    public function hasContentSegment($segment)
    {
        $this->ensureContentsFormatted();
        return isset($this->formattedContents[$segment]);
    }
    
    /**
     * Gets all the page's formatted content segments.
     */
    public function getContentSegments()
    {
        $this->ensureContentsFormatted();
        return $this->formattedContents;
    }
    
    protected $pageData;
    /**
     * Gets the data used for rendering the page.
     */
    public function getPageData()
    {
        if ($this->pageData == null)
        {
            $this->pageData = DataBuilder::getPageData($this);
        }
        return $this->pageData;
    }
    
    protected $extraData;
    /**
     * Gets the extra data.
     */
    public function getExtraPageData()
    {
        return $this->extraData;
    }
    
    /**
     * Adds extra data to the page's data for rendering.
     */
    public function setExtraPageData(array $data)
    {
        $this->unload();
        $this->extraData = $data;
    }
    
    protected $assetUrlBaseRemap;
    /**
     * Gets the asset URL base remapping pattern.
     */
    public function getAssetUrlBaseRemap()
    {
        return $this->assetUrlBaseRemap;
    }
    
    /**
     * Sets the asset URL base remapping pattern.
     */
    public function setAssetUrlBaseRemap($remap)
    {
        $this->assetUrlBaseRemap = $remap;
    }
 
    protected $paginationDataSource;
    /**
     * Gets the pagination data source.
     */
    public function getPaginationDataSource()
    {
        return $this->paginationDataSource;
    }

    /**
     * Sets the pagination data source.
     */
    public function setPaginationDataSource($postInfos)
    {
        $this->paginationDataSource = $postInfos;
    }

    /**
     * Unloads the page.
     */
    public function unload()
    {
        $this->config = null;
        $this->contents = null;
        $this->pageData = null;
        $this->formattedContents = null;

        foreach ($this->observers as $observer)
        {
            $observer->onPageUnloaded($this);
        }
    }

    /**
     * Returns whether the page is currently loaded.
     */
    public function isLoaded()
    {
        return $this->config != null ||
            $this->contents != null ||
            $this->pageData != null || 
            $this->formattedContents != null;
    }

    protected $observers;
    /**
     * Adds a page observer.
     */
    public function addObserver(IPageObserver $observer)
    {
        $this->observers[] = $observer;
    }

    /**
     * Creates a new Page instance.
     */
    public function __construct(IPieCrust $pieCrust, $uri, $path, $pageType = IPage::TYPE_REGULAR, $blogKey = null, $pageKey = null, $pageNumber = 1, $date = null)
    {
        $this->pieCrust = $pieCrust;

        $this->uri = $uri;
        $this->path = $path;
        $this->type = $pageType;
        $this->blogKey = $blogKey;
        $this->key = $pageKey;
        $this->pageNumber = $pageNumber;
        $this->date = $date;
        $this->dateIsLocked = false;
        $this->pageData = null;

        $this->config = null;
        $this->contents = null;
        $this->formattedContents = null;

        $this->observers = array();
    }
    
    /**
     * Ensures the page has been loaded from disk.
     */
    protected function ensureConfigLoaded()
    {
        if ($this->config == null)
        {
            // Set the configuration to an empty array so that the PageLoader
            // can call 'set()' on it and assign the actual configuration.
            $this->config = new PageConfiguration($this, array(), false);

            $loader = new PageLoader($this);
            $this->contents = $loader->load();
            $this->wasCached = $loader->wasCached();
            $this->formattedContents = null;

            foreach ($this->observers as $observer)
            {
                $observer->onPageLoaded($this);
            }
        }
    }
    
    /**
     * Ensures the page has been formatted completely.
     */
    protected function ensureContentsFormatted()
    {
        if ($this->formattedContents == null)
        {
            $this->ensureConfigLoaded();

            $loader = new PageLoader($this);
            $this->formattedContents = $loader->formatContents($this->contents);

            foreach ($this->observers as $observer)
            {
                $observer->onPageFormatted($this);
            }
        }
    }
    
    /**
     * Creates a new Page instance given a fully qualified URI.
     */
    public static function createFromUri(IPieCrust $pieCrust, $uri, $useRepository = true)
    {
        if ($uri == null)
            throw new InvalidArgumentException("The given URI is null.");
        
        $uriInfo = UriParser::parseUri($pieCrust, $uri);
        if ($uriInfo == null or
            (!$uriInfo['was_path_checked'] and !is_file($uriInfo['path']))
           )
        {
            if ($uriInfo['type'] == IPage::TYPE_TAG)
                throw new PieCrustException("Tried to show posts with tag '{$uriInfo['key']}' but the special tag listing page was not found.");
            if ($uriInfo['type'] == IPage::TYPE_CATEGORY)
                throw new PieCrustException("Tried to show the posts in category '{$uriInfo['key']}' but the special category listing page was not found.");
            throw new PieCrustException('404');
        }
        
        if ($useRepository)
        {
            $pageRepository = $pieCrust->getEnvironment()->getPageRepository();
            $page = $pageRepository->getOrCreatePage(
                $uriInfo['uri'],
                $uriInfo['path'],
                $uriInfo['type'],
                $uriInfo['blogKey']
            );
            $page->setPageKey($uriInfo['key']);
            $page->setPageNumber($uriInfo['page']);
            $page->setDate($uriInfo['date']);
            return $page;
        }
        else
        {
            return new Page(
                $pieCrust,
                $uriInfo['uri'],
                $uriInfo['path'],
                $uriInfo['type'],
                $uriInfo['blogKey'],
                $uriInfo['key'],
                $uriInfo['page'],
                $uriInfo['date']
            );
        }
    }
    
    /**
     * Creates a new Page instance given a path.
     */
    public static function createFromPath(IPieCrust $pieCrust, $path, $pageType = IPage::TYPE_REGULAR, $pageNumber = 1, $blogKey = null, $pageKey = null, $date = null)
    {
        if ($path == null)
            throw new InvalidArgumentException("The given path is null.");
        if (!is_file($path))
            throw new InvalidArgumentException("The given path does not exist: " . $path);
        
        $relativePath = PieCrustHelper::getRelativePath($pieCrust, $path, true);
        $uri = UriBuilder::buildUri($pieCrust, $relativePath);
        return new Page(
                $pieCrust,
                $uri,
                $path,
                $pageType,
                $blogKey,
                $pageKey,
                $pageNumber,
                $date
            );
    }
}
