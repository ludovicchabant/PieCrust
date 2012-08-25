<?php

require __DIR__ . '/../piecrust.php';

piecrust_setup('compiler');

if (!Phar::canWrite())
    die("Can't create Phar file because 'phar.readonly' is enabled in 'php.ini'." . PHP_EOL);

$pharFile = 'piecrust.phar';
if ($argc > 1)
    $pharFile = $argv[1];

$compiler = new PieCrust\Compiler();
$compiler->compile($pharFile);

