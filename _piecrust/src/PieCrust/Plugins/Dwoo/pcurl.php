<?php

// No namespace because Dwoo doesn't support them with
// the directory loading system.
use PieCrust\TemplateEngines\DwooTemplateEngine;


function Dwoo_Plugin_pcurl(Dwoo $dwoo, $value)
{
    return DwooTemplateEngine::formatUri($value);
}
