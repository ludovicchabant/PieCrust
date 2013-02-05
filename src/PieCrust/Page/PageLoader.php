<?php

namespace PieCrust\Page;

use \Exception;
use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Data\DataBuilder;
use PieCrust\IO\Cache;
use PieCrust\Util\Configuration;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


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
        try
        {
            return $this->loadUnsafe();
        }
        catch (Exception $e)
        {
            $relativePath = PageHelper::getRelativePath($this->page);
            throw new PieCrustException("Error loading page '{$relativePath}': {$e->getMessage()}", 0, $e);
        }
    }

    public function formatContents(array $rawSegments)
    {
        try
        {
            return $this->formatContentsUnsafe($rawSegments);
        }
        catch (Exception $e)
        {
            $relativePath = PageHelper::getRelativePath($this->page);
            throw new PieCrustException("Error formatting page '{$relativePath}': {$e->getMessage()}", 0, $e);
        }
    }

    protected function loadUnsafe()
    {
        if ($this->wasCached())
        {
            // Get the page from the cache.
            $configText = $this->cache->read($this->page->getUri(), 'json');
            $config = json_decode($configText, true);
            $this->page->getConfig()->set($config, false); // false = No need to validate this.
            if (!$this->page->getConfig()->hasValue('segments'))
                throw new PieCrustException("Can't get segments list from cache.");
            
            $contents  = array();
            foreach ($config['segments'] as $key)
            {
                $contents[$key] = json_decode($this->cache->read($this->page->getUri(), $key . '.json'),true);
            }
            
            return $contents;
        }
        else
        {
            // Load the page from disk.
            $rawContents = file_get_contents($this->page->getPath());
            $parsedContents = Configuration::parseHeader($rawContents);
            
            // Set the configuration.
            $config = $this->page->getConfig();
            $config->set($parsedContents['config']);
            
            // Set the raw content with the unparsed content segments.
            $rawSegmentsOffset = $parsedContents['text_offset'];
            $contents = $this->parseContentSegments($rawContents, $rawSegmentsOffset);
            // Add the list of known segments to the configuration.
            foreach ($contents as $key => $segment)
            {
                $config->appendValue('segments', $key);
            }
            
            // Cache that shit out.
            if ($this->cache != null)
            {
                $yamlMarkup = json_encode($config->get());
                $this->cache->write($this->page->getUri(), 'json', $yamlMarkup);
                
                $keys = $config['segments'];
                foreach ($keys as $key)
                {
                    $this->cache->write($this->page->getUri() . '.' . $key, 'json', json_encode($contents[$key]));
                }
            }
            
            return $contents;
        }
    }
    
    protected function parseContentSegments($rawContents, $offset)
    {
        $end = strlen($rawContents);
        $matches = array();
        $matchCount = preg_match_all(
            '/^(?:---\s*(\w+)(?:\:(\w+))?\s*---|<--\s*(\w+)\s*-->)\s*\n/m', 
            $rawContents, 
            $matches, 
            PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, 
            $offset
        );
        $segmentName = 'content';
        $segmentFormat = null;
        if ($matchCount > 0)
        {
            $contents = array();
            
            if ($matches[0][0][1] > $offset)
            {
                // There's some default content at the beginning.
                $contents[$segmentName] = array(
                    array(
                        'content' => substr($rawContents, $offset, $matches[0][0][1] - $offset),
                        'format' => $segmentFormat
                    )
                );
            }
            
            for ($i = 0; $i < $matchCount; ++$i)
            {
                // Get each segment as the text that's between the end of the current captured string
                // and the beginning of the next captured string (or the end of the input text if
                // the current is the last capture).
                $matchStart = $matches[0][$i][1] + strlen($matches[0][$i][0]);
                $matchEnd = ($i < $matchCount - 1) ? $matches[0][$i+1][1] : $end;
                if (!empty($matches[1][$i][0]))
                {
                    $segmentName = $matches[1][$i][0];
                    $segmentFormat = empty($matches[2][$i]) ? null : $matches[2][$i][0];
                }
                elseif (!empty($matches[3][$i][0]))
                {
                    $segmentFormat = $matches[3][$i][0];
                }
                $segmentContent = substr($rawContents, $matchStart, $matchEnd - $matchStart);
                if (empty($contents[$segmentName]))
                    $contents[$segmentName] = array();
                $contents[$segmentName][] = array(
                    'content' => $segmentContent,
                    'format' => $segmentFormat
                );
            }
            return $contents;
        }
        else
        {
            // No segments, just the content.
            return array('content' => array(
                array(
                    'content' => substr($rawContents, $offset),
                    'format' => null
                )
            ));
        }
    }

    protected function formatContentsUnsafe(array $rawSegments)
    {
        $pieCrust = $this->page->getApp();

        // Set the page as the current context.
        $executionContext = $pieCrust->getEnvironment()->getExecutionContext(true);
        $executionContext->pushPage($this->page);

        // Get the data and the template engine.
        $data = DataBuilder::getPageRenderingData($this->page);

        $templateEngineName = $this->page->getConfig()->getValue('template_engine');
        $templateEngine = PieCrustHelper::getTemplateEngine($pieCrust, $templateEngineName);
        if (!$templateEngine)
            throw new PieCrustException("Unknown template engine '{$templateEngineName}'.");

        // Render each text segment.
        $contents = array();
        foreach ($rawSegments as $key => $pieces)
        {
            $contents[$key] = '';
            foreach ($pieces as $piece)
            {
                $content = $piece['content'];
                $format = $piece['format'];
                ob_start();
                try
                {
                    $templateEngine->renderString($content, $data);
                    $renderedContent = ob_get_clean();
                }
                catch (Exception $e)
                {
                    ob_end_clean();
                    throw $e;
                }

                if(!$format) $format = $this->page->getConfig()->getValue('format');
                $renderedAndFormattedContent = PieCrustHelper::formatText(
                    $pieCrust, 
                    $renderedContent, 
                    $format
                );
                $contents[$key] .= $renderedAndFormattedContent;
            }
        }
        if (!empty($contents['content']))
        {
            $matches = array();
            if (preg_match(
                '/^<!--\s*(more|(page)?break)\s*-->\s*$/m', 
                $contents['content'], 
                $matches, 
                PREG_OFFSET_CAPTURE
            ))
            {
                // Add a special content segment for the "intro/abstract" part 
                // of the article.
                $offset = $matches[0][1];
                $abstract = substr($contents['content'], 0, $offset);
                $this->page->getConfig()->appendValue('segments', 'content.abstract');
                $contents['content.abstract'] = $abstract;
            }
        }

        // Restore the previous context.
        $executionContext->popPage();

        return $contents;
    }
}
