<?php

function Dwoo_Plugin_pccaturl_compile(Dwoo_Compiler $compiler, $value, $blogKey = null)
{
    $format = DwooTemplateEngine::getCategoryUrlFormat($blogKey);
    return '\'' . DwooTemplateEngine::formatUri(UriBuilder::buildCategoryUri($format, $value)) . '\'';
}
