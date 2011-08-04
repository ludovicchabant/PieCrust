<?php


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
    
    public static function getOrCreatePage(PieCrust $pieCrust, $uri, $path, $pageType = PIECRUST_PAGE_REGULAR, $pageNumber = 1, $pageKey = null)
    {
        if (!self::$enabled)
        {
            return new Page($pieCrust, $uri, $path, $pageType, $pageNumber, $pageKey);
        }
        
        $page = self::getPage($uri);
        if ($page == null)
        {
            $page = new Page($pieCrust, $uri, $path, $pageType, $pageNumber, $pageKey);
            self::addPage($page);
        }
        else
        {
            assert($uri == $page->getUri());
            assert($path == $page->getPath());
            assert($pageType == $page->getPageType());
            assert($pageKey == $page->getPageKey());
            $page->setPageNumber($pageNumber);
        }
        return $page;
    }
}
