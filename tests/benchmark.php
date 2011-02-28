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

function run_query($pieCrust, $uri)
{
	$page = new Page($pieCrust, $uri);	
	$renderer = new PageRenderer($pieCrust);	
	return $renderer->get($page, null, false);
}

function run_detailed_query($bench, $pieCrust, $uri)
{
	$page = new Page($pieCrust, $uri);
	$bench->setMarker('Created page');
	
	$page->getConfig();
	$bench->setMarker('Loaded page config and contents.');
	
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

echo '<h1>Rendering Markdown syntax page</h1>';
	
//
// Iteration benchmark.
//
echo '<h2>Iteration Benchmark</h2>';
ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);
$bench = new Benchmark_Iterate();
$bench->start();
$pieCrust = new PieCrust();
$pieCrust->setConfig(array('site' => array('enable_cache' => true)));
$runCount = 100;
$bench->run($runCount, 'run_query', $pieCrust, '/markdown-syntax');
$bench->stop();

function filter_end_marker($value) { return preg_match('/^end_/', $value['name']); }
function map_diff_time($value) { return $value['diff']; }
$prof = $bench->getProfiling();
$diffValues = array_map('map_diff_time', array_filter($prof, 'filter_end_marker'));
echo '<p>Ran page query '.$runCount.' times.</p>';
echo '<p>Median page query: <strong>'.(median($diffValues)*1000).'ms</strong></p>';
echo '<p>Average page query: <strong>'.(average($diffValues)*1000).'ms</strong></p>';
echo '<p>Max page query: <strong>'.(max($diffValues)*1000).'ms</strong></p>';

//
// Marked run (uncached, then cached).
//
echo '<h2>Timed Benchmark</h2>';

echo '<h3>Uncached</h3>';
ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);
$bench = new Benchmark_Timer();
$bench->start();
run_detailed_query($bench, $pieCrust, '/markdown-syntax');
$bench->stop();
$bench->display();

echo '<h3>Cached</h3>';
$bench = new Benchmark_Timer();
$bench->start();
run_detailed_query($bench, $pieCrust, '/markdown-syntax');
$bench->stop();
$bench->display();

/*echo '<h1>Rendering HTML page</h1>';
$bench = new Benchmark_Iterate();
$bench->start();
$bench->run(10, 'file_get_contents', '_reference/markdown-syntax.html');
$bench->stop();
$bench->display();*/

?>
	</body>
</html>