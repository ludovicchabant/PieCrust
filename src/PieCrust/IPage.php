<?php

namespace PieCrust;


/**
 * The interface for a PieCrust page.
 */
interface IPage
{
    // {{{ Page types.
    const TYPE_REGULAR = 1;
    const TYPE_POST = 2;
    const TYPE_TAG = 3;
    const TYPE_CATEGORY = 4;
    // }}}
    
    /**
     * Gets the PieCrust app this page belongs to.
     */
    public function getApp();
    
    /**
     * Gets the file-system path to the page's file.
     */
    public function getPath();
    
    /**
     * Gets the PieCrust URI to the page.
     */
    public function getUri();
    
    /**
     * Gets the blog key for this page.
     */
    public function getBlogKey();
    
    /**
     * Gets the page number (for pages that display a large number of posts).
     */
    public function getPageNumber();
    
    /**
     * Sets the page number. This also unloads the page.
     */
    public function setPageNumber($pageNumber);
    
    /**
     * Gets the page key (e.g. the tag or category)
     */
    public function getPageKey();

    /**
     * Sets the page key (e.g. the tag or category)
     */
    public function setPageKey($key);

    /**
     * Gets the date this page was created.
     */
    public function getDate($withTime = false);
    
    /**
     * Sets the date this page was created.
     */
    public function setDate($date);
    
    /**
     * Gets the page type.
     */
    public function getPageType();
    
    /**
     * Gets whether this page's contents were cached.
     */
    public function wasCached();
    
    /**
     * Gets the page's configuration from its YAML header.
     */
    public function getConfig();
    
    /**
     * Gets the page's formatted content.
     */
    public function getContentSegment($segment = 'content');
    
    /**
     * Returns whether a given content segment exists.
     */
    public function hasContentSegment($segment);
    
    /**
     * Gets all the page's formatted content segments.
     */
    public function getContentSegments();
    
    /**
     * Gets the data used for rendering the page.
     */
    public function getPageData();
    
    /**
     * Gets the extra data for the page's rendering.
     */
    public function getExtraPageData();
    
    /**
     * Adds extra data to the page's data for rendering.
     */
    public function setExtraPageData(array $data);
    
    /**
     * Gets the asset URL base remapping pattern.
     */
    public function getAssetUrlBaseRemap();
    
    /**
     * Sets the asset URL base remapping pattern.
     */
    public function setAssetUrlBaseRemap($remap);

    /**
     * Gets the pagination data source.
     */
    public function getPaginationDataSource();

    /**
     * Sets the pagination data source.
     */
    public function setPaginationDataSource($postInfos);

    /**
     * Unloads the page.
     */
    public function unload();

    /**
     * Returns whether the page is currently loaded.
     */
    public function isLoaded();

    /**
     * Adds a page observer.
     */
    public function addObserver(IPageObserver $observer);
}
