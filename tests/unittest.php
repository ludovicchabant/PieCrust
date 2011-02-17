<?php

require_once 'util.php';

// This requires the PEAR PHPUnit package.
define('TESTS_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tests');
define('PHPUNIT', '"' . $_SERVER["PHPRC"] . DIRECTORY_SEPARATOR . 'php" "' . $_SERVER["PHPRC"] . DIRECTORY_SEPARATOR . 'phpunit"');

$command = PHPUNIT . ' "' . TESTS_DIR . '"';
$output = array();
exec($command, $output);

?>
<html>
	<head>
		<title>PieCrust Unit Tests</title>
	</head>
	<body>
<?php
foreach ($output as $line)
{
	echo $line . "<br/>\n";
}
?>
	</body>
</html>
