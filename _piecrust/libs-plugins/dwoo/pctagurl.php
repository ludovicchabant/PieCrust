<?php

function Dwoo_Plugin_pctagurl_compile(Dwoo_Compiler $compiler, $value)
{
    $format = DwooTemplateEngine::getTagUrlFormat();
    return '\'' . DwooTemplateEngine::formatUri(Paginator::buildTagUri($format, $value)) . '\'';
}
