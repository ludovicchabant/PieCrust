<?php

// No namespace because Dwoo doesn't support them with
// the directory loading system.
use PieCrust\TemplateEngines\DwooTemplateEngine;
use PieCrust\Util\UriBuilder;


function Dwoo_Plugin_pccaturl(Dwoo $dwoo, $value, $blogKey = null)
{
    $format = DwooTemplateEngine::getCategoryUrlFormat($blogKey);
    return DwooTemplateEngine::formatUri(UriBuilder::buildCategoryUri($format, $value));
}
