<?php

namespace PieCrust\Util;

use PieCrust\PieCrustException;


/**
 * Helper class to get information from the hosting server.
 */
class ServerHelper
{
    /**
     * Gets the root URL of the current site, given server variables.
     */
    public static function getSiteRoot(array $server)
    {
        if (isset($server['HTTP_HOST']))
        {
            $host = ((isset($server['HTTPS']) and $server['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $server['HTTP_HOST'];
            $folder = rtrim(dirname($server['PHP_SELF']), '\\/') .'/';
            return $host . $folder;
        }
        else
        {
            return '/';
        }
    }

    /**
     * Gets the requested PieCrust URI based on given server variables.
     */
    public static function getRequestUri(array $server, $prettyUrls)
    {
        $requestUri = null;
        if (!$prettyUrls)
        {
            // Using standard query (no pretty URLs / URL rewriting)
            $requestUri = '/';
            $requestVars = array();
            parse_str($server['QUERY_STRING'], $requestVars);
            foreach ($requestVars as $key => $value)
            {
                if ($key[0] == '/' and $value == null)
                {
                    $requestUri = $key;
                    break;
                }
            }
        }
        else
        {
            // Get the requested URI via URL rewriting.
            if (isset($server['IIS_WasUrlRewritten']) &&
                $server['IIS_WasUrlRewritten'] == '1' &&
                isset($server['UNENCODED_URL']) &&
                $server['UNENCODED_URL'] != '')
            {
                // IIS7 rewriting module.
                $requestUri = $server['UNENCODED_URL'];
            }
            elseif (isset($server['REQUEST_URI']))
            {
                // Apache mod_rewrite.
                $requestUri = $server['REQUEST_URI'];
            }
            
            if ($requestUri != null)
            {
                // Clean up by removing the base URL of the application, and the trailing
                // query string that we should ignore because we're using 'pretty URLs'.
                if (isset($server['PHP_SELF']))
                {
                    $rootDirectory = rtrim(dirname($server['PHP_SELF']), '\\/') . '/';
                    if (strlen($rootDirectory) > 1)
                    {
                        if (strlen($requestUri) < strlen($rootDirectory))
                        {
                            throw new PieCrustException("You're trying to access a resource that's not within the directory served by PieCrust.");
                        }
                        $requestUri = substr($requestUri, strlen($rootDirectory) - 1);
                    }
                }
                $questionMark = strpos($requestUri, '?');
                if ($questionMark !== false)
                {
                    $requestUri = substr($requestUri, 0, $questionMark);
                }
                $requestUri = $requestUri;
            }
        }
        if ($requestUri == null)
        {
            throw new PieCrustException("PieCrust can't figure out the request URI. " .
                                        "It may be because you're running a non supported web server (PieCrust currently supports IIS 7.0+ and Apache), " .
                                        "or just because my code sucks.");
        }
        return $requestUri;
    }
}
