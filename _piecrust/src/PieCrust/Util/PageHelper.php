<?php

namespace PieCrust\Util;

use PieCrust\IPage;


class PageHelper
{
    /**
     * Gets the relative path of a page.
     */
    public static function getRelativePath(IPage $page, $stripExtension = false)
    {
        return PathHelper::getRelativePath($page->getApp(), $page->getPath(), $stripExtension);
    }

    /**
     * Gets a configuration value either on the given page, or on its parent
     * application.
     */
    public static function getConfigValue(IPage $page, $key, $appSection)
    {
        if ($page->getConfig()->hasValue($key))
            return $page->getConfig()->getValue($key);
        return $page->getApp()->getConfig()->getValue($appSection.'/'.$key);
    }
    
    /**
     * Gets a timestamp/date from a post info array.
     */
    public static function getPostDate(array $postInfo)
    {
        return mktime(0, 0, 0, intval($postInfo['month']), intval($postInfo['day']), intval($postInfo['year']));
    }
    
    /**
     * Gets whether the given page is a regular page.
     */
    public static function isRegular(IPage $page)
    {
        return $page->getPageType() == IPage::TYPE_REGULAR;
    }
    
    /**
     * Gets whether the given page is a blog post.
     */
    public static function isPost(IPage $page)
    {
        return $page->getPageType() == IPage::TYPE_POST;
    }
    
    /**
     * Gets whether the given page is a tag listing.
     */
    public static function isTag(IPage $page)
    {
        return $page->getPageType() == IPage::TYPE_TAG;
    }
    
    /**
     * Gets whether the given page is a category listing.
     */
    public static function isCategory(IPage $page)
    {
        return $page->getPageType() == IPage::TYPE_CATEGORY;
    }
}
