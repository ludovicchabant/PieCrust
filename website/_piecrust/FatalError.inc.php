<?php

function piecrust_fatal_error($errors)
{
	$printDetails = (ini_get('display_errors') == true);
	$errorMessages = '';
	foreach ($errors as $e)
	{
		$errorMessages .= '<li><h3>' . $e->getMessage() . '</h3>';
		if ($printDetails)
		{
			$errorMessages .= '<p>Error: <code>' . $e->getCode() . '</code><br/>' .
							  '   File: <code>' . $e->getFile() . '</code><br/>' .
							  '   Line <code>' . $e->getLine() . '</code></p>';
		}
		$errorMessages .= '</li>' . "\n";
	}
	$html = <<<EOD
<!doctype html>
<html>
	<body>
		<h1>Wow, something is seriously fucked up here.</h1>
		<p>We're very sorry but things went so bad around here that we can't even show
		   you a nice error page. All we know is that the following happened:</p>
		<ul>
			$errorMessages
		</ul>
	</body>
</html>
EOD;
	echo $html;
}
