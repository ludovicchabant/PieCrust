<?php

require 'bootstrap.php';

use Symfony\Component\Yaml\Yaml;
use PieCrust\Benchmarks\ApplicationBenchmark;

$color = null;
if (DIRECTORY_SEPARATOR != "\\")
    $color = new Console_Color2();

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

    $medianDiffStr = sprintf("%+01.1f%%", $medianDiff);
    global $color;
    if ($color)
    {
        if ($medianDiff > 0)
            $medianDiffStr = $color->convert("%r" . $color->escape($medianDiffStr) . "%n");
        else
            $medianDiffStr = $color->convert("%g" . $color->escape($medianDiffStr) . "%n");
    }
    echo $newTime['name'] . ' : ' . $medianDiffStr . PHP_EOL;
    echo sprintf('  median  : %01.2fms -> %01.2fms', $oldMedian, $newMedian) . PHP_EOL;
    echo sprintf('  average : %01.2fms -> %01.2fms', $oldAverage, $newAverage) . PHP_EOL;
    echo sprintf('  max     : %01.2fms -> %01.2fms', $oldMax, $newMax) . PHP_EOL;
    echo PHP_EOL;
}

// Set up the command line parser.
$parser = new Console_CommandLine(array(
    'name' => 'run_benchmarks',
    'description' => 'The PieCrust benchmarks suite',
    'version' => PieCrust\PieCrustDefaults::VERSION
));
$parser->addOption('hostname', array(
    'long_name'   => '--hostname',
    'description' => "The hostname to use to store the benchmark results.",
    'default'     => php_uname("n"),
    'help_name'   => 'HOSTNAME'
));

// Parse user arguments.
try
{
    $result = $parser->parse();
}
catch (Exception $ex)
{
    $parser->displayError($ex->getMessage());
    return 1;
}

// Run!
$b = new ApplicationBenchmark();
$newTimes = $b->runAllBenchmarks();

// Get the results and save them.
$hostname = $result->options['hostname'];
if (is_file('benchmarks.yml'))
{
    $oldTimes = Yaml::parse('benchmarks.yml');
    if (isset($oldTimes[$hostname]))
    {
        // Got some previous times for this host... compare them to
        // the new ones.
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

