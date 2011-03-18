<?php

class PageRenderer
{
    protected $pieCrust;
    
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function render(Page $page, $extraData = null)
    {
        $pageConfig = $page->getConfig();
        
        // Get the template name.
        $templateName = $pageConfig['layout'];
        if ($templateName == null or $templateName == '' or $templateName == 'none')
        {
            $templateName = false;
        }
        else
        {
            if (!preg_match('/\.[a-zA-Z0-9]+$/', $templateName))
            {
                $templateName .= '.html';
            }
        }
        
        if ($templateName !== false)
        {
            // Get the template engine and the page data.
            $extension = pathinfo($templateName, PATHINFO_EXTENSION);
            $templateEngine = $this->pieCrust->getTemplateEngine($extension);
            $data = $page->getContentSegments();
            $data = array_merge($this->pieCrust->getSiteData(), $page->getPageData(), $data);
            if ($extraData != null)
            {
                if (is_array($extraData))
                {
                    $data = array_merge($data, $extraData);
                }
                else
                {
                    $data['extra'] = $extraData;
                }
            }
            
            // Render the page.
            $templateEngine->renderFile($templateName, $data);
        }
        else
        {
            echo $page->getContentSegment();
        }
        
        if ($this->pieCrust->isDebuggingEnabled())
        {
            // Add a footer with version, caching and timing information.
            $this->renderStatsFooter($page);
        }
    }
    
    public function get(Page $page, $extraData = null)
    {
        ob_start();
        $this->render($page, $extraData);
        return ob_get_clean();
    }
    
    public function renderStatsFooter(Page $page)
    {
        global $PIECRUST_START_TIME;
        $timeSpan = microtime(true) - $PIECRUST_START_TIME;
        echo "<!-- PieCrust " . PieCrust::VERSION . " - " .
             ($page->isCached() ? "baked this morning" : "baked just now") .
             ", in " . $timeSpan * 1000 . " milliseconds. -->";
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

