<?php

class PieCrustException extends Exception
{
}

function piecrust_error_handler($errno, $errstr, $errfile = null, $errline = 0, $errcontext = null)
{
	throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler(piecrust_error_handler);
