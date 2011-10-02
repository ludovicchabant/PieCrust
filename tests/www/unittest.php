<?php

define('PIECRUST_UNITTESTS_ROOT_DIR', dirname(__DIR__) . '/src/test-websites/tests/');
define('PIECRUST_UNITTESTS_TEST_CASES_DIR', dirname(__DIR__) . '/src/test-cases/');

require_once 'global_setup.php';
require_once 'util.php';

require_once 'PHPUnitWebReport/PHPUnit/WebReport.php';

$logFile = dirname(__DIR__) . '/tmp/test-results.xml';
$output = PHPUnit_WebReport_Dashboard::run(PIECRUST_UNITTESTS_TEST_CASES_DIR, $logFile);
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
