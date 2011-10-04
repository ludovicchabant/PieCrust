<?php

namespace PieCrust\Util;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
use PieCrust\Page\Page;


/**
 * A utility class for parsing page URIs.
 */
class UriParser
{
    /**
     * Parse a relative URI and returns information about it.
     */
    public static function parseUri(PieCrust $pieCrust, $uri)
    {
        if (strpos($uri, '..') !== false)   // Some bad boy's trying to access files outside of our standard folders...
        {
            throw new PieCrustException('404');
        }
        
        $uri = trim($uri, '/');
        if ($uri == '') $uri = PieCrust::INDEX_PAGE_NAME;
        
        $pageNumber = 1;
        $matches = array();
        if (preg_match('/\/(\d+)\/?$/', $uri, $matches))
        {
            // Requesting a page other than the first for this article.
            $uri = substr($uri, 0, strlen($uri) - strlen($matches[0]));
            $pageNumber = intval($matches[1]);
        }
        
        $pageInfo = array(
                'uri' => $uri,
                'page' => $pageNumber,
                'type' => Page::TYPE_REGULAR,
                'blogKey' => null,
                'key' => null,
                'date' => null,
                'path' => null,
                'was_path_checked' => false
            );
        
        // Try first with a regular page path.
        if (UriParser::tryParsePageUri($pieCrust, $uri, $pageInfo))
        {
            return $pageInfo;
        }
        
        $blogKeys = $pieCrust->getConfigValueUnchecked('site', 'blogs');
        
        // Try with a post.
        foreach ($blogKeys as $blogKey)
        {
            if (UriParser::tryParsePostUri($pieCrust, $blogKey, $uri, $pageInfo))
            {
                return $pageInfo;
            }
        }
        
        // Try with special pages (tag & category)
        foreach ($blogKeys as $blogKey)
        {
            if (UriParser::tryParseTagUri($pieCrust, $blogKey, $uri, $pageInfo))
            {
                return $pageInfo;
            }
            if (UriParser::tryParseCategoryUri($pieCrust, $blogKey, $uri, $pageInfo))
            {
                return $pageInfo;
            }
        }
        
        // No idea what that URI is...
        return null;
    }
    
    private static function tryParsePageUri(PieCrust $pieCrust, $uri, array &$pageInfo)
    {
        $matches = array();
        $uriWithoutExtension = $uri;
        if (preg_match('/\.[a-zA-Z0-9]+$/', $uri, $matches))
        {
            // There's an extension specified. Strip it
            // (the extension is probably because the page has a `content_type` different than HTML, which means
            //  it would be baked into a static file with that extension).
            $uriWithoutExtension = substr($uri, 0, strlen($uri) - strlen($matches[0]));
        }
        
        $path = $pieCrust->getPagesDir() . $uriWithoutExtension . '.html';
        if (is_file($path))
        {
            $pageInfo['path'] = $path;
            $pageInfo['was_path_checked'] = true;
            return true;
        }
        return false;
    }
    
    private static function tryParsePostUri(PieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $matches = array();
        $postsPattern = UriBuilder::buildPostUriPattern($pieCrust->getConfigValueUnchecked($blogKey, 'post_url'));
        if (preg_match($postsPattern, $uri, $matches))
        {
            $fs = FileSystem::create($pieCrust, $blogKey);
            $path = $fs->getPath($matches);
            $date = mktime(0, 0, 0, intval($matches['month']), intval($matches['day']), intval($matches['year']));
            
            $pageInfo['type'] = Page::TYPE_POST;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['date'] = $date;
            $pageInfo['path'] = $path;
            return true;
        }
        return false;
    }
    
    private static function tryParseTagUri(PieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $matches = array();
        $tagsPattern = UriBuilder::buildTagUriPattern($pieCrust->getConfigValueUnchecked($blogKey, 'tag_url'));
        if (preg_match($tagsPattern, $uri, $matches))
        {
            $prefix = '';
            if ($blogKey != PieCrust::DEFAULT_BLOG_KEY)
                $prefix = $blogKey . '/';
            
            $path = $pieCrust->getPagesDir() . $prefix . PieCrust::TAG_PAGE_NAME . '.html';
            
            $tags = explode('/', trim($matches['tag'], '/'));
            if (count($tags) > 1)
            {
                // Check the tags were specified in alphabetical order.
                //TODO: temporary check until I find a way to make it cheap to support all permutations in the baker.
                sort($tags);
                if (implode('/', $tags) != $matches['tag'])
                    throw new PieCrustException("Multi-tags must be specified in alphabetical order, sorry.");
            }
            else
            {
                $tags = $matches['tag'];
            }
            
            $pageInfo['type'] = Page::TYPE_TAG;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['key'] = $tags;
            $pageInfo['path'] = $path;
            
            return true;
        }
        return false;
    }
    
    private static function tryParseCategoryUri(PieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $categoryPattern = UriBuilder::buildCategoryUriPattern($pieCrust->getConfigValueUnchecked($blogKey, 'category_url'));
        if (preg_match($categoryPattern, $uri, $matches))
        {
            $prefix = '';
            if ($blogKey != PieCrust::DEFAULT_BLOG_KEY)
                $prefix = $blogKey . '/';
            
            $path = $pieCrust->getPagesDir() . $prefix . PieCrust::CATEGORY_PAGE_NAME . '.html';
            
            $pageInfo['type'] = Page::TYPE_CATEGORY;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['key'] = $matches['cat'];
            $pageInfo['path'] = $path;
            return true;
        }
        return false;
    }
}
