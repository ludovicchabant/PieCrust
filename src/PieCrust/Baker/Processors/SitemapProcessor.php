<?php

namespace PieCrust\Baker\Processors;

use Symfony\Component\Yaml\Yaml;
use PieCrust\PieCrustException;
use PieCrust\Util\UriParser;


class SitemapProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('sitemap', 'sitemap', 'xml');
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $sitemap = Yaml::parse(file_get_contents($inputPath));
        if (!isset($sitemap['locations']))
            throw new PieCrustException("No locations were defined in the sitemap.");

        $rootUrl = $this->pieCrust->getConfig()->getValueUnchecked('site/root');
        
        $xml = new \XMLWriter();
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
                $locUrl = $rootUrl . ltrim($loc['url'], '/');
                $xml->writeElement('loc', $locUrl);
                
                // lastmod
                $locLastModType = 'now';
                if (isset($loc['lastmod']))
                {
                    $locLastModType = $loc['lastmod'];
                }

                $locLastMod = $locLastModType;
                if ($locLastModType == 'now')
                {
                    $locLastMod = date('c');
                }
                $xml->writeElement('lastmod', $locLastMod);
                
                // changefreq
                if (isset($loc['changefreq']))
                {
                    $xml->writeElement('changefreq', $loc['changefreq']);
                }
                
                // priority
                if (isset($loc['priority']))
                {
                    $xml->writeElement('priority', $loc['priority']);
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
