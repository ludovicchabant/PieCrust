#!/usr/bin/php
<?php

set_include_path(__DIR__ . '/libs' . PATH_SEPARATOR . __DIR__ . '/src' . PATH_SEPARATOR . get_include_path());

require 'PHPUnit/Autoload.php';
$command = new PHPUnit_TextUI_Command;
$testCasesDir = __DIR__ . '/src/test-cases';
echo $testCasesDir.PHP_EOL;
return $command->run(array($_SERVER['argv'][0], $testCasesDir), true);
