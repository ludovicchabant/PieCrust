<?php

function Dwoo_Plugin_pcposturl_compile(Dwoo_Compiler $compiler, $year, $month, $day, $slug, $blogKey = null)
{
    $postInfo = array(
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'name' => $slug
    );
    $format = DwooTemplateEngine::getPostUrlFormat($blogKey);
    return '\'' . DwooTemplateEngine::formatUri(UriBuilder::buildPostUri($format, $postInfo)) . '\'';
}
