<?php

namespace PieCrust;


/**
 * A class containing site data to be passed to a page.
 */
class PieCrustSiteData
{
    const DEBUG_INFO_CSS = 'padding: 1em; background: #a42; color: #fff; position: fixed; width: 50%; bottom: 0; right: 0;';
    const P_CSS = 'margin: 0; padding: 0';
    
    protected $pieCrust;
    protected $wasCurrentPageCached;
    
    public $version;
    public $url;
    public $branding;
    
    public function __construct(PieCrust $pieCrust, $wasCurrentPageCached)
    {
        $this->pieCrust = $pieCrust;
        $this->wasCurrentPageCached = $wasCurrentPageCached;
        
        $this->version = PieCrust::VERSION;
        $this->url = 'http://bolt80.com/piecrust/';
        $this->branding = 'Baked with <em><a href="'. $this->url . '">PieCrust</a> ' . $this->version . '</em>.';
    }
    
    public function debug_info()
    {
        if (!$this->pieCrust->isDebuggingEnabled())
            return '';
        
        $output = '<div id="piecrust-debug-info" style="' . self::DEBUG_INFO_CSS . '">';
        $output .= '<p style="' . self::P_CSS . '"><strong>PieCrust ' . PieCrust::VERSION . '</strong> &mdash; ';
        if ($this->wasCurrentPageCached !== null)
        {
            $output .= ($this->wasCurrentPageCached ? "baked this morning" : "baked just now");
        }
        
        $runInfo = $this->pieCrust->getLastRunInfo();
        if ($runInfo['cache_validity'] != null)
        {
            $wasCacheCleaned = $runInfo['cache_validity']['was_cleaned'];
            $output .= ", from a " . ($wasCacheCleaned ? "brand new" : "valid") . " cache";
        }
        else
        {
            $output .= ", with no cache";
        }
        $timeSpan = microtime(true) - $runInfo['start_time'];
        $output .= ", in " . sprintf('%8.1f', $timeSpan * 1000.0) . " ms.";
        $output .= "</p>";
        $output .= "</div>";
        return $output;
    }
}
