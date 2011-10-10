<?php

namespace PieCrust\Baker\Processors;

use \XMLWriter;
use PieCrust\PieCrustException;
use PieCrust\Util\UriParser;

require_once 'sfYaml/lib/sfYamlParser.php';


class SitemapProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('sitemap', 'sitemap', 'xml');
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $yamlParser = new \sfYamlParser();
        $sitemap = $yamlParser->parse(file_get_contents($inputPath));
        if (!isset($sitemap['locations']))
            throw new PieCrustException("No locations were defined in the sitemap.");
        
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'utf-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($sitemap['locations'] as $loc)
        {
            $xml->startElement('url');
            {
                // loc
                $locUrl = $this->pieCrust->getConfigValueUnchecked('site', 'root') . ltrim($loc['url'], '/');
                $xml->writeElement('loc', $locUrl);
                
                // lastmod
                $locLastMod = null;
                if (isset($loc['lastmod']))
                {
                    $locLastMod = $loc['lastmod'];
                }
                else if (isset($loc['lastmod_path']))
                {
                    $fullPath = $this->pieCrust->getRootDir() . ltrim($loc['lastmod_path'], '/\\');
                    $locLastMod = date('c', filemtime($fullPath));
                }
                else
                {
                    $urlInfo = UriParser::parseUri($this->pieCrust, $loc['url']);
                    if ($urlInfo)
                    {
                        if (is_file($urlInfo['path']))
                        {
                            $locLastMod = date('c', filemtime($urlInfo['path']));
                        }
                    }
                }
                if (!$locLastMod)
                {
                    throw new PieCrustException("No idea what '".$loc['url']."' is. Please specify a 'lastmod' time, or 'lastmod_path' path.");
                }
                $xml->writeElement('lastmod', $locLastMod);
                
                // changefreq
                if (isset($loc['changefreq']))
                {
                    $xml->writeAttribute('changefreq', $loc['changefreq']);
                }
                
                // priority
                if (isset($loc['priority']))
                {
                    $xml->writeAttribute('priority', $loc['priority']);
                }
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();
        $markup  = $xml->outputMemory(true);
        file_put_contents($outputPath, $markup);
    }
}