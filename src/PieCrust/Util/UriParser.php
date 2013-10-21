<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PathHelper;


/**
 * A utility class for parsing page URIs.
 */
class UriParser
{
    // {{{ Uri types
    const PAGE_URI_REGULAR = 1;
    const PAGE_URI_POST = 2;
    const PAGE_URI_TAG = 4;
    const PAGE_URI_CATEGORY = 8;
    const PAGE_URI_ANY = 15;
    // }}}

    /**
     * Parse a relative URI and returns information about it.
     */
    public static function parseUri(IPieCrust $pieCrust, $uri, $uriTypes = self::PAGE_URI_ANY)
    {
        if (strpos($uri, '..') !== false)   // Some bad boy's trying to access files outside of our standard folders...
        {
            throw new PieCrustException('404');
        }
        
        $uri = '/' . trim($uri, '/');
        
        $pageNumber = 1;
        $matches = array();
        if (preg_match('/\/(\d+)\/?$/', $uri, $matches))
        {
            // Requesting a page other than the first for this article.
            $uri = substr($uri, 0, strlen($uri) - strlen($matches[0]));
            $pageNumber = intval($matches[1]);
        }

        $uri = ltrim($uri, '/');
        
        $pageInfo = array(
                'uri' => $uri,
                'page' => $pageNumber,
                'type' => IPage::TYPE_REGULAR,
                'blogKey' => null,
                'key' => null,
                'date' => null,
                'path' => null,
                'was_path_checked' => false
            );
        
        // Try first with a regular page path.
        if (($uriTypes & self::PAGE_URI_REGULAR) != 0 and
            self::tryParsePageUri($pieCrust, $uri, $pageInfo))
        {
            return $pageInfo;
        }
        
        $blogKeys = $pieCrust->getConfig()->getValueUnchecked('site/blogs');
        
        // Try with a post.
        if (($uriTypes & self::PAGE_URI_POST) != 0)
        {
            foreach ($blogKeys as $blogKey)
            {
                if (self::tryParsePostUri($pieCrust, $blogKey, $uri, $pageInfo))
                {
                    return $pageInfo;
                }
            }
        }
        
        // Try with special pages (tag & category)
        if (($uriTypes & (self::PAGE_URI_CATEGORY | self::PAGE_URI_TAG)) != 0)
        {
            foreach ($blogKeys as $blogKey)
            {
                if (($uriTypes & self::PAGE_URI_TAG) != 0 and
                    self::tryParseTagUri($pieCrust, $blogKey, $uri, $pageInfo))
                {
                    return $pageInfo;
                }
                if (($uriTypes & self::PAGE_URI_CATEGORY) != 0 and
                    self::tryParseCategoryUri($pieCrust, $blogKey, $uri, $pageInfo))
                {
                    return $pageInfo;
                }
            }
        }
        
        // No idea what that URI is...
        return null;
    }
    
    private static function tryParsePageUri(IPieCrust $pieCrust, $uri, array &$pageInfo)
    {
        if ($uri == '')
        {
            $uri = PieCrustDefaults::INDEX_PAGE_NAME;
        }

        if (preg_match('/\.[a-zA-Z0-9]+$/', $uri))
        {
            // There's an extension specified, so no need to append `.html`.
            $relativePath = $uri;
        }
        else
        {
            $relativePath = array();
            $autoFormats = $pieCrust->getConfig()->getValueUnchecked('site/auto_formats');
            foreach ($autoFormats as $ext => $format)
            {
                $relativePath[] = $uri . '.' . $ext;
            }
        }

        $path = PathHelper::getUserOrThemePath($pieCrust, $relativePath);
        if ($path !== false)
        {
            $pageInfo['path'] = $path;
            $pageInfo['was_path_checked'] = true;
            return true;
        }
        return false;
    }
    
