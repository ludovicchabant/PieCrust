<?php
include 'benchmark.php';
?>
<!doctype html>
<html>
	<head>
		<title>PieCrust Tests</title>
	</head>
	<body>
<?php
	
echo '<h1>Rendering Markdown syntax page</h1>';
ensure_cache(true);
$bench = new Benchmark_Iterate();
$bench->start();

$pieCrust = new PieCrust();
$pieCrust->setConfig(array('site' => array('debug' => true, 'enable_cache' => true)));
$bench->setMarker('Created PieCrust app');

$bench->run(100, 'run_query', $bench, $pieCrust, '/markdown-syntax');

$bench->stop();
$bench->display();

//echo '<pre>';
//print_r($bench->getProfiling());
//echo '</pre>';

echo '<h2>Median page rendering time</h2>';
$prof = $bench->getProfiling();
$diffValues = array();
foreach ($prof as $p)
{
	if (preg_match('/^end_/', $p['name']))
	{
		array_push($diffValues, $p['diff']);
	}
}
sort($diffValues);
$count = count($diffValues);
$middle = $count / 2;
if ($count % 2 == 0)
{
	$medianTime = ($diffValues[$middle] + $diffValues[$middle-1])/2;
}
else
{
	$medianTime = $diffValues[$middle];
}
echo '<p>'.($medianTime*1000).'ms</p>';

/*echo '<h1>Rendering HTML page</h1>';
$bench = new Benchmark_Iterate();
$bench->start();
$bench->run(10, 'file_get_contents', '_reference/markdown-syntax.html');
$bench->stop();
$bench->display();*/

//echo run_query(null, $pieCrust, '/markdown-syntax');

?>
	</body>
</html>