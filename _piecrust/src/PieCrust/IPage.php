<?php

namespace PieCrust;


/**
 * The interface for a PieCrust page.
 */
interface IPage
{
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
     * Sets the page number.
     */
    public function setPageNumber($pageNumber);
    
    /**
     * Gets the date this page was created.
     */
    public function getDate();
    
    /**
     * Sets the date this page was created.
     */
    public function setDate($date);
    
    /**
     * Gets the page type.
     */
    public function getPageType();
    
    /**
     * Gets the page key (e.g. the tag or category)
     */
    public function getPageKey();
    
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
}