    private static function tryParsePostUri(IPieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $postsDir = $pieCrust->getPostsDir();
        if ($postsDir === false)
            return false;

        $matches = array();
        $postsPattern = UriBuilder::buildPostUriPattern($pieCrust->getConfig()->getValueUnchecked($blogKey.'/post_url'));
        if (preg_match($postsPattern, $uri, $matches))
        {
            $fs = $pieCrust->getEnvironment()->getFileSystem();
            $pathInfo = $fs->getPostPathInfo($blogKey, $matches, FileSystem::PATHINFO_PARSING);
            $date = mktime(0, 0, 0, intval($pathInfo['month']), intval($pathInfo['day']), intval($pathInfo['year']));
            
            $pageInfo['type'] = IPage::TYPE_POST;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['date'] = $date;
            $pageInfo['path'] = $pathInfo['path'];
            return true;
        }
        return false;
    }
    
    private static function tryParseTagUri(IPieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $blogKeyDir = '';
        if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
            $blogKeyDir = $blogKey . '/';
            
        $tagPageName = array();
        $themeTagPageName = array();
        $autoFormats = $pieCrust->getConfig()->getValueUnchecked('site/auto_formats');
        foreach ($autoFormats as $ext => $format)
        {
            $tagPageName[] = $blogKeyDir . PieCrustDefaults::TAG_PAGE_NAME . '.' . $ext;
            $themeTagPageName[] = PieCrustDefaults::TAG_PAGE_NAME . '.' . $ext;
        }
        $path = PathHelper::getUserOrThemePath($pieCrust, $tagPageName, $themeTagPageName);
        if ($path === false)
            return false;

        $matches = array();
        $flags = $pieCrust->getConfig()->getValueUnchecked('site/slugify_flags');
        $tagsPattern = UriBuilder::buildTagUriPattern($pieCrust->getConfig()->getValueUnchecked($blogKey.'/tag_url'));
        if (preg_match($tagsPattern, $uri, $matches))
        {
            $tags = explode('/', trim($matches['tag'], '/'));
            if (count($tags) > 1)
            {
                // Check the tags were specified in alphabetical order.
                //TODO: temporary check until I find a way to make it cheap to support all permutations in the baker.
                sort($tags);
                if (implode('/', $tags) != $matches['tag'])
                    throw new PieCrustException("Multi-tags must be specified in alphabetical order, sorry.");
                $tags = array_filter($tags, function($t) use ($flags) {
                    $t = rawurldecode($t);
                    return UriBuilder::slugify($t, $flags);
                });
            }
            else
            {
                $tags = rawurldecode($matches['tag']);
                $tags = UriBuilder::slugify($tags, $flags);
            }
            
            $pageInfo['type'] = IPage::TYPE_TAG;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['key'] = $tags;
            $pageInfo['path'] = $path;
            $pageInfo['was_path_checked'] = true;
            
            return true;
        }
        return false;
    }
    
    private static function tryParseCategoryUri(IPieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $blogKeyDir = '';
        if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
            $blogKeyDir = $blogKey . '/';
            
        $categoryPageName = array();
        $themeCategoryPageName = array();
        $autoFormats = $pieCrust->getConfig()->getValueUnchecked('site/auto_formats');
        foreach ($autoFormats as $ext => $format)
        {
            $categoryPageName[] = $blogKeyDir . PieCrustDefaults::CATEGORY_PAGE_NAME . '.' . $ext;
            $themeCategoryPageName[] = PieCrustDefaults::CATEGORY_PAGE_NAME . '.' . $ext;
        }
        $path = PathHelper::getUserOrThemePath($pieCrust, $categoryPageName, $themeCategoryPageName);
        if ($path === false)
            return false;

        $flags = $pieCrust->getConfig()->getValueUnchecked('site/slugify_flags');
        $categoryPattern = UriBuilder::buildCategoryUriPattern($pieCrust->getConfig()->getValueUnchecked($blogKey.'/category_url'));
        if (preg_match($categoryPattern, $uri, $matches))
        {
            $cat = rawurldecode($matches['cat']);
            $cat = UriBuilder::slugify($cat, $flags);
            $pageInfo['type'] = IPage::TYPE_CATEGORY;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['key'] = $cat;
            $pageInfo['path'] = $path;
            $pageInfo['was_path_checked'] = true;
            return true;
        }
        return false;
    }
}
