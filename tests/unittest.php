<?php

require_once 'util.php';
require_once 'libs/phpunit_webreport/PHPUnit/WebReport.php';

define('PIECRUST_UNITTESTS_CACHE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '_cache' . DIRECTORY_SEPARATOR);
define('TESTS_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'test-cases');

$logFile = PIECRUST_UNITTESTS_CACHE_DIR . 'test-results.xml';
$output = PHPUnit_WebReport_Dashboard::run(TESTS_DIR, $logFile);
$dashboard = new PHPUnit_WebReport_Dashboard($logFile);

?>
<html>
    <head>
        <title>PieCrust Unit Tests</title>
        <style type="text/css">
<?php echo $dashboard->getReportCss(); ?>
        </style>
    </head>
    <body>
        <h1>Unit Testing</h1>
        <h2>Test Cases Results</h2>
<?php
    $dashboard->display();
?>
        <h2>PHPUnit Output</h2>
        <pre>
<?php
foreach ($output as $line)
{
    echo htmlentities($line) . "\n";
}
?>
        </pre>
    </body>
</html>
