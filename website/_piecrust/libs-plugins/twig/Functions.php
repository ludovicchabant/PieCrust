<?php

function twig_pcurl_function($value)
{
    return TwigTemplateEngine::getPathPrefix() . $value;
}
