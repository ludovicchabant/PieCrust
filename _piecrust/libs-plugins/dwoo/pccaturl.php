<?php

function Dwoo_Plugin_pccaturl_compile(Dwoo_Compiler $compiler, $value)
{
    $format = DwooTemplateEngine::getCategoryUrlFormat();
    return '\'' . DwooTemplateEngine::formatUri(UriBuilder::buildCategoryUri($format, $value)) . '\'';
}
