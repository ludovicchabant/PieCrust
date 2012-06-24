<?php

namespace PieCrust\Page;

use \Exception;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Data\DataBuilder;
use PieCrust\IO\Cache;
use PieCrust\Util\PathHelper;
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
            $this->config = null;
            $this->contents = null;
            $this->pageData = null;
        }
    }
    
    protected $date;
    /**
     * Gets the date this page was created.
     */
    public function getDate()
    {
        if ($this->date === null)
        {
            $this->date = filemtime($this->path);
        }
        return $this->date;
    }
    
    /**
     * Sets the date this page was created.
     */
    public function setDate($date)
    {
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
    
    protected $type;
    /**
     * Gets the page type.
     */
    public function getPageType()
    {
        return $this->type;
    }
    
    protected $key;
    /**
     * Gets the page key (e.g. the tag or category)
     */
    public function getPageKey()
    {
        return $this->key;
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
    
    protected $didFormatContents;
    protected $contents;
    /**
     * Gets the page's formatted content.
     */
    public function getContentSegment($segment = 'content')
    {
        $this->ensureContentsLoaded();
        return $this->contents[$segment];
    }
    
    /**
     * Returns whether a given content segment exists.
     */
    public function hasContentSegment($segment)
    {
        $this->ensureContentsLoaded();
        return isset($this->contents[$segment]);
    }
    
    /**
     * Gets all the page's formatted content segments.
     */
    public function getContentSegments($ensureFormatted = true)
    {
        if($ensureFormatted) $this->ensureContentsLoaded();
        return $this->contents;
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
        if ($this->config != null or $this->contents != null)
            throw new PieCrustException("Extra data on a page must be set before the page's configuration, contents and data are loaded.");
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
        $this->pageData = null;

        $this->config = null;
        $this->contents = null;
        $this->didFormatContents = false;
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
            $this->didFormatContents = false;
        }
    }
    
    /**
     * Ensures the page has been loaded from disk.
     */
    protected function ensureContentsLoaded()
    {
        if (!$this->didFormatContents)
        {
            $this->ensureConfigLoaded();

            $loader = new PageLoader($this);
            $this->contents = $loader->formatContents($this->contents);
            $this->didFormatContents = true;
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
            return $pageRepository->getOrCreatePage(
                $uriInfo['uri'],
                $uriInfo['path'],
                $uriInfo['type'],
                $uriInfo['blogKey'],
                $uriInfo['key'],
                $uriInfo['page'],
                $uriInfo['date']
            );
        }
        else
        {
            return new static(
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
        
        $relativePath = PathHelper::getRelativePath($pieCrust, $path, $pageType);
        $uri = UriBuilder::buildUri($relativePath);
        return new static(
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
