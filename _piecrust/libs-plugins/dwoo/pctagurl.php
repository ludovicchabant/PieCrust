<?php

require_once 'LinkCollector.class.php';

function Dwoo_Plugin_pctagurl_compile(Dwoo_Compiler $compiler, $value, $blogKey = null)
{
    if (LinkCollector::isEnabled()) LinkCollector::instance()->registerTagCombination($blogKey, $value);
    $format = DwooTemplateEngine::getTagUrlFormat($blogKey);
    return '\'' . DwooTemplateEngine::formatUri(UriBuilder::buildTagUri($format, $value)) . '\'';
}
