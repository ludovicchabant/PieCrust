<?php

define('PIECRUST_BENCHMARKS_ROOT_DIR', __DIR__ . '/test-websites/benchmarks/');
define('PIECRUST_BENCHMARKS_CACHE_DIR', __DIR__ . '/output/cache');

// This requires the PEAR Benchmark package.
require_once 'Benchmark/Timer.php';
require_once 'Benchmark/Iterate.php';

require_once 'util.php';
require_once 'piecrust_setup.php';

use PieCrust\Page\Page;
use PieCrust\Page\PageRenderer;
use PieCrust\PieCrust;


function init_app($cache)
{
    $pc = new PieCrust(array('cache' => $cache, 'root' => PIECRUST_BENCHMARKS_ROOT_DIR));
    $pc->setCacheDir(PIECRUST_BENCHMARKS_CACHE_DIR);
    $pc->getConfig();
    return $pc;
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
$pieCrust = init_app(true);
$bench->run($runCount, 'run_query', $pieCrust, '/empty');
$bench->stop();
display_profiling_times($runCount, $bench->getProfiling());

//
// Marked run (uncached, then cached).
//
echo '<h2>Timed Benchmark</h2>';

$pieCrust = init_app(true);

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