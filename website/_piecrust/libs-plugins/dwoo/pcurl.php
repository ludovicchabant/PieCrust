<?php

function Dwoo_Plugin_pcurl_compile(Dwoo_Compiler $compiler, $value)
{
    $delim = '\'';
    if (substr($value, 0, 1) === $delim && substr($value, -1) === $delim)
    {
        return '\'' . DwooTemplateEngine::getPathPrefix() . trim($value, $delim) . '\'';
    }
    else
    {
        return '\'' . DwooTemplateEngine::getPathPrefix() .'\'.' . $value;
    }
}
