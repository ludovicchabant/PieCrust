<?php

require __DIR__ . '/../piecrust.php';

piecrust_setup('compiler');

if (!Phar::canWrite())
    die("Can't create Phar file because 'phar.readonly' is enabled in 'php.ini'." . PHP_EOL);

$pharFile = 'piecrust.phar';
$options = array();
for ($i = 1; $i < $argc; ++$i)
{
    $name = $argv[$i];
    if ($name[0] == '-')
    {
        if ($name == '--core-libs')
            $options['core_libs_only'] = true;
    }
    else
    {
        $pharFile = $name;
    }
}

$compiler = new PieCrust\Compiler();
$compiler->compile($pharFile, $options);

