<?php
if (PHP_VERSION_ID < 50300)
{
    trigger_error("PieCrust requires PHP 5.3+", E_USER_ERROR);
}
require '../piecrust.php';
piecrust_run();
