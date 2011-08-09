<?php

function Dwoo_Plugin_pctagurl_compile(Dwoo_Compiler $compiler, $value, $blogKey = null)
{
    $format = DwooTemplateEngine::getTagUrlFormat($blogKey);
    return '\'' . DwooTemplateEngine::formatUri(UriBuilder::buildTagUri($format, $value)) . '\'';
}
