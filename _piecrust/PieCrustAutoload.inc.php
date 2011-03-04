<?php

function pieCrustAutoload($class)
{
    if (substr($class, 0, 8) === "PieCrust")
    {
        include $class . '.class.php';
    }
}

spl_autoload_register('pieCrustAutoload');
