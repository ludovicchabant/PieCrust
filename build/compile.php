<?php

require __DIR__ . '/../piecrust.php';

piecrust_setup('compiler');

$compiler = new PieCrust\Compiler();
$compiler->compile();

