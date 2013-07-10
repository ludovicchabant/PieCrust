<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\UriBuilder;


/**
 * Helper class for doing stuff with pages and posts.
 */
class PageHelper
{
    /**
     * Gets the relative path of a page.
     */
    public static function getRelativePath(IPage $page, $stripExtension = false)
    {
        $basePath = null;
        $themeDir = $page->getApp()->getThemeDir();
        if ($themeDir and strncmp($page->getPath(), $themeDir, strlen($themeDir)) == 0)
        {
            // This is a theme page.
            switch ($page->getPageType())
            {
                case IPage::TYPE_REGULAR:
                case IPage::TYPE_CATEGORY:
                case IPage::TYPE_TAG:
                    $basePath = $themeDir . PieCrustDefaults::CONTENT_PAGES_DIR;
                    break;
                case IPage::TYPE_POST:
                    $basePath = $themeDir . PieCrustDefaults::CONTENT_POSTS_DIR;
                    break;
                default:
                    throw new InvalidArgumentException("Unknown page type given: " . $page->getPageType());
            }
        }
        else
        {
            // This is a website page.
            switch ($page->getPageType())
            {
                case IPage::TYPE_REGULAR:
                case IPage::TYPE_CATEGORY:
                case IPage::TYPE_TAG:
                    $basePath = $page->getApp()->getPagesDir();
                    break;
                case IPage::TYPE_POST:
                    $basePath = $page->getApp()->getPostsDir();
                    break;
                default:
                    throw new InvalidArgumentException("Unknown page type given: " . $page->getPageType());
            }
        }
        if (!$basePath)
            throw new PieCrustException("Can't get a relative page path if no pages or posts directory exsists in the website.");
        
        $relativePath = substr($page->getPath(), strlen($basePath));
        if ($stripExtension)
            $relativePath = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
        return $relativePath;
    }

    /**
     * Gets a configuration value either on the given page, or on its parent
     * application.
     */
    public static function getConfigValue(IPage $page, $key, $appSection)
    {
        if ($page->getConfig()->hasValue($key))
            return $page->getConfig()->getValueUnchecked($key);
        return $page->getApp()->getConfig()->getValue($appSection.'/'.$key);
    }

    /**
     * Gets a configuration value either on the given page, or on its parent
     * application.
     */
    public static function getConfigValueUnchecked(IPage $page, $key, $appSection)
    {
        if ($page->getConfig()->hasValue($key))
            return $page->getConfig()->getValueUnchecked($key);
        return $page->getApp()->getConfig()->getValueUnchecked($appSection.'/'.$key);
    }
    
    /**
     * Gets a timestamp/date from a post info array.
     */
    public static function getPostDate($postInfo)
    {
        return mktime(0, 0, 0, $postInfo->monthValue, $postInfo->dayValue, $postInfo->yearValue);
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

    /**
     * Calls a function on every page found in the application.
     */
    public static function processPages(IPieCrust $pieCrust, $callback)
    {
        $pages = self::getPages($pieCrust);
        foreach ($pages as $page)
        {
            if (call_user_func($callback, $page) === false)
                break;
        }
    }

    /**
     * Gets all the pages found for in a website.
     */
    public static function getPages(IPieCrust $pieCrust)
    {
        return $pieCrust->getEnvironment()->getPages();
    }

    /**
     * Calls a function on every post found in the given blog.
     */
    public static function processPosts(IPieCrust $pieCrust, $blogKey, $callback)
    {
        $posts = self::getPosts($pieCrust, $blogKey);
        foreach ($posts as $post)
        {
            if (call_user_func($callback, $post) === false)
                break;
        }
    }

    /**
     * Gets all the posts found for in a website for a particular blog.
     */
    public static function getPosts(IPieCrust $pieCrust, $blogKey)
    {
        return $pieCrust->getEnvironment()->getPosts($blogKey);
    }
}
