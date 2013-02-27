<?php

namespace PieCrust\Data;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\Configuration;


/**
 * A class containing PieCrust app's data to be passed to a page.
 */
class PieCrustData
{
    protected $pieCrust;
    protected $siteData;
    protected $pageData;
    protected $pageContentSegments;
    protected $wasCurrentPageCached;
    
    public $version;
    public $url;
    public $branding;
    
    public function __construct(IPieCrust $pieCrust, $siteData, $pageData, $pageContentSegments, $wasCurrentPageCached)
    {
        $this->pieCrust = $pieCrust;
        $this->siteData = $siteData;
        $this->pageData = $pageData;
        $this->pageContentSegments = $pageContentSegments;
        $this->wasCurrentPageCached = $wasCurrentPageCached;
        
        $this->version = PieCrustDefaults::VERSION;
        $this->url = 'http://bolt80.com/piecrust/';
        $this->branding = 'Baked with <em><a href="'. $this->url . '">PieCrust</a> ' . $this->version . '</em>.';
    }
    
    public function debug_info()
    {
        if (!$this->pieCrust->isDebuggingEnabled() or
            !$this->pieCrust->getConfig()->getValue('site/enable_debug_info'))
            return '';
        
        $output = '<div id="piecrust-debug-info" style="' . DataStyles::CSS_DEBUGINFO . '">' . PHP_EOL;
        
        $output .= '<div id="piecrust-cache-info">' . PHP_EOL;
        $output .= '<p style="' . DataStyles::CSS_P . '"><strong>PieCrust ' . PieCrustDefaults::VERSION . '</strong> &mdash; ' . PHP_EOL;
        if ($this->wasCurrentPageCached !== null)
        {
            $output .= ($this->wasCurrentPageCached ? "baked this morning" : "baked just now");
        }
        
        // If we have some execution info in the environment, 
        // add more information.
        if ($this->pieCrust->getEnvironment()->getExecutionContext() != null)
        {
            $executionContext = $this->pieCrust->getEnvironment()->getExecutionContext();
            if ($this->pieCrust->isCachingEnabled())
            {
                $output .= ", from a " . ($executionContext->wasCacheCleaned ? "brand new" : "valid") . " cache";
            }
            else
            {
                $output .= ", with no cache";
            }
            $timeSpan = microtime(true) - $executionContext->startTime;
            $output .= ", in " . sprintf('%8.1f', $timeSpan * 1000.0) . " ms";
        }

        $output .= '</p>' . PHP_EOL;
        $output .= '</div>' . PHP_EOL;
        
        if ($this->pageData or $this->pageContentSegments)
        {
            $output .= '<div id="piecrust-data-info">' . PHP_EOL;
            $output .= '<p style="' . DataStyles::CSS_P . ' cursor: pointer;" onclick="var l = document.getElementById(\'piecrust-data-list\'); if (l.style.display == \'none\') l.style.display = \'block\'; else l.style.display = \'none\';">';
            $output .= "<span style=\"" . DataStyles::CSS_BIGHEADER . "\">Template engine data</span> &mdash; click to toggle</a>.</p>" . PHP_EOL;
            
            $data = array(
                'Website data' => $this->siteData,
                'Page data' => $this->pageData,
                'Page contents' => $this->pageContentSegments,
                'PieCrust data' => array(
                    'piecrust' => array(
                        'version' => $this->version,
                        'url' => $this->url,
                        'branding' => $this->branding,
                        'debug_info' => "This very thing you're looking at!"
                    )
                )
            );
            $output .= '<div id="piecrust-data-list" style="display: none;">' . PHP_EOL;
            $output .= '<p style="' . DataStyles::CSS_DOC . '">The following key/value pairs are available in the layout\'s markup, ' .
                       'and most is available in the page\'s markup.</p>' . PHP_EOL;
            foreach ($data as $desc => $d)
            {
                $output .= '<div>' . PHP_EOL;
                $output .= '<p style="' . DataStyles::CSS_HEADER . '">&raquo; ' . $desc . '</p>' . PHP_EOL;
                $output .= '<div style="' . DataStyles::CSS_DATA . '">' . PHP_EOL;
                ob_start();
                try
                {
                    $formatter = new DataFormatter();
                    $formatter->format($d);
                }
                catch (Exception $e)
                {
                    ob_end_clean();
                    throw new PieCrustException("Error while generating the debug data.", 0, $e);
                }
                $output .= ob_get_clean();
                $output .= '</div>' .PHP_EOL;
                $output .= '</div>' . PHP_EOL;
            }
            $output .= '</div>' . PHP_EOL;
            $output .= '</div>' . PHP_EOL;
        }
        
        $output .= '</div>';
        return $output;
    }
}
