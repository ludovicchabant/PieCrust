<?php

namespace PieCrust\Chef;

use \Exception;
use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Plugins\PluginLoader;
use PieCrust\Util\PathHelper;


/**
 * The PieCrust Chef application.
 */
class Chef
{
    /**
     * Builds a new instance of Chef.
     */
    public function __construct()
    {
    }
    
    /**
     * Runs Chef given some command-line arguments.
     */
    public function run($userArgc = null, $userArgv = null)
    {
        try
        {
            return $this->runUnsafe($userArgc, $userArgv);
        }
        catch (Exception $e)
        {
            echo "Fatal Error: " . $e->getMessage() . PHP_EOL;
            echo $e->getFile() . ":" . $e->getLine() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;
            return 2;
        }
    }

    /**
     * Runs Chef given some command-line arguments.
     */
    public function runUnsafe($userArgc = null, $userArgv = null)
    {
        // Get the arguments.
        if ($userArgc == null || $userArgv == null)
        {
            $getopt = new \Console_Getopt();
            $userArgv = $getopt->readPHPArgv();
            // `readPHPArgv` returns a `PEAR_Error` (or something like it) if
            // it can't figure out the CLI arguments.
            if (!is_array($userArgv))
                throw new PieCrustException($userArgv->getMessage());
            $userArgc = count($userArgv);
        }

        // Find whether the '--root' parameter was given.
        $rootDir = null;
        foreach ($userArgv as $arg)
        {
            if (substr($arg, 0, strlen('--root=')) == '--root=')
            {
                $rootDir = substr($arg, strlen('--root='));
                break;
            }
        }
        if ($rootDir == null)
        {
            // No root given. Find it ourselves.
            $rootDir = PathHelper::getAppRootDir(getcwd());
        }
        else
        {
            // The root was given.
            $rootDir = PathHelper::getAbsolutePath($rootDir);
            if (!is_dir($rootDir))
                throw new PieCrustException("The given root directory doesn't exist: " . $rootDir);
        }

        // Build the appropriate app.
        if ($rootDir == null)
        {
            $pieCrust = new NullPieCrust();
        }
        else
        {
            $environment = new ChefEnvironment();
            $pieCrust = new PieCrust(array(
                'root' => $rootDir,
                'cache' => !in_array('--no-cache', $userArgv),
                'environment' => $environment
            ));
        }

        // Set up the command line parser.
        $parser = new \Console_CommandLine(array(
            'name' => 'chef',
            'description' => 'The PieCrust chef manages your website.',
            'version' => PieCrustDefaults::VERSION
        ));
        $this->addCommonOptionsAndArguments($parser);
        // Sort commands by name.
        $sortedCommands = $pieCrust->getPluginLoader()->getCommands();
        usort($sortedCommands, function ($c1, $c2) { return strcmp($c1->getName(), $c2->getName()); });
        // Add commands to the parser.
        foreach ($sortedCommands as $command)
        {
            $commandParser = $parser->addCommand($command->getName());
            $command->setupParser($commandParser, $pieCrust);
        }

        // Parse the command line.
        try
        {
            $result = $parser->parse($userArgc, $userArgv);
        }
        catch (Exception $e)
        {
            $parser->displayError($e->getMessage(), false);
            return 1;
        }

        // If no command was given, use `help`.
        if (empty($result->command_name))
        {
            $result = $parser->parse(2, array('chef', 'help'));
        }

        // Create the log.
        $debugMode = $result->options['debug'];
        $quietMode = $result->options['quiet'];
        if ($debugMode && $quietMode)
        {
            $parser->displayError("You can't specify both --debug and --quiet.", false);
            return 1;
        }
        $log = new ChefLog('Chef', '', array('lineFormat' => '%{message}'));
        // Make the log available to PieCrust.
        if ($rootDir != null)
            $environment->setLog($log);
        // Make the log available for debugging purposes.
        $GLOBALS['__CHEF_LOG'] = $log;

        // Handle deprecated stuff.
        if ($result->options['no_cache_old'])
        {
            $log->warning("The `--nocache` option has been renamed `--no-cache`.");
            $result->options['no_cache'] = true;
        }

        // Run the command.
        foreach ($pieCrust->getPluginLoader()->getCommands() as $command)
        {
            if ($command->getName() == $result->command_name)
            {
                try
                {
                    if ($rootDir == null && $command->requiresWebsite())
                    {
                        $cwd = getcwd();
                        throw new PieCrustException("No PieCrust website in '{$cwd}' ('_content/config.yml' not found!).");
                    }

                    if ($debugMode)
                    {
                        $log->debug("PieCrust v." . PieCrustDefaults::VERSION);
                        $log->debug("  Website: {$rootDir}");
                    }

                    $context = new ChefContext($pieCrust, $result, $log);
                    $context->setVerbosity($debugMode ? 
                        'debug' : 
                        ($quietMode ? 'quiet' : 'default')
                    );
                    $command->run($context);
                    return;
                }
                catch (Exception $e)
                {
                    $this->logException($log, $e, $debugMode);
                    return 1;
                }
            }
        }
    }

    protected function logException($log, $e, $debugMode = false)
    {
        if ($debugMode)
        {
            $log->emerg($e->getMessage());
            $log->debug($e->getTraceAsString());
            $e = $e->getPrevious();
            while ($e)
            {
                $log->err("-----------------");
                $log->err($e->getMessage());
                $log->debug($e->getTraceAsString());
                $e = $e->getPrevious();
            }
            $log->err("-----------------");
        }
        else
        {
            $log->emerg($e->getMessage());
            $e = $e->getPrevious();
            while ($e)
            {
                $log->err($e->getMessage());
                $e = $e->getPrevious();
            }
        }
    }

    protected function addCommonOptionsAndArguments(\Console_CommandLine $parser)
    {
        $parser->addOption('root', array(
            'long_name'   => '--root',
            'description' => "The root directory of the website (defaults to the first parent of the current directory that contains a '_content' directory).",
            'default'     => null,
            'help_name'   => 'ROOT_DIR'
        ));
        $parser->addOption('debug', array(
            'long_name'   => '--debug',
            'description' => "Show debug information.",
            'default'     => false,
            'help_name'   => 'DEBUG',
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('no_cache', array(
            'long_name'   => '--no-cache',
            'description' => "When applicable, disable caching.",
            'default'     => false,
            'help_name'   => 'NOCACHE',
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('quiet', array(
            'long_name'   => '--quiet',
            'description' => "Print only important information.",
            'default'     => false,
            'help_name'   => 'QUIET',
            'action'      => 'StoreTrue'
        ));

        // Deprecated stuff.
        $parser->addOption('no_cache_old', array(
            'long_name'   => '--nocache',
            'description' => "Deprecated. Use `--no-cache`.",
            'default'     => false,
            'help_name'   => 'NOCACHE',
            'action'      => 'StoreTrue'
        ));
    }
}

