<?php

namespace PieCrust\Chef;

require_once 'Log.php';
require_once 'Console/CommandLine.php';
require_once 'Console/Getopt.php';

use \Log;
use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use \Console_Getopt;
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
    public function __construct()
    {
    }
    
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

    public function runUnsafe($userArgc = null, $userArgv = null)
    {
        // Get the arguments.
        if ($userArgc == null || $userArgv == null)
        {
            $getopt = new Console_Getopt();
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
            $pieCrust = new PieCrust(array(
                'root' => $rootDir,
                'cache' => !in_array('--nocache', $userArgv)
            ));
        }

        // Set up the command line parser.
        $parser = new Console_CommandLine(array(
            'name' => 'chef',
            'description' => 'The PieCrust chef manages your website.',
            'version' => PieCrustDefaults::VERSION
        ));
        // Sort commands by name.
        $sortedCommands = $pieCrust->getPluginLoader()->getCommands();
        usort($sortedCommands, function ($c1, $c2) { return strcmp($c1->getName(), $c2->getName()); });
        // Add commands to the parser.
        foreach ($sortedCommands as $command)
        {
            $commandParser = $parser->addCommand($command->getName());
            $command->setupParser($commandParser, $pieCrust);
            $this->addCommonOptionsAndArguments($commandParser);
        }

        // Parse the command line.
        try
        {
            $result = $parser->parse($userArgc, $userArgv);
        }
        catch (Exception $e)
        {
            $parser->displayError($e->getMessage());
            return 1;
        }

        // If no command was given, use `help`.
        if (empty($result->command_name))
        {
            $result = $parser->parse(2, array('chef', 'help'));
        }

        // Create the log.
        $debugMode = $result->command->options['debug'];
        $quietMode = $result->command->options['quiet'];
        if ($debugMode && $quietMode)
        {
            $parser->displayError("You can't specify both --debug and --quiet.");
            return 1;
        }
        $log = Log::singleton('console', 'Chef', '', array('lineFormat' => '%{message}'));

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
                    $log->emerg(self::getErrorMessage($e, $debugMode));
                    return 1;
                }
            }
        }
    }

    public static function getErrorMessage(Exception $e, $debugMode = false)
    {
        $message = $e->getMessage();
        if ($debugMode)
        {
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Debug Information" . PHP_EOL;
            while ($e)
            {
                $message .= "-----------------" . PHP_EOL;
                $message .= $e->getMessage() . PHP_EOL;
                $message .= $e->getTraceAsString();
                $message .= PHP_EOL;
                $e = $e->getPrevious();
            }
            $message .= "-----------------" . PHP_EOL;
        }
        return $message;
    }

    protected function addCommonOptionsAndArguments(Console_CommandLine $parser)
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
        $parser->addOption('nocache', array(
            'long_name'   => '--nocache',
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
    }
}

