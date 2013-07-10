<?php

/**
 * Shows a hard-coded system message.
 */
function piecrust_show_system_message($message, $details = null)
{
    $contents = file_get_contents(__DIR__ . '/res/messages/' . $message . '.html');
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
    if (error_reporting() & $errno)
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    return;
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
            $guard = 100;
            while (ob_get_level() > 0 && (--$guard) > 0)
                ob_end_clean();
        }
        catch (Exception $e)
        {
        }
        
        piecrust_show_system_message('critical', "{$error['message']} in '{$error['file']}:{$error['line']}'");
        exit();
    }
}

/**
 * The Chef shutdown function (command-line version of `piecrust_shutdown_function`).
 */
function chef_shutdown_function()
{
    $error = error_get_last();
    if ($error)
    {
        try
        {
            $guard = 100;
            while (ob_get_level() > 0 && (--$guard) > 0)
                ob_end_clean();
        }
        catch (Exception $e)
        {
        }

        echo "Critical error in '{$error['file']}:{$error['line']}': {$error['message']}\n";
        exit();
    }
}

/**
 * Sets up basic things like the global error handler or the timezone.
 */
function piecrust_setup($profile = 'web')
{
    // Add the `libs` directory to the include path for the PEAR stuff
    // that still uses old-style includes, and for other stuff like Markdown
    // and Smartypants that have conditional include directories (i.e. include
    // a different implement of the same class based on runtime conditions).
    $srcDir = __DIR__ . '/src';
    $libsDir = __DIR__ . '/libs';
    set_include_path(
        get_include_path() . PATH_SEPARATOR . 
        $libsDir . '/pear' . PATH_SEPARATOR .
        $libsDir);

    // Set the autoloader
    $loader = (require $libsDir . '/autoload.php');
    $loader->add('Console_', $libsDir . '/pear');
    $loader->add('Log_', $libsDir . '/pear');
    
    // Set error handling.
    switch ($profile)
    {
    case 'web':
        {
            set_error_handler('piecrust_error_handler');
            register_shutdown_function('piecrust_shutdown_function');
            break;
        }
    case 'chef':
    default:
        {
            ini_set('display_errors', true);
            ini_set("memory_limit", "-1");
            error_reporting(E_ALL);
            set_error_handler('piecrust_error_handler');
            register_shutdown_function('chef_shutdown_function');
            break;
        }
    case 'test':
        {
            ini_set('display_errors', true);
            ini_set('display_startup_errors', true);
            error_reporting(E_ALL | E_STRICT);
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

    $parameters = PieCrust\Runner\PieCrustRunner::getPieCrustParameters($parameters);
    $pieCrust = new PieCrust\PieCrust($parameters);
    $runner = new PieCrust\Runner\PieCrustRunner($pieCrust);
    $runner->run($uri);
}

/**
 * Setups and runs a new instance of Chef.
 */
function piecrust_chef($userArgc = null, $userArgv = null, $profile = 'chef')
{
    piecrust_setup($profile);
    
    $chef = new PieCrust\Chef\Chef();
    return $chef->run($userArgc, $userArgv);
}
