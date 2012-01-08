<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\IO\FileSystem;
use PieCrust\Page\PageRepository;
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

    /**
     * Calls a function on every page found in the application.
     */
    public function processPages(IPieCrust $pieCrust, $callback)
    {
        $pagesDir = $pieCrust->getPagesDir();
        $directory = new \RecursiveDirectoryIterator($pagesDir);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $path)
        {
            if ($iterator->isDot())
                continue;

            $pagePath = $path->getPathname();
            $relativePath = PathHelper::getRelativePagePath($pieCrust, $pagePath, IPage::TYPE_REGULAR);
            $relativePathInfo = pathinfo($relativePath);
            if ($relativePathInfo['filename'] == PieCrustDefaults::CATEGORY_PAGE_NAME or
                $relativePathInfo['filename'] == PieCrustDefaults::TAG_PAGE_NAME or
                $relativePathInfo['extension'] != 'html')
            {
                continue;
            }

            $page = PageRepository::getOrCreatePage(
                $pieCrust,
                UriBuilder::buildUri($relativePath),
                $pagePath
            );
            $callback($page);
        }
    }

    /**
     * Calls a function on every post found in the given blog.
     */
    public function processPosts(IPieCrust $pieCrust, $callback, $blogKey = null)
    {
        if ($blogKey == null)
        {
            $blogs = $pieCrust->getConfig()->getValue('site/blogs');
            $blogKey = $blogs[0];
        }

        $fs = FileSystem::create($pieCrust, $blogKey);
        $postInfos = $fs->getPostFiles();

        $postUrlFormat = $pieCrust->getConfig()->getValue($blogKey.'/post_url');
        foreach ($postInfos as $postInfo)
        {
            $uri = UriBuilder::buildPostUri($postUrlFormat, $postInfo);
            $page = PageRepository::getOrCreatePage(
                $pieCrust,
                $uri,
                $postInfo['path'],
                IPage::TYPE_POST,
                $blogKey
            );
            $page->setDate(self::getPostDate($postInfo));
            $callback($page);
        }
    }
}
