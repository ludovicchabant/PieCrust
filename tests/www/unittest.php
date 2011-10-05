<?php

define('PIECRUST_UNITTESTS_ROOT_DIR', dirname(__DIR__) . '/src/test-websites/tests/');
define('PIECRUST_UNITTESTS_TEST_CASES_DIR', dirname(__DIR__) . '/src/test-cases/');

require_once 'global_setup.php';
require_once 'util.php';

require_once 'PHPUnitWebReport/PHPUnit/WebReport.php';

$logFile = dirname(__DIR__) . '/tmp/test-results.xml';
$output = PHPUnit_WebReport_Dashboard::run(PIECRUST_UNITTESTS_TEST_CASES_DIR, $logFile);
$dashboard = new PHPUnit_WebReport_Dashboard($logFile);

$GLOBALS['headMarkup'] = '<style type="text/css">'.$dashboard->getReportCss().'</style>';
include 'header.php';

echo '<h2>Test Cases Results</h2>';
$dashboard->display(3);

echo '<h2>PHPUnit Output</h2>';
echo '<pre>';

foreach ($output as $line)
{
    echo htmlentities($line) . PHP_EOL;
}

include 'footer.php';
