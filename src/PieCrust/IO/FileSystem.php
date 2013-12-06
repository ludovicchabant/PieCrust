<?php

namespace PieCrust\IO;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;


/**
 * Base class for a  PieCrust file-system that provides
 * the list of blog posts in descending date order, and
 * the list of pages.
 */
abstract class FileSystem
{
    // Path info request modes {{{
    const PATHINFO_PARSING = 0;
    const PATHINFO_CREATING = 1;
    // }}}
    
    protected $pieCrust;
    protected $htmlExtensions;

    /**
     * Initializes this file system with the given application.
     */
    public function initialize(IPieCrust $pieCrust)
    {
        $autoFormats = $pieCrust->getConfig()->getValueUnchecked('site/auto_formats');
        $htmlExtensions = array_keys($autoFormats);
        if (count($htmlExtensions) == 0)
            $htmlExtensions = array('html');
        $this->htmlExtensions = $htmlExtensions;
        $this->pieCrust = $pieCrust;
    }

    /**
     * Gets the name of the file system.
     */
    public abstract function getName();

    /**
     * Gets the info about all the page files in the website.
     *
     * This should return an array of `PageInfo` instances.
     */
    public abstract function getPageFiles();
    
    /**
     * Gets the info about all the post files in the website for the
     * given blog key.
     *
     * This should return an array of `PostInfo` instances.
     *
     * File infos are expected to be sorted in reverse chronological
     * order based on the day of the post.
     */
    public abstract function getPostFiles($blogKey);
    
    /**
     * Gets the complete info for a post file based on an incomplete
     * one (e.g. when the URL to a post doesn't contain all the
     * information to locate it on disk).
     */
    public function getPostPathInfo($blogKey, $captureGroups, $mode)
    {
        $needsRecapture = false;
        if (array_key_exists('year', $captureGroups))
        {
            $year = $captureGroups['year'];
        }
        else
        {
            $year = '????';
            $needsRecapture = true;
        }
        if (array_key_exists('month', $captureGroups))
        {
            $month = $captureGroups['month'];
        }
        else
        {
            $month = '??';
            $needsRecapture = true;
        }
        if (array_key_exists('day', $captureGroups))
        {
            $day = $captureGroups['day'];
        }
        else
        {
            $day = '??';
            $needsRecapture = true;
        }
        if (array_key_exists('ext', $captureGroups))
        {
            $ext = $captureGroups['ext'];
        }
        else
        {
            $extCount = count($this->htmlExtensions);
            if ($extCount <= 1)
            {
                $ext = 'html';
            }
            else
            {
                $ext = '*';
                $needsRecapture = true;
            }
        }
        $slug = $captureGroups['slug']; // 'slug' is required.
        
        $path = $this->getPostPathFormat($blogKey);
        $path = str_replace(
            array('%year%', '%month%', '%day%', '%slug%', '%ext%'),
            array($year, $month, $day, $slug, $ext),
            $path
        );
        
        $pathInfo = array(
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'slug' => $slug,
            'ext' => $ext,
            'path' => $path
        );
        if ($needsRecapture)
        {
            if ($mode == self::PATHINFO_CREATING)
                throw new PieCrustException("No enough information to provide a path info for creation.");

            // Not all path components were specified in the URL (e.g. because the
            // post URL format doesn't capture all of them).
            // We need to find a physical file that matches everything we have,
            // and fill in the blanks.
            $possiblePaths = glob($path, GLOB_NOSORT);
            // TODO: throw different exceptions if we find 0 or more than 1 file.
            if (count($possiblePaths) != 1)
                throw new PieCrustException('404');
            
            $pathInfo['path'] = $possiblePaths[0];
            
            $postPathFormat = str_replace('\\', '/', $this->getPostPathFormat($blogKey));
            $pathComponentsRegex = preg_quote($postPathFormat, '/');
            $pathComponentsRegex = str_replace(
                array('%year%', '%month%', '%day%', '%slug%', '%ext%'),
                array('(?P<year>\d{4})', '(?P<month>\d{2})', '(?P<day>\d{2})', '(?P<slug>.+)', '(?P<ext>\w+)'),
                $pathComponentsRegex
            );
            $pathComponentsRegex = '/' . $pathComponentsRegex . '/';
            $pathComponentsMatches = array();
            if (preg_match(
                $pathComponentsRegex,
                str_replace('\\', '/', $possiblePaths[0]),
                $pathComponentsMatches) !== 1)
                throw new PieCrustException("Can't extract path components from path: " . $possiblePaths[0]);
            
            if (isset($pathComponentsMatches['year']))
                $pathInfo['year'] = $pathComponentsMatches['year'];
            if (isset($pathComponentsMatches['month']))
                $pathInfo['month'] = $pathComponentsMatches['month'];
            if (isset($pathComponentsMatches['day']))
                $pathInfo['day'] = $pathComponentsMatches['day'];
            if (isset($pathComponentsMatches['slug']))
                $pathInfo['slug'] = $pathComponentsMatches['slug'];
            if (isset($pathComponentsMatches['ext']))
                $pathInfo['ext'] = $pathComponentsMatches['ext'];
        }
        return $pathInfo;
    }
    
    /**
     * Gets the posts path format.
     *
     * This should return a string like `/path/to/posts/%year%/%month%/%slug%.%ext%`.
     */
    public abstract function getPostPathFormat($blogKey);
}

