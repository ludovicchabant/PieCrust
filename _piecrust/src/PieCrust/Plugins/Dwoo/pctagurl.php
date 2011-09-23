<?php

// No namespace because Dwoo doesn't support them with
// the directory loading system.
use PieCrust\TemplateEngines\DwooTemplateEngine;
use PieCrust\Util\LinkCollector;
use PieCrust\Util\UriBuilder;


function Dwoo_Plugin_pctagurl(Dwoo $dwoo, $value, $blogKey = null)
{
    if (LinkCollector::isEnabled()) LinkCollector::instance()->registerTagCombination($blogKey, $value);
    $format = DwooTemplateEngine::getTagUrlFormat($blogKey);
    return DwooTemplateEngine::formatUri(UriBuilder::buildTagUri($format, $value));
}
