<?php

require 'bootstrap.php';

use Symfony\Component\Yaml\Yaml;
use PieCrust\Benchmarks\ApplicationBenchmark;


function compare_times($oldTime, $newTime)
{
    $oldMedian = (float)$oldTime['profiling']['median'];
    $oldAverage = (float)$oldTime['profiling']['average'];
    $oldMax = (float)$oldTime['profiling']['max'];

    $newMedian = (float)$newTime['profiling']['median'];
    $newAverage = (float)$newTime['profiling']['average'];
    $newMax = (float)$newTime['profiling']['max'];

    $medianDiff = $newMedian * 100.0 / $oldMedian - 100.0;
    $averageDiff = $newAverage * 100.0 / $oldAverage - 100.0;
    $maxDiff = $newMax * 100.0 / $oldMax - 100.0;

    echo $newTime['name'] . ' : ' . sprintf("%+01.1f%%", $medianDiff) . PHP_EOL;
    echo sprintf('  median  : %01.2fms -> %01.2fms', $oldMedian, $newMedian) . PHP_EOL;
    echo sprintf('  average : %01.2fms -> %01.2fms', $oldAverage, $newAverage) . PHP_EOL;
    echo sprintf('  max     : %01.2fms -> %01.2fms', $oldMax, $newMax) . PHP_EOL;
    echo PHP_EOL;
}

$hostname = php_uname("n");

$b = new ApplicationBenchmark();
$newTimes = $b->runAllBenchmarks();
if (is_file('benchmarks.yml'))
{
    $oldTimes = Yaml::parse('benchmarks.yml');
    if (isset($oldTimes[$hostname]))
    {
        foreach ($oldTimes[$hostname] as $oldTime)
        {
            foreach ($newTimes as $newTime)
            {
                if ($newTime['name'] == $oldTime['name'])
                {
                    compare_times($oldTime, $newTime);
                    break;
                }
            }
        }
    }
    $oldTimes[$hostname] = $newTimes;
    file_put_contents('benchmarks.yml', Yaml::dump($oldTimes, 4));
}
else
{
    $newTimes = array($hostname => $newTimes);
    file_put_contents('benchmarks.yml', $newTimes);
}

