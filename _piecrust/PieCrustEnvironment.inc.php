<?php

// Check the version of PHP.
if (!defined('PHP_VERSION_ID') or PHP_VERSION_ID < 50300)
{
    die("You need PHP 5.3+ to use PieCrust.");
}

// Set the include path.
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
