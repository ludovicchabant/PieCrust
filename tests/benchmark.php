<?php

require_once 'util.php';

// This requires the PEAR Benchmark package.
require_once 'Benchmark/Timer.php';
require_once 'Benchmark/Iterate.php';

// Include the PieCrust app but with a root directory set
// to the test website's root dir.
define('PIECRUST_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('PIECRUST_BENCHMARKS_CACHE_DIR', PIECRUST_ROOT_DIR . '_cache');
require_once 'PieCrust.class.php';

function init_app($cache)
{
    $pc = new PieCrust(array('cache' => $cache));
    $pc->getConfig();
}

function run_query($pieCrust, $uri)
{
    $page = Page::createFromUri($pieCrust, $uri);  
    $renderer = new PageRenderer($pieCrust);    
    return $renderer->get($page, null, false);
}

function run_detailed_query($bench, $pieCrust, $uri)
{   
    $pieCrust->getConfig();
    $bench->setMarker('App config loaded');

    $page = Page::createFromUri($pieCrust, $uri);
    $bench->setMarker('Created page');
    
    $page->getConfig();
    $bench->setMarker('Loaded page config and contents');
    
    $renderer = new PageRenderer($pieCrust);
    $bench->setMarker('Created renderer');
    
    $page = $renderer->get($page, null, false);
    $bench->setMarker('Rendered page');
    
    return $page;
}

?>

<!doctype html>
<html>
    <head>
        <title>PieCrust Benchmarks</title>
    </head>
    <body>
<?php

echo '<h1>PieCrust Benchmarks</h1>';

$runCount = 100;
function filter_end_marker($value) { return preg_match('/^end_/', $value['name']); }
function map_diff_time($value) { return $value['diff']; }
function display_profiling_times($runCount, $prof)
{
    $diffValues = array_map('map_diff_time', array_filter($prof, 'filter_end_marker'));
    echo '<p>Ran '.$runCount.' times.</p>';
    echo '<p>Median time: <strong>'.(median($diffValues)*1000).'ms</strong></p>';
    echo '<p>Average time: <strong>'.(average($diffValues)*1000).'ms</strong></p>';
    echo '<p>Max time: <strong>'.(max($diffValues)*1000).'ms</strong></p>';
}

//
// App init benchmark.
//
echo '<h2>App Init Benchmark (non-caching config)</h2>';
ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);
$bench = new Benchmark_Iterate();
$bench->start();
$bench->run($runCount, 'init_app', false);
$bench->stop();
display_profiling_times($runCount, $bench->getProfiling());

echo '<h2>App Init Benchmark (caching config)</h2>';
ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);
$bench = new Benchmark_Iterate();
$bench->start();
$bench->run($runCount, 'init_app', true);
$bench->stop();
display_profiling_times($runCount, $bench->getProfiling());

//
// Page rendering benchmark.
//
echo '<h2>Page Rendering Benchmark</h2>';
ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);
$bench = new Benchmark_Iterate();
$bench->start();
$pieCrust = new PieCrust();
$bench->run($runCount, 'run_query', $pieCrust, '/empty');
$bench->stop();
display_profiling_times($runCount, $bench->getProfiling());

//
// Marked run (uncached, then cached).
//
echo '<h2>Timed Benchmark</h2>';

$pieCrust = new PieCrust();

echo '<h3>Uncached</h3>';
ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);
$bench = new Benchmark_Timer();
$bench->start();
run_detailed_query($bench, $pieCrust, '/empty');
$bench->stop();
$bench->display();

echo '<h3>Cached</h3>';
$bench = new Benchmark_Timer();
$bench->start();
run_detailed_query($bench, $pieCrust, '/empty');
$bench->stop();
$bench->display();

?>
    </body>
</html>