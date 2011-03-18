<?php

// Add the PieCrust app directory to the include path.
set_include_path(get_include_path() . PATH_SEPARATOR . 
                 (dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . '_piecrust') . PATH_SEPARATOR .
                 (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'libs'));

if (!defined('PHP_VERSION_ID') or PHP_VERSION_ID < 50300)
{
    die("You need PHP 5.3+ to use PieCrust.");
}
