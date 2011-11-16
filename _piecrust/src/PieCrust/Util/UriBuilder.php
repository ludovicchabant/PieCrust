<?php

namespace PieCrust\Util;

use PieCrust\IPage;


/**
 * A utility class for building page URIs and URI patterns.
 */
class UriBuilder
{
    /**
     * Gets the URI of a page given a path.
     */
    public static function buildUri($path, $makePathRelativeTo = null, $stripIndex = true)
    {
        if ($makePathRelativeTo != null)
        {
            $basePath = $makePathRelativeTo;
            if (is_int($makePathRelativeTo))
            {
                switch ($makePathRelativeTo)
                {
                    case IPage::TYPE_REGULAR:
                    case IPage::TYPE_CATEGORY:
                    case IPage::TYPE_TAG:
                        $basePath = $this->pieCrust->getPagesDir();
                        break;
                    case IPage::TYPE_POST:
                        $basePath = $this->pieCrust->getPostsDir();
                        break;
                    default:
                        throw new InvalidArgumentException("Unknown page type given: " . $makePathRelativeTo);
                }
            }
            $path = str_replace('\\', '/', substr($path, strlen($baseDir)));
        }
        $uri = preg_replace('/\.[a-zA-Z0-9]+$/', '', $path);    // strip the extension
        if ($stripIndex) $uri = str_replace('_index', '', $uri);// strip special name
        return $uri;
    }
    
    /**
     * Builds the URL of a post given a URL format.
     */
    public static function buildPostUri($postUrlFormat, $postInfo)
    {
        if (is_int($postInfo['month']))
        {
            $postInfo['month'] = sprintf('%02s', $postInfo['month']);
        }
        if (is_int($postInfo['day']))
        {
            $postInfo['day'] = sprintf('%02s', $postInfo['day']);
        }
        
        $replacements = array(
            '%year%' => $postInfo['year'],
            '%month%' => $postInfo['month'],
            '%day%' => $postInfo['day'],
            '%slug%' => $postInfo['name']
        );
        return str_replace(array_keys($replacements), array_values($replacements), $postUrlFormat);
    }
    
    /**
     * Builds the regex pattern to match the given URL format.
     */
    public static function buildPostUriPattern($postUrlFormat)
    {
        static $replacements = array(
            '%year%' => '(?P<year>\d{4})',
            '%month%' => '(?P<month>\d{2})',
            '%day%' => '(?P<day>\d{2})',
            '%slug%' => '(?P<slug>.*)'
        );
        return '/^' . str_replace(array_keys($replacements), array_values($replacements), preg_quote($postUrlFormat, '/')) . '\/?$/';
    }
    
    /**
     * Builds the URL of a tag listing.
     */
    public static function buildTagUri($tagUrlFormat, $tag)
    {
        if (is_array($tag)) $tag = implode('/', $tag);
        return str_replace('%tag%', $tag, $tagUrlFormat);
    }
    
    /**
     * Builds the regex pattern to match the given URL format.
     */
    public static function buildTagUriPattern($tagUrlFormat)
    {
        return '/^' . str_replace('%tag%', '(?P<tag>[\w\-_]+(\/[\w\-_]+)*)', preg_quote($tagUrlFormat, '/')) . '\/?$/';
    }
    
    /**
     * Builds the URL of a category listing.
     */
    public static function buildCategoryUri($categoryUrlFormat, $category)
    {
        return str_replace('%category%', $category, $categoryUrlFormat);
    }
    
    /**
     * Builds the regex pattern to match the given URL format.
     */
    public static function buildCategoryUriPattern($categoryUrlFormat)
    {
        return '/^' . str_replace('%category%', '(?P<cat>[\w\-]+)', preg_quote($categoryUrlFormat, '/')) . '\/?$/';
    }
}
