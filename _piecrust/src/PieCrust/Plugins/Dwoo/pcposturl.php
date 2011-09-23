<?php

// No namespace because Dwoo doesn't support them with
// the directory loading system.
use PieCrust\TemplateEngines\DwooTemplateEngine;
use PieCrust\Util\UriBuilder;


function Dwoo_Plugin_pcposturl(Dwoo $dwoo, $year, $month, $day, $slug, $blogKey = null)
{
    $postInfo = array(
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'name' => $slug
    );
    $format = DwooTemplateEngine::getPostUrlFormat($blogKey);
    return DwooTemplateEngine::formatUri(UriBuilder::buildPostUri($format, $postInfo));
}
