<?php

namespace PieCrust\Util;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Environment\PageRepository;
use PieCrust\IO\FileSystem;


/**
 * A class that provides utility functions for a PieCrust app.
 */
class PieCrustHelper
{
    /**
     * Formats a given text using the registered page formatters.
     * 
     * Throws an exception if no 'exclusive' formatter was found.
     */
    public static function formatText(IPieCrust $pieCrust, $text, $format = null)
    {
        if (!$format)
            $format = $pieCrust->getConfig()->getValueUnchecked('site/default_format');
        
        $unformatted = true;
        $formattedText = $text;
        $gotExclusiveFormatter = false;
        foreach ($pieCrust->getPluginLoader()->getFormatters() as $formatter)
        {
            $isExclusive = $formatter->isExclusive();
            if ((!$isExclusive || $unformatted) && 
                $formatter->supportsFormat($format))
            {
                $formattedText = $formatter->format($formattedText);
                $unformatted = false;
                if ($isExclusive)
                    $gotExclusiveFormatter = true;
            }
        }
        if (!$gotExclusiveFormatter)
            throw new PieCrustException("Unknown page format: " . $format);
        return $formattedText;
    }
    
    /**
     * Gets the template engine associated with the given extension.
     */
    public static function getTemplateEngine(IPieCrust $pieCrust, $extension = 'html')
    {
        if ($extension == 'html' or $extension == null)
        {
            $extension = $pieCrust->getConfig()->getValueUnchecked('site/default_template_engine');
        }

        foreach ($pieCrust->getPluginLoader()->getTemplateEngines() as $templateEngine)
        {
            if ($templateEngine->getExtension() == $extension)
            {
                return $templateEngine;
            }
        }
        
        return null;
    }

    /**
     * Gets the page repository associated with the given app, or a new
     * one with caching disabled if none was found.
     */
    public static function getPageRepository(IPieCrust $pieCrust)
    {
        $pageRepository = $pieCrust->getEnvironment()->getPageRepository();
        if($pageRepository)
            return $pageRepository;
        return new PageRepository($pieCrust, false);
    }

    /**
     * Gets a formatted page URL.
     */
    public static function formatUri(IPieCrust $pieCrust, $uri)
    {
        $uriDecorators = $pieCrust->getEnvironment()->getUrlDecorators();
        $uriPrefix = $uriDecorators[0];
        $uriSuffix = $uriDecorators[1];

        if ($uriPrefix == null or $uriSuffix == null)
        {
            $isBaking = ($pieCrust->getConfig()->getValue('baker/is_baking') === true);
            $isPretty = ($pieCrust->getConfig()->getValueUnchecked('site/pretty_urls') === true);
            $uriPrefix = $pieCrust->getConfig()->getValueUnchecked('site/root') . (($isPretty or $isBaking) ? '' : '?/');
            $uriSuffix = ($isBaking and !$isPretty) ? '.html' : '';

            // Preserve the debug flag if needed.
            if ($pieCrust->isDebuggingEnabled() && !$isBaking)
            {
                if ($isPretty)
                    $uriSuffix .= '?!debug';
                else if (strpos($uriPrefix, '?') === false)
                    $uriSuffix .= '?!debug';
                else
                    $uriSuffix .= '&!debug';
            }

            // Cache the values in the app's environment.
            $pieCrust->getEnvironment()->setUrlDecorators($uriPrefix, $uriSuffix);
        }
        
        return $uriPrefix . $uri . $uriSuffix;
    }

    /**
     * Makes sure the website's posts infos are cached in the app's environment.
     */
    public static function ensurePostInfosCached(IPieCrust $pieCrust, $blogKey)
    {
        $postsInfos = $pieCrust->getEnvironment()->getCachedPostsInfos($blogKey);
        if ($postsInfos == null)
            return self::cachePostInfos($pieCrust, $blogKey);
        return $postsInfos;
    }

    /**
     * Cache the website's posts infos in the app's environment.
     */
    public static function cachePostInfos(IPieCrust $pieCrust, $blogKey)
    {
        $fs = FileSystem::create($pieCrust, $blogKey);
        $postInfos = $fs->getPostFiles();
        $pieCrust->getEnvironment()->setCachedPostsInfos($blogKey, $postInfos);
        return $postInfos;
    }
}

