<?php

namespace PieCrust;


/**
 * The interface for a PieCrust application.
 */
interface IPieCrust
{
    /**
     * The root directory of the website.
     */
    public function getRootDir();
    
    /**
     * Gets whether caching is enabled.
     */
    public function isCachingEnabled();
    
    /**
     * Gets whether debugging is enabled.
     */
    public function isDebuggingEnabled();
    
    /**
     * Gets the directories that contain templates and layouts ('/_content/templates' by default).
     */
    public function getTemplatesDirs();
    
    /**
     * Sets the directories that contain templates and layouts. Directories can be
     * relative to the site's root directory.
     */
    public function setTemplatesDirs($dir);
    
    /**
     * Adds a templates directory. It can be relative to the site's root directory.
     */
    public function addTemplatesDir($dir);
    
    /**
     * Gets the directory that contains the pages and their assets ('/_content/pages' by default).
     */
    public function getPagesDir();
    
    /**
     * Sets the directory that contains the pages and their assets.
     */
    public function setPagesDir($dir);
    
    /**
     * Gets the directory that contains the posts and their assets ('/_content/posts' by default).
     */
    public function getPostsDir();
    
    /**
     * Sets the directory that contains the posts and their assets.
     */
    public function setPostsDir($dir);
    
    /**
     * Gets the cache directory ('/_cache' by default).
     */
    public function getCacheDir();
    
    /**
     * Sets the cache directory ('/_cache' by default).
     */
    public function setCacheDir($dir);
    
    /**
     * Gets the application's configuration.
     */
    public function getConfig();
    
    /**
     * Formats a given text using the registered page formatters.
     */
    public function formatText($text, $format = null);
    
    /**
     * Gets a formatted page URL.
     */
    public function formatUri($uri);
    
    /**
     * Gets the template engine associated with the given extension.
     */
    public function getTemplateEngine($extension = 'html');
    
    /**
     * Gets the information about the last execution (call to run() or runUnsafe()).
     */
    public function getLastRunInfo();
    
    /**
     * Runs PieCrust on the given URI.
     */
    public function run($uri = null, $server = null);
    
    /**
     * Runs PieCrust on the given URI with the given extra page rendering data,
     * but without any error handling.
     */
    public function runUnsafe($uri = null, $server = null, $extraPageData = null, array &$headers = null);
}
