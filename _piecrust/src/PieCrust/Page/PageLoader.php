<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Data\DataBuilder;
use PieCrust\IO\Cache;
use PieCrust\Util\Configuration;
use PieCrust\Util\PageHelper;


/**
 * A class responsible for loading a PieCrust page.
 */
class PageLoader
{
    protected $page;
    protected $cache;
    
    protected $wasCached;
    /**
     * Gets whether the page's contents have been cached.
     */
    public function wasCached()
    {
        if ($this->wasCached === null)
        {
            $cacheTime = $this->getCacheTime();
            if ($cacheTime === false)
            {
                $this->wasCached = false;
            }
            else
            {
                $this->wasCached = ($cacheTime > filemtime($this->page->getPath()));
            }
        }
        return $this->wasCached;
    }
    
    protected $cacheTime;
    /**
     * Gets the cache time for the page, or false if it was not cached.
     */
    public function getCacheTime()
    {
        if ($this->cacheTime === null)
        {
            if ($this->cache == null)
            {
                $this->cacheTime = false;
            }
            else
            {
                $this->cacheTime = $this->cache->getCacheTime($this->page->getUri(), 'json');
            }
        }
        return $this->cacheTime;
    }
    
    /**
     * Creates a new instance of PageLoader.
     */
    public function __construct(IPage $page)
    {
        $this->page = $page;
        
        $this->cache = null;
        if ($page->getApp()->isCachingEnabled())
        {
            $this->cache = new Cache($page->getApp()->getCacheDir() . 'pages_r');
        }
        
        $this->pageData = null;
    }
    
    /**
     * Loads the page's configuration and contents.
     */
    public function load()
    {
        // Caching must be disabled if we get some extra page data because then this page
        // is not self-contained anymore.
        $enableCache = ($this->page->getExtraPageData() == null);
        
        if ($enableCache and $this->wasCached())
        {
            // Get the page from the cache.
            $configText = $this->cache->read($this->page->getUri(), 'json');
            $config = json_decode($configText, true);
            $this->page->getConfig()->set($config, false); // false = No need to validate this.
            if (!$this->page->getConfig()->hasValue('segments'))
                throw new PieCrustException("Error in page '".$this->page->getPath()."': Can't get segments list from cache.");
            
            $contents  = array('content' => null, 'content.abstract' => null);
            foreach ($config['segments'] as $key)
            {
                $contents[$key] = $this->cache->read($this->page->getUri(), $key . '.html');
            }
            
            return $contents;
        }
        else
        {
            // Load the page from disk and re-format it.
            $rawContents = file_get_contents($this->page->getPath());
            $parsedContents = Configuration::parseHeader($rawContents);
            
            // We need to set the configuration on the page right away because
            // most formatters, template engines, and other elements will need
            // access to it for rendering the contents.
            $config = $this->page->getConfig();
            $config->set($parsedContents['config']);
            
            $rawSegmentsOffset = $parsedContents['text_offset'];
            $rawSegments = $this->parseContentSegments($rawContents, $rawSegmentsOffset);
            
            $pieCrust = $this->page->getApp();
            $pageData = $this->page->getPageData();
            $siteData = DataBuilder::getSiteData($pieCrust);
            $appData = DataBuilder::getAppData($pieCrust, $siteData, $pageData, null, false);
            $data = Configuration::mergeArrays(
                $pageData,
                $siteData,
                $appData
            );
            $templateEngineName = $this->page->getConfig()->getValue('template_engine');
            $templateEngine = $pieCrust->getTemplateEngine($templateEngineName);
            if (!$templateEngine)
                throw new PieCrustException("Error in page '".$this->page->getPath()."': Unknown template engine '".$templateEngineName."'.");
            $contents = array('content' => null, 'content.abstract' => null);
            foreach ($rawSegments as $key => $content)
            {
                ob_start();
                try
                {
                    $templateEngine->renderString($content, $data);
                    $renderedContent = ob_get_clean();
                }
                catch (Exception $e)
                {
                    ob_end_clean();
                    throw new PieCrustException("Error in page '".$this->page->getPath()."': ".$e->getMessage(), 0, $e);
                }
                
                $config->appendValue('segments', $key);
                $renderedAndFormattedContent = $pieCrust->formatText($renderedContent, $config['format']);
                $contents[$key] = $renderedAndFormattedContent;
                if ($key == 'content')
                {
                    $matches = array();
                    if (preg_match('/^<!--\s*(more|(page)?break)\s*-->\s*$/m', $renderedAndFormattedContent, $matches, PREG_OFFSET_CAPTURE))
                    {
                        // Add a special content segment for the "intro/abstract" part of the article.
                        $offset = $matches[0][1];
                        $abstract = substr($renderedAndFormattedContent, 0, $offset);
                        $config->appendValue('segments', 'content.abstract');
                        $contents['content.abstract'] = $abstract;
                    }
                }
            }
            
            // Do not cache the page if 'volatile' data was accessed (e.g. the page displays
            // the latest posts).
            $wasVolatileDataAccessed = $data['pagination']->wasPaginationDataAccessed();
            if ($enableCache and $this->cache != null and !$wasVolatileDataAccessed)
            {
                $yamlMarkup = json_encode($config->get());
                $this->cache->write($this->page->getUri(), 'json', $yamlMarkup);
                
                $keys = $config['segments'];
                foreach ($keys as $key)
                {
                    $this->cache->write($this->page->getUri() . '.' . $key, 'html', $contents[$key]);
                }
            }
            
            return $contents;
        }
    }
    
    protected function parseContentSegments($rawContents, $offset)
    {
        $end = strlen($rawContents);
        $matches = array();
        $matchCount = preg_match_all('/^---(\w+)---\s*\n/m', $rawContents, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, $offset);
        if ($matchCount > 0)
        {
            $contents = array();
            
            if ($matches[0][0][1] > $offset)
            {
                // There's some default content at the beginning.
                $contents['content'] = substr($rawContents, $offset, $matches[0][0][1] - $offset);
            }
            
            for ($i = 0; $i < $matchCount; ++$i)
            {
                // Get each segment as the text that's between the end of the current captured string
                // and the beginning of the next captured string (or the end of the input text if
                // the current is the last capture).
                $matchStart = $matches[0][$i][1] + strlen($matches[0][$i][0]);
                $matchEnd = ($i < $matchCount - 1) ? $matches[0][$i+1][1] : $end;
                $segmentName = $matches[1][$i][0];
                $segmentContent = substr($rawContents, $matchStart, $matchEnd - $matchStart);
                $contents[$segmentName] = $segmentContent;
            }
            
            return $contents;
        }
        else
        {
            // No segments, just the content.
            return array('content' => substr($rawContents, $offset));
        }
    }
}
