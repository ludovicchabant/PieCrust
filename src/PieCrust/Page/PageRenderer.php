<?php

namespace PieCrust\Page;

use \Exception;
use PieCrust\IPage;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Data\DataBuilder;
use PieCrust\Util\Configuration;
use PieCrust\Util\PieCrustHelper;


/**
 * This class is responsible for rendering the final page.
 */
class PageRenderer
{
    protected $page;
    /**
     * Gets the page this renderer is bound to.
     */
    public function getPage()
    {
        return $this->page;
    }
    
    /**
     * Creates a new instance of PageRenderer.
     */
    public function __construct(IPage $page)
    {
        $this->page = $page;
    }
    
    /**
     * Renders the given page and sends the result to the standard output.
     */
    public function render()
    {
        $pieCrust = $this->page->getApp();
        $pageConfig = $this->page->getConfig();

        // Set the page as the current context.
        $executionContext = $pieCrust->getEnvironment()->getExecutionContext(true);
        $executionContext->pushPage($this->page);
        
        // Get the template name.
        $templateNames = $this->page->getConfig()->getValue('layout');
        if ($templateNames == null or $templateNames == '' or $templateNames == 'none')
        {
            $templateNames = false;
        }
        else
        {
            $templateNames = explode(',', $templateNames);
            foreach ($templateNames as &$name)
            {
                if (!preg_match('/\.[a-zA-Z0-9]+$/', $name))
                {
                    $name .= '.html';
                }
            }
        }
        
        if ($templateNames !== false)
        {
            // Get the template engine and the page data.
            $extension = pathinfo($templateNames[0], PATHINFO_EXTENSION);
            $templateEngine = PieCrustHelper::getTemplateEngine($pieCrust, $extension);
            
            // Render the page.
            $data = DataBuilder::getTemplateRenderingData($this->page);
            $templateEngine->renderFile($templateNames, $data);
        }
        else
        {
            // No template... just output the 'content' segment.
            echo $this->page->getContentSegment();
        }

        // Restore the previous context.
        $executionContext->popPage();
    }
    
    public function get()
    {
        ob_start();
        try
        {
            $this->render();
            return ob_get_clean();
        }
        catch (Exception $e)
        {
            ob_end_clean();
            throw $e;
        }
    }
    
    public static function getHeaders($contentType, $server = null)
    {
        $mimeType = null;
        switch ($contentType)
        {
            case 'html':
                $mimeType = 'text/html';
                break;
            case 'xml':
                $mimeType = 'text/xml';
                break;
            case 'txt':
            case 'text':
            default:
                $mimeType = 'text/plain';
                break;
            case 'css':
                $mimeType = 'text/css';
                break;
            case 'xhtml':
                $mimeType = 'application/xhtml+xml';
                break;
            case 'atom':
                if ($server == null or strpos($server['HTTP_ACCEPT'], 'application/atom+xml') !== false)
                {
                    $mimeType = 'application/atom+xml';
                }
                else
                {
                    $mimeType = 'text/xml';
                }
                break;
            case 'rss':
                if ($server == null or strpos($server['HTTP_ACCEPT'], 'application/rss+xml') !== false)
                {
                    $mimeType = 'application/rss+xml';
                }
                else
                {
                    $mimeType = 'text/xml';
                }
                break;
            case 'json':
                $mimeType = 'application/json';
                break;
        }
        
        if ($mimeType != null)
        {
            return array('Content-type' => $mimeType. '; charset=utf-8');
        }
        return null;
    }
}

