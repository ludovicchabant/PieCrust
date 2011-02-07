<?php

function Dwoo_Plugin_pcurl_compile(Dwoo_Compiler $compiler, $value)
{
    $delim = '\'';
    if (substr($value, 0, 1) === $delim && substr($value, -1) === $delim)
    {
        return '\'' . PIECRUST_URL_BASE . '/?/' . trim($value, $delim) . '\'';
    }
    else
    {
        return '\'' . PIECRUST_URL_BASE . '/?/\'.' . $value;
    }
}
