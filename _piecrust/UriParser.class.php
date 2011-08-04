<?php

require_once 'PieCrust.class.php';
require_once 'PieCrustException.class.php';
require_once 'Page.class.php';
require_once 'UriBuilder.class.php';


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
        if ($uri == '') $uri = PIECRUST_INDEX_PAGE_NAME;
        
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
                'type' => PIECRUST_PAGE_REGULAR,
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
        
        // Try with a post.
        if (UriParser::tryParsePostUri($pieCrust, $uri, $pageInfo))
        {
            return $pageInfo;
        }
        
        // Try with special pages (tag & category)
        if (UriParser::tryParseTagUri($pieCrust, $uri, $pageInfo))
        {
            return $pageInfo;
        }
        if (UriParser::tryParseCategoryUri($pieCrust, $uri, $pageInfo))
        {
            return $pageInfo;
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
        
        $path = $pieCrust->getPagesDir() . str_replace('/', DIRECTORY_SEPARATOR, $uriWithoutExtension) . '.html';
        if (is_file($path))
        {
            $pageInfo['path'] = $path;
            $pageInfo['was_path_checked'] = true;
            return true;
        }
        return false;
    }
    
    private static function tryParsePostUri(PieCrust $pieCrust, $uri, array &$pageInfo)
    {
        $matches = array();
        $postsPattern = UriBuilder::buildPostUriPattern($pieCrust->getConfigValueUnchecked('site', 'post_url'));
        if (preg_match($postsPattern, $uri, $matches))
        {
            $fs = FileSystem::create($pieCrust);
            $path = $fs->getPath($matches);
            $date = mktime(0, 0, 0, intval($matches['month']), intval($matches['day']), intval($matches['year']));
            
            $pageInfo['type'] = PIECRUST_PAGE_POST;
            $pageInfo['date'] = $date;
            $pageInfo['path'] = $path;
            return true;
        }
        return false;
    }
    
    private static function tryParseTagUri(PieCrust $pieCrust, $uri, array &$pageInfo)
    {
        $matches = array();
        $tagsPattern = UriBuilder::buildTagUriPattern($pieCrust->getConfigValueUnchecked('site', 'tag_url'));
        if (preg_match($tagsPattern, $uri, $matches))
        {
            $path = $pieCrust->getPagesDir() . PIECRUST_TAG_PAGE_NAME . '.html';
            
            $pageInfo['type'] = PIECRUST_PAGE_TAG;
            $pageInfo['key'] = $matches['tag'];
            $pageInfo['path'] = $path;
            return true;
        }
        return false;
    }
    
    private static function tryParseCategoryUri(PieCrust $pieCrust, $uri, array &$pageInfo)
    {
        $categoryPattern = UriBuilder::buildCategoryUriPattern($pieCrust->getConfigValueUnchecked('site', 'category_url'));
        if (preg_match($categoryPattern, $uri, $matches))
        {
            $path = $pieCrust->getPagesDir() . PIECRUST_CATEGORY_PAGE_NAME . '.html';
            
            $pageInfo['type'] = PIECRUST_PAGE_CATEGORY;
            $pageInfo['key'] = $matches['cat'];
            $pageInfo['path'] = $path;
            return true;
        }
        return false;
    }
}
