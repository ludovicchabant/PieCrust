<?php

namespace PieCrust\Util;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


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
     * Gets the repository handler associated with the given source.
     */
    public static function getRepository(IPieCrust $pieCrust, $source, $throwIfNotFound = true)
    {
        $repositories = $pieCrust->getPluginLoader()->getRepositories();
        foreach ($repositories as $repo)
        {
            if ($repo->supportsSource($source))
            {
                return $repo;
            }
        }
        if ($throwIfNotFound)
            throw new PieCrustException("Can't find a repository handler for source: {$source}");
        return null;
    }

    /**
     * Gets the default blog key.
     */
    public static function getDefaultBlogKey(IPieCrust $pieCrust)
    {
        $blogKeys = $pieCrust->getConfig()->getValueUnchecked('site/blogs');
        return $blogKeys[0];
    }

    /**
     * Gets all the blog keys.
     */
    public static function getBlogKeys(IPieCrust $pieCrust)
    {
        return $pieCrust->getConfig()->getValueUnchecked('site/blogs');
    }

    /**
     * Gets a formatted page URI.
     */
    public static function formatUri(IPieCrust $pieCrust, $uri)
    {
        // We only add a `.html` extension to the URI if we're baking without
        // 'pretty URLs', if the given URI doesn't have an extension already,
        // and if this is not the site's root.
        $extension = '';
        if ($uri != '')
        {
            $isPretty = ($pieCrust->getConfig()->getValueUnchecked('site/pretty_urls') === true);
            if (!$isPretty)
            {
                $isBaking = ($pieCrust->getConfig()->getValue('baker/is_baking') === true);
                if ($isBaking)
                {
                    $hasExtension = pathinfo($uri, PATHINFO_EXTENSION) != null;
                    if (!$hasExtension)
                    {
                        $extension = '.html';
                    }
                }
            }
        }

        $uriDecorators = $pieCrust->getEnvironment()->getUriDecorators();
        $uriPrefix = $uriDecorators[0];
        $uriSuffix = str_replace('%extension%', $extension, $uriDecorators[1]);
        return $uriPrefix . $uri . $uriSuffix;
    }
}

