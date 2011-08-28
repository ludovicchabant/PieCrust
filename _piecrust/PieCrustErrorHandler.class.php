<?php

require_once 'PieCrust.class.php';
require_once 'UriParser.class.php';
require_once 'PieCrustException.class.php';


/**
 * A class that handles critical/fatal errors caught
 * while running a PieCrust application.
 */
class PieCrustErrorHandler
{
    protected $pieCrust;
    
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    /**
     * Handles an exception by showing an appropriate
     * error page.
     */
    public function handleError(Exception $e)
    {
        $displayErrors = ((bool)ini_get('display_errors') or $this->pieCrust->isDebuggingEnabled());
        
        // If debugging is enabled, just display the error and exit.
        if ($displayErrors)
        {
            $errorMessage = piecrust_format_errors(array($e), true);
            $this->showSystemMessage('error', $errorMessage);
            return;
        }
        
        // First of all, check that we're not running
        // some completely brand new and un-configured website.
        if ($this->isEmptySetup())
        {
            $this->showSystemMessage('welcome');
            return;
        }
        
        // Get the URI to the custom error page.
        $errorPageUri = '_error';
        if ($e->getMessage() == '404')
        {
            header('HTTP/1.0 404 Not Found');
            $errorPageUri = '_404';
        }
        try
        {
            $errorPageUriInfo = UriParser::parseUri($this->pieCrust, $errorPageUri);
        }
        catch (Exception $inner)
        {
            // What the fuck.
            $this->showSystemMessage('error', $inner->getMessage());
            return;
        }
        $errorMessage = "<p>We're very sorry but something wrong happened. We'll try to do better next time.</p>";
        if ($errorPageUriInfo != null and is_file($errorPageUriInfo['path']))
        {
            // We have a custom error page. Show it, or display
            // the "fatal error" page if even this doesn't work.
            try
            {
                $this->pieCrust->runUnsafe($errorPageUri);
            }
            catch (Exception $inner)
            {
                // Well there's really something wrong.
                $this->showSystemMessage('error', $errorMessage);
            }
        }
        else
        {
            // We don't have a custom error page. Just show a generic
            // error page and exit.
            $this->showSystemMessage(substr($errorPageUri, 1), $errorMessage);
        }
    }
    
    protected function isEmptySetup()
    {
        if (!is_dir($this->pieCrust->getRootDir() . PIECRUST_CONTENT_DIR))
            return true;
        if (!is_file($this->pieCrust->getRootDir() . PIECRUST_CONFIG_PATH))
            return true;
        
        return false;
    }
    
    protected function showSystemMessage($message, $details = null)
    {
        $contents = file_get_contents(PIECRUST_APP_DIR . 'messages/' . $message . '.html');
        if ($details != null)
        {
            $contents = str_replace('{{ details }}', $details, $contents);
        }
        echo $contents;
    }
}
