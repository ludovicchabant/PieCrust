<?php

$pieCrustStartTime = microtime(true);

define('PIECRUST_URL_BASE', dirname($_SERVER['PHP_SELF']));
require_once('_piecrust/PieCrust.class.php');
PieCrust::setup();
$pieCrust = new PieCrust(PIECRUST_URL_BASE);
$pieCrust->run();

$pieCrustEndTime = microtime(true);
echo '<!-- baked in ' . ($pieCrustEndTime - $pieCrustStartTime) . 's. -->';
