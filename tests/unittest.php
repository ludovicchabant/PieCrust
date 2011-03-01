<?php

require_once 'util.php';
require_once 'libs/phpunit_webreport/PHPUnit/WebReport.php';

define('PIECRUST_UNITTESTS_CACHE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '_cache' . DIRECTORY_SEPARATOR);

// This requires the PEAR PHPUnit package.
define('TESTS_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'test-cases');
if (isset($_SERVER['PHPRC']))
{
	// Windows
	define('PHPUNIT', '"' . $_SERVER['PHPRC'] . DIRECTORY_SEPARATOR . 'php" "' . $_SERVER['PHPRC'] . DIRECTORY_SEPARATOR . 'phpunit"');
}
else
{
	// Mac/Unix
	define('PHPUNIT', 'phpunit');
}

$logFile = PIECRUST_UNITTESTS_CACHE_DIR . 'test-results.xml';
$command = PHPUNIT . ' --log-junit "' . $logFile . '" "' . TESTS_DIR . '"';
$output = array();
exec($command, $output);
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
