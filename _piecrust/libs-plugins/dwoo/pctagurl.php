<?php

function Dwoo_Plugin_pctagurl_compile(Dwoo_Compiler $compiler, $value)
{
    $format = DwooTemplateEngine::getTagUrlFormat();
    return '\'' . DwooTemplateEngine::formatUri(UriBuilder::buildTagUri($format, $value)) . '\'';
}
