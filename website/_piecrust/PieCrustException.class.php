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

