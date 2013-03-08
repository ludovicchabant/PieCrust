<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;


/**
 * A utility class for building page URIs and URI patterns.
 */
class UriBuilder
{
    /**
     * Gets the URI of a page given a relative path.
     */
    public static function buildUri(IPieCrust $pieCrust, $relativePath)
    {
        $pregQuoteFunc = function($value) { return preg_quote($value, '/'); };
        $autoFormats = $pieCrust->getConfig()->getValueUnchecked('site/auto_formats');
        $stripExtensions = array_map($pregQuoteFunc, array_keys($autoFormats));
        $stripPattern = "/\\.(" . implode('|', $stripExtensions) . ")$/";

        $uri = str_replace('\\', '/', $relativePath);
        $uri = preg_replace($stripPattern, "", $uri);

        if ($uri == '_index')
            $uri = '';

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
        if (is_array($tag))
            $tag = implode('/', $tag);
        $tag = self::slugify($tag);
        return str_replace('%tag%', $tag, $tagUrlFormat);
    }
    
    /**
     * Builds the regex pattern to match the given URL format.
     */
    public static function buildTagUriPattern($tagUrlFormat)
    {
        return '/^' . str_replace('%tag%', '(?P<tag>[\w\-\.]+(\/[\w\-\.]+)*)', preg_quote($tagUrlFormat, '/')) . '\/?$/';
    }
    
    /**
     * Builds the URL of a category listing.
     */
    public static function buildCategoryUri($categoryUrlFormat, $category)
    {
        $category = self::slugify($category);
        return str_replace('%category%', $category, $categoryUrlFormat);
    }
    
    /**
     * Builds the regex pattern to match the given URL format.
     */
    public static function buildCategoryUriPattern($categoryUrlFormat)
    {
        return '/^' . str_replace('%category%', '(?P<cat>[\w\-\.]+)', preg_quote($categoryUrlFormat, '/')) . '\/?$/';
    }

    /**
     * Transform a string into something that can be used for an URL.
     * TODO: replace other character, remove diacritics, etc.
     */
    public static function slugify($value)
    {
        return preg_replace('/\s+/', '-', $value);
    }
}
