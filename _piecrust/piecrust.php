<?php

/**
 * Shows a hard-coded system message.
 */
function piecrust_show_system_message($message, $details = null)
{
    $contents = file_get_contents(__DIR__ . '/resources/messages/' . $message . '.html');
    if ($details != null)
    {
        $contents = str_replace('{{ details }}', $details, $contents);
    }
    echo $contents;
}

/**
 * The PieCrust error handler.
 */
function piecrust_error_handler($errno, $errstr, $errfile = null, $errline = 0, $errcontext = null)
{
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

/**
 * The PieCrust shutdown function.
 */
function piecrust_shutdown_function()
{ 
    $error = error_get_last();
    if ($error)
    {
        try
        {
            $obStatus = ob_get_status();
            if ($obStatus['level'] > 0)
                ob_end_clean();
        }
        catch (Exception $e)
        {
        }
        
        piecrust_show_system_message('critical', $error['message'] . ' in ' . $error['file'] . '(' . $error['line'] . ')');
        exit();
    }
}

/**
 * Sets up basic things like the global error handler or the timezone.
 */
function piecrust_setup($profile = 'web')
{
    // Check the version of PHP.
    if (!defined('PHP_VERSION_ID') or PHP_VERSION_ID < 50300)
    {
        die("You need PHP 5.3+ to use PieCrust.");
    }

    // Set the include path.
    set_include_path(get_include_path() . PATH_SEPARATOR .
        __DIR__ . '/src' . PATH_SEPARATOR .
        __DIR__ . '/libs');
    
    // Set the autoloader.
    spl_autoload_register(function($class)
        {
            if (strpos($class, 'PieCrust\\') == 0)
            {
                $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
                if (file_exists($file))
                {
                    require_once $file;
                    return true;
                }
            }
            return false;
        });
    
    // Set the time zone.
    date_default_timezone_set('America/Los_Angeles');
    
    // Set error handling.
    switch ($profile)
    {
    case 'web':
        {
            ini_set('display_errors', false);
            error_reporting(E_ALL ^ E_NOTICE);
            set_error_handler('piecrust_error_handler');
            register_shutdown_function('piecrust_shutdown_function');
            break;
        }
    case 'test':
    case 'debug':
        {
            ini_set('display_errors', true);
            ini_set('display_startup_errors', true);
            error_reporting(E_ALL);
            set_error_handler('piecrust_error_handler');
            break;
        }
    }
}


/**
 * Setups and runs a new PieCrust app with the given parameters, requesting the given URI.
 */
function piecrust_run($parameters = array(), $uri = null, $profile = 'web')
{
    piecrust_setup($profile);
    
    $pieCrust = new PieCrust\PieCrust($parameters);
    $pieCrust->run($uri);
}
