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
    public static function formatException($error, $printDetails = false)
    {
        $errorMessage = "<h3>{$error->getMessage()}</h3>" . PHP_EOL;
        if ($printDetails)
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
                $errorMessage .= "</li>";
            }
            $errorMessage .= "</ul>";
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
        $displayErrors = ($this->pieCrust->isDebuggingEnabled() ||
                          $this->pieCrust->getConfig()->getValue('site/display_errors'));
        
        // If debugging is enabled, just display the error and exit.
        if ($displayErrors)
        {
            if ($e->getMessage() == '404')
            {
                //TODO: set header?
                piecrust_show_system_message('404');
                return;
            }
            $errorMessage = self::formatException($e, true);
            piecrust_show_system_message('error', $errorMessage);
            return;
        }
        
        // First of all, check that we're not running
        // some completely brand new and un-configured website.
        if ($this->isEmptySetup())
        {
            piecrust_show_system_message('welcome');
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
            $errorPageUriInfo = UriParser::parseUri($this->pieCrust, $errorPageUri);
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
            $errorMessage = self::formatException($e, false);
            piecrust_show_system_message(substr($errorPageUri, 1), $errorMessage);
        }
    }
    
    protected function isEmptySetup()
    {
        if (!is_dir($this->pieCrust->getRootDir() . PieCrustDefaults::CONTENT_DIR))
            return true;
        if (!is_file($this->pieCrust->getRootDir() . PieCrustDefaults::CONFIG_PATH))
            return true;
        
        return false;
    }
}
