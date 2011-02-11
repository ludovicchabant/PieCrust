<?php

$pieCrustStartTime = microtime(true);
define('PIECRUST_URL_BASE', dirname($_SERVER['PHP_SELF']));
require_once('_piecrust/PieCrust.class.php');
$pieCrust = new PieCrust(PIECRUST_URL_BASE);
$pieCrust->run();
$pieCrustEndTime = microtime(true);
echo '<!-- baking lasted ' . ($pieCrustEndTime - $pieCrustStartTime) . 's. -->';
