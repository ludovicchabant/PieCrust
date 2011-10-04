<?php

namespace PieCrust\Page;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;


/**
 * A class that stores already processed pages for recycling.
 */
class PageRepository
{
    protected static $enabled = true;
    protected static $pages = array();
    
    public static function isEnabled()
    {
        return self::$enabled;
    }
    
    public static function enable($onOff = true)
    {
        self::$enabled = $onOff;
    }
    
    public static function clearPages()
    {
        self::$pages = array();
    }
    
    public static function addPage(Page $page)
    {
        self::$pages[$page->getUri()] = $page;
    }
    
    public static function getPage($uri)
    {
        if (!isset(self::$pages[$uri])) return null;
        return self::$pages[$uri];
    }
    
    public static function getOrCreatePage(PieCrust $pieCrust, $uri, $path, $pageType = Page::TYPE_REGULAR, $blogKey = null, $pageKey = null, $pageNumber = 1)
    {
        if (!self::$enabled)
        {
            return new Page($pieCrust, $uri, $path, $pageType, $blogKey, $pageKey, $pageNumber);
        }
        
        $page = self::getPage($uri);
        if ($page == null)
        {
            $page = new Page($pieCrust, $uri, $path, $pageType, $blogKey, $pageKey, $pageNumber);
            self::addPage($page);
        }
        else
        {
            assert($uri == $page->getUri());
            assert($path == $page->getPath());
            assert($pageType == $page->getPageType());
            assert($blogKey == $page->getBlogKey());
            assert($pageKey == $page->getPageKey());
            $page->setPageNumber($pageNumber);
        }
        return $page;
    }
}
