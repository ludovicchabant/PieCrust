<?php

/**
 * The PieCrust exception class.
 */
class PieCrustException extends Exception
{
}

/**
 * The error handler for PieCrust that raises a new ErrorException.
 */
function piecrust_error_handler($errno, $errstr, $errfile = null, $errline = 0, $errcontext = null)
{
	throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

/**
 * Formats an array of exceptions into an HTML chunk.
 */
function piecrust_format_errors($errors, $printDetails = false)
{
	$errorMessages = '<ul>';
	foreach ($errors as $e)
	{
		$errorMessages .= '<li><h3>' . $e->getMessage() . '</h3>';
		if ($printDetails)
		{
			$errorMessages .= '<p>Error: <code>' . $e->getCode() . '</code><br/>' .
							  '   File: <code>' . $e->getFile() . '</code><br/>' .
							  '   Line <code>' . $e->getLine() . '</code><br/>' .
							  '   Trace: <code><pre>' . $e->getTraceAsString() . '</pre></code></p>';
		}
		$errorMessages .= '</li>';
	}
    $errorMessages .= '</ul>';
    return $errorMessages;
}


