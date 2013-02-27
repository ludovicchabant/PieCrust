<?php

namespace PieCrust\Runner;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\UriParser;
use PieCrust\Util\HttpHeaderHelper;


/**
 * A class that handles critical/fatal errors caught
 * while running a PieCrust application.
 */
class PieCrustErrorHandler
{
    /**
     * Formats an exception, along with its inner exceptions, into a chunk of HTML.
     */
    public static function formatException($error, $debugInfo = false)
    {
        $errorMessage = "<h3>{$error->getMessage()}</h3>" . PHP_EOL;
        if ($debugInfo)
        {
            $cur = $error;
            $errorMessage .= "<ul>" . PHP_EOL;
            while ($cur != null)
            {
                $errorMessage .= "<li><h4>{$cur->getMessage()}</h4>" . PHP_EOL;
                $errorMessage .= "<p>Error: <code>{$cur->getCode()}</code><br/>" .
                    "File: <code>{$cur->getFile()}</code><br/>" .
                    "Line <code>{$cur->getLine()}</code><br/>" .
                    "Trace: <code><pre>{$cur->getTraceAsString()}</pre></code></p>";
                $cur = $cur->getPrevious();
                $errorMessage .= "</li>" . PHP_EOL;
            }
            $errorMessage .= "</ul>" . PHP_EOL;
        }
        else
        {
            $cur = $error->getPrevious();
            while ($cur != null)
            {
                $errorMessage .= "<h3>{$cur->getMessage()}</h3>" . PHP_EOL;
                $cur = $cur->getPrevious();
            }
        }
        return $errorMessage;
    }
    
    protected $pieCrust;
    
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    /**
     * Handles an exception by showing an appropriate
     * error page.
     */
    public function handleError(Exception $e, $server = null, array &$headers = null)
    {
        $isDebuggingEnabled = $this->pieCrust->isDebuggingEnabled();
        $displayErrors = $this->pieCrust->getConfig()->getValue('site/display_errors');
        
        // If debugging is enabled, just display the error and exit.
        if ($isDebuggingEnabled || $displayErrors)
        {
            if ($e->getMessage() == '404')
            {
                //TODO: set header?
                piecrust_show_system_message('404');
                return;
            }
            $errorMessage = self::formatException($e, $isDebuggingEnabled);
            piecrust_show_system_message('error', $errorMessage);
            return;
        }
        
        // Generic error message in case we don't have anything custom.
        $errorMessage = "<p>We're sorry but something very wrong happened, and we don't know what. ".
                        "We'll try to do better next time.</p>".PHP_EOL;
        
        // Get the URI to the custom error page, and the error code.
        if ($e->getMessage() == '404')
        {
            HttpHeaderHelper::setOrAddHeader(0, 404, $headers);
            $errorPageUri = '_404';
        }
        else
        {
            HttpHeaderHelper::setOrAddHeader(0, 500, $headers);
            $errorPageUri = '_error';
        }

        // Get the error page's info.
        try
        {
            $errorPageUriInfo = UriParser::parseUri($this->pieCrust, $errorPageUri, UriParser::PAGE_URI_REGULAR);
        }
        catch (Exception $inner)
        {
            // What the fuck.
            piecrust_show_system_message('critical', $errorMessage);
            return;
        }

        // Render the error page (either a custom one, or a generic one).
        if ($errorPageUriInfo != null and is_file($errorPageUriInfo['path']))
        {
            // We have a custom error page. Show it, or display
            // the "fatal error" page if even this doesn't work.
            try
            {
                $runner = new PieCrustRunner($this->pieCrust);
                $runner->runUnsafe($errorPageUri, $server, null, $headers);
            }
            catch (Exception $inner)
            {
                // Well there's really something wrong.
                piecrust_show_system_message('critical', $errorMessage);
            }
        }
        else
        {
            // We don't have a custom error page. Just show the generic
            // error page.
            piecrust_show_system_message(substr($errorPageUri, 1), $errorMessage);
        }
    }
}
