<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * A class that stores already processed pages for recycling.
 */
class PageRepository
{
    protected static $enabled = true;
    protected static $pages = array();
    protected static $assetUrlBaseRemap = null;
    
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
    
    public static function addPage(IPage $page)
    {
        if (!self::$enabled)
            return;
        self::$pages[$page->getUri()] = $page;
    }

    public static function getPages()
    {
        return self::$pages;
    }
    
    public static function getPage($uri)
    {
        if (!self::$enabled)
            return null;
        if (!isset(self::$pages[$uri]))
            return null;
        return self::$pages[$uri];
    }
    
    public static function getOrCreatePage(IPieCrust $pieCrust, $uri, $path, $pageType = IPage::TYPE_REGULAR, $blogKey = null, $pageKey = null, $pageNumber = 1)
    {
        $page = self::getPage($uri);
        if ($page == null)
        {
            $page = new Page($pieCrust, $uri, $path, $pageType, $blogKey, $pageKey, $pageNumber);
            if (self::$assetUrlBaseRemap != null)
                $page->setAssetUrlBaseRemap(self::$assetUrlBaseRemap);
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

    public static function setAssetUrlBaseRemap($remap)
    {
        self::$assetUrlBaseRemap = $remap;
    }
}
