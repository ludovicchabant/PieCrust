<?php

require 'bootstrap.php';
require 'src/benchmarks/ApplicationBenchmark.php';

use Symfony\Component\Yaml\Yaml;

$b = new ApplicationBenchmark();
$r = $b->runAllBenchmarks();
$t = Yaml::dump($r, 4);
file_put_contents('benchmarks.yml', $t);
echo $t;
echo PHP_EOL;

