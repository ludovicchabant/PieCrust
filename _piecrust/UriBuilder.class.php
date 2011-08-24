<?php


/**
 * A utility class for building page URIs and URI patterns.
 */
class UriBuilder
{
    /**
     * Builds the URL of a post given a URL format.
     */
    public static function buildPostUri($postUrlFormat, $postInfo)
    {
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
