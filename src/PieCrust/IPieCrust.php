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
     * Gets the directories that contain the user plugins.
     */
    public function getPluginsDirs();

    /**
     * Sets the directories that contain the user plugins.
     */
    public function setPluginsDirs($dir);

    /**
     * Adds a directory that contains some user plugins.
     */
    public function addPluginsDir($dir);

    /**
     * Gets the directory that contains the current theme, if any.
     */
    public function getThemeDir();

    /**
     * Sets the directory that contains the current theme, if any.
     */
    public function setThemeDir($dir);
    
    /**
     * Gets the cache directory ('/_cache' by default).
     */
    public function getCacheDir();
    
    /**
     * Sets the cache directory ('/_cache' by default).
     */
    public function setCacheDir($dir);

    /**
     * Gets the plugin loader for this app.
     */
    public function getPluginLoader();
    
    /**
     * Gets the application's configuration.
     */
    public function getConfig();

    /**
     * Gets the applicaiton's execution environment.
     */
    public function getEnvironment();
}
