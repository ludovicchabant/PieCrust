<?php

/**
 * The StupidHttp auto-loader.
 *
 * It is recommended to use a global auto-loader, like the Symfony ClassLoader
 * component or the one provided with Composer. If you're taking care of your
 * own infrastructure, however, you can use this simple auto-loader to get
 * things going.
 */
class StupidHttp_Autoloader
{
    /**
     * Registers StupidHttp_Autoloader as an SPL autoloader.
     */
    public static function register()
    {
        $instance = new StupidHttp_Autoloader();
        spl_autoload_register(array($instance, 'autoload'));
    }

    /**
     * Autoloads the requested class, if it's part of StupidHttp.
     */
    public static function autoload($class)
    {
        if (strpos($class, 'StupidHttp_') !== 0)
            return;

        $path = dirname(__DIR__) . 
            DIRECTORY_SEPARATOR .
            str_replace('_', DIRECTORY_SEPARATOR, $class) . 
            '.php';
        if (is_file($path))
            require $path;
    }
}
