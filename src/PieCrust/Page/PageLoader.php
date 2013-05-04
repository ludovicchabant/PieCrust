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
            throw new PieCrustException("Error loading page: {$relativePath}", 0, $e);
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
            throw new PieCrustException("Error formatting page: {$relativePath}", 0, $e);
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
                $segmentText = $this->cache->read($this->page->getUri() . '.' . $key, 'json');
                $contents[$key] = json_decode($segmentText);
                // The deserialized JSON object is not of type `ContentSegment` but will
                // have the same attributes so it should work all fine.

                // Sanity test: if the first content segment is null, it may mean that the
                // original page file was in a non-supported encoding.
                if (count($contents[$key]) > 0 && $contents[$key]->parts[0]->content === null)
                    throw new PieCrustException("Corrupted cache: is the page not saved in UTF-8 encoding?");
            }
            
            return $contents;
        }
        else
        {
            // Load the page from disk.
            $rawContents = file_get_contents($this->page->getPath());
            $rawContents = PageLoader::removeUnicodeBOM($rawContents);
            $header = Configuration::parseHeader($rawContents);

            // Set the format from the file extension.
            if (!isset($header->config['format']))
            {
                $app = $this->page->getApp();
                $autoFormats = $app->getConfig()->getValueUnchecked('site/auto_formats');
                $extension = pathinfo($this->page->getPath(), PATHINFO_EXTENSION);
                if (isset($autoFormats[$extension]))
                {
                    $format = $autoFormats[$extension];
                    if ($format)
                        $header->config['format'] = $autoFormats[$extension];
                }
            }

            // Set the configuration.
            $config = $this->page->getConfig();
            $config->set($header->config);
            
            // Set the raw content with the unparsed content segments.
            $contents = $this->parseContentSegments($rawContents, $header->textOffset);
            // Add the list of known segments to the configuration.
            foreach ($contents as $key => $segment)
            {
                $config->appendValue('segments', $key);
            }
            
            // Cache that shit out.
            if ($this->cache != null)
            {
                $cacheUri = $this->page->getUri();
                if ($cacheUri == '')
                    $cacheUri = '_index';

                $configText = json_encode($config->get());
                $this->cache->write($cacheUri, 'json', $configText);
                
                $keys = $config['segments'];
                foreach ($keys as $key)
                {
                    $segmentText = json_encode($contents[$key]);
                    $this->cache->write($cacheUri . '.' . $key, 'json', $segmentText);
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
                $contents[$segmentName] = new ContentSegment(
                    substr($rawContents, $offset, $matches[0][0][1] - $offset)
                );
            }
            
            for ($i = 0; $i < $matchCount; ++$i)
            {
                // Get each segment as the text that's between the end of the 
                // current captured string and the beginning of the next 
                // captured string (or the end of the input text if the current
                // is the last capture).
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

                // Create a new segment, or add that part to an existing one.
                if (!isset($contents[$segmentName]))
                {
                    $contents[$segmentName] = new ContentSegment();
                }
                $contents[$segmentName]->parts[] = new ContentSegmentPart(
                    $segmentContent,
                    $segmentFormat
                );
            }

            return $contents;
        }
        else
        {
            // No segments, just the content.
            return array('content' => new ContentSegment(
                substr($rawContents, $offset)
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
        foreach ($rawSegments as $key => $segment)
        {
            $contents[$key] = '';
            foreach ($segment->parts as $part)
            {
                ob_start();
                try
                {
                    $templateEngine->renderString($part->content, $data);
                    $renderedContent = ob_get_clean();
                }
                catch (Exception $e)
                {
                    ob_end_clean();
                    throw $e;
                }

                $format = $part->format;
                if(!$format)
                    $format = $this->page->getConfig()->getValue('format');
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

    /**
     * Remove Unicode "byte order mark" (BOM)...
     * @param string $data
     * @return string
     */
    protected function removeUnicodeBOM($data)
    {
        if (substr($data, 0, 3) == pack('CCC', 0xEF, 0xBB, 0xBF)) // UTF-8...
        {
            return substr($data, 3);
        }
        elseif (substr($data, 0, 2) == pack('CC', 0xFE, 0xFF)) // UTF-16 (BE)...
        {
            return substr($data, 2);
        }
        elseif (substr($data, 0, 2) == pack('CC', 0xFF, 0xFE)) // UTF-16 (LE)...
        {
            return substr($data, 2);
        }
        elseif (substr($data, 0, 4) == pack('CCCC', 0x00, 0x00, 0xFE, 0xFF)) // UTF-32 (BE)...
        {
            return substr($data, 4);
        }
        elseif (substr($data, 0, 4) == pack('CCCC', 0x00, 0x00, 0xFF, 0xFE)) // UTF-32 (LE)...
        {
            return substr($data, 4);
        }
        return $data;
    }
}
