<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;
use PieCrust\Util\UriBuilder;


/**
 * A class that provides utility functions for a PieCrust app.
 */
class PieCrustHelper
{
    /**
     * Returns a path relative to a site's root directory.
     */
    public static function getRelativePath(IPieCrust $pieCrust, $path, $stripExtension = false)
    {
        $basePath = null;
        $themeDir = $pieCrust->getThemeDir();
        if ($themeDir and strncmp($path, $themeDir, strlen($themeDir)) == 0)
        {
            // Theme path.
            $basePath = $themeDir;
        }
        else
        {
            // Normal website path.
            $basePath = $pieCrust->getRootDir();
        }
        if (!$basePath)
            throw new PieCrustException("Can't get a relative path for '$path': it doesn't seem to be either a website, theme or resource path.");

        $relativePath = PathHelper::getRelativePath($basePath, $path);
        if ($stripExtension)
            $relativePath = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
        return $relativePath;
    }
    
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
     * Gets the default blog key. This is either the blog key for the current
     * page (if there's a current request running), or the first declared blog.
     */
    public static function getDefaultBlogKey(IPieCrust $pieCrust)
    {
        $executionContext = $pieCrust->getEnvironment()->getExecutionContext();
        if ($executionContext != null)
        {
            $page = $executionContext->getPage();
            if ($page != null)
            {
                $blogKey = $page->getConfig()->getValueUnchecked('blog');
                if ($blogKey != null)
                    return $blogKey;
            }
        }

        $blogKeys = $pieCrust->getConfig()->getValueUnchecked('site/blogs');
        return $blogKeys[0];
    }

    /**
     * Gets a formatted page URI.
     */
    public static function formatUri(IPieCrust $pieCrust, $uri)
    {
        if (strlen($uri) > 0 and
            ($uri[0] == '/' or preg_match(',[a-zA-Z]+://,', $uri)))
        {
            // Don't do anything if the URI is already absolute.
            return $uri;
        }

        // Get the URI format for the current app. There's a couple weird ones
        // that should be used only if the URI to format doesn't have an extension
        // specified.
        $uriFormat = $pieCrust->getEnvironment()->getUriFormat();
        $tokens = array(
            '%root%',
            '%slug%',
            '%slash_if_no_ext%',
            '%html_if_no_ext%'
        );
        $replacements = array(
            $pieCrust->getConfig()->getValueUnchecked('site/root'),
            $uri,
            '/',
            '.html'
        );

        // Adjust the replacement bits if the given URI has an extension.
        $hasExtensionOrIsRoot = (($uri == '') or (pathinfo($uri, PATHINFO_EXTENSION) != null));
        if ($hasExtensionOrIsRoot)
        {
            $replacements[2] = '';
            $replacements[3] = '';
        }

        $formattedUri = str_replace($tokens, $replacements, $uriFormat);
        return $formattedUri;
    }
}

