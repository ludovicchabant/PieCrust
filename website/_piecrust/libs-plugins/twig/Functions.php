<?php

function twig_pcurl_function($value)
{
    return PIECRUST_URL_BASE . TwigTemplateEngine::getPathPrefix() . $value;
}
