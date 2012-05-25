<?php

function unittest_setup()
{
    // Setup the PieCrust environement.
    require dirname(__DIR__) . '/piecrust.php';
    piecrust_setup('test');

    // Setup the unit-tests autoloader.
    require __DIR__ . '/libs/autoload.php';

    // Include the global utilities.
    require __DIR__ . '/src/util.php';
}

unittest_setup();

