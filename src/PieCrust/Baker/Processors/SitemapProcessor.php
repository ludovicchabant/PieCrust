<?php

namespace PieCrust\Baker\Processors;

use Symfony\Component\Yaml\Yaml;
use PieCrust\PieCrustException;
use PieCrust\Util\UriParser;
use PieCrust\Util\PieCrustHelper;


class SitemapProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('sitemap', 'sitemap', 'xml');
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $sitemap = Yaml::parse(file_get_contents($inputPath));
        
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'utf-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $this->addManualLocations($sitemap, $xml);
        $this->addAutomaticLocations($sitemap, $xml);
        $xml->endElement();
        $xml->endDocument();
        $markup  = $xml->outputMemory(true);
        file_put_contents($outputPath, $markup);
    }

    private function addAutomaticLocations($sitemap, $xml)
    {
        if (!isset($sitemap['autogen']))
            return;

        $autogen = $sitemap['autogen'];
        if (isset($autogen['pages']) && $autogen['pages'])
        {
            foreach ($this->pieCrust->getEnvironment()->getPages() as $page)
            {
                $xml->startElement('url');
                $xml->writeElement('loc', PieCrustHelper::formatUri($this->pieCrust, $page->getUri()));
                $xml->writeElement('lastmod', date('c'));
                $xml->endElement();
            }
        }

        if (isset($autogen['posts']) && $autogen['posts'])
        {
            $blogKeys = $this->pieCrust->getConfig()->getValueUnchecked('site/blogs');
            foreach ($blogKeys as $blogKey)
            {
                foreach ($this->pieCrust->getEnvironment()->getPosts($blogKey) as $page)
                {
                    $xml->startElement('url');
                    $xml->writeElement('loc', PieCrustHelper::formatUri($this->pieCrust, $page->getUri()));
                    $xml->writeElement('lastmod', date('c'));
                    $xml->endElement();
                }
            }
        }
    }

    private function addManualLocations($sitemap, $xml)
    {
        if (!isset($sitemap['locations']))
            return;

        $rootUrl = $this->pieCrust->getConfig()->getValueUnchecked('site/root');
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
    }
}
