<?php

namespace PieCrust\Chef;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\PieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Plugins\PluginLoader;

require_once 'Console/CommandLine.php';


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
        // Get the arguments.
        if ($userArgc == null || $userArgv == null)
        {
            if (php_sapi_name() != 'cli')
                throw new PieCrustException("PieCrust 'chef' must be run through the command-line.");

            if (isset($argc) && isset($argv))
            {
                // case of register_argv_argc = 1
                $userArgc = $argc;
                $userArgv = $argv;
            }
            if (isset($_SERVER['argc']) && isset($_SERVER['argv']))
            {
                $userArgc = $_SERVER['argc'];
                $userArgv = $_SERVER['argv'];
            }
            else
            {
                $userArgc = 0;
                $userArgv = array();
            }
        }

        // Find whether the '--root' parameter was given.
        $rootArgIndex = array_search('--root', $userArgv);
        if ($rootArgIndex === false)
        {
            // No root given. Find it ourselves.
            $rootDir = getcwd();
            while (!is_dir($rootDir . DIRECTORY_SEPARATOR . '_content'))
            {
                $rootDirParent = rtrim(dirname($rootDir), '/\\');
                if ($rootDir == $rootDirParent)
                {
                    $rootDir = null;
                    break;
                }
                $rootDir = $rootDirParent;
            }
        }
        else
        {
            // The root was given.
            if (count($userArgv) > $rootArgIndex + 1)
            {
                $rootDir = $userArgv[$rootArgIndex + 1];
                $rootDir = PathHelper::getAbsolutePath($rootDir);
                if (!is_dir($rootDir))
                    $rootDir = null;
            }
            else
            {
                $rootDir = null;
            }

            if ($rootDir == null)
            {
                $this->parser->displayError("The given root directory doesn't exist: {$rootDir}");
                die();
            }
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
                'debug' => in_array('--debug', $userArgv)
            ));
        }

        // Set up the command line parser.
        $this->parser = new Console_CommandLine(array(
            'name' => 'chef',
            'description' => 'The PieCrust chef manages your website.',
            'version' => PieCrustDefaults::VERSION
        ));
        foreach ($pieCrust->getPluginLoader()->getCommands() as $command)
        {
            $commandParser = $this->parser->addCommand($command->getName());
            $command->setupParser($commandParser);
            $this->addCommonOptionsAndArguments($commandParser);
        }

        // Parse the command line.
        try
        {
            $result = $this->parser->parse($userArgc, $userArgv);
        }
        catch (Exception $e)
        {
            $this->parser->displayError($e->getMessage());
            self::displayDebugInformation($this, $e);
            die();
        }

        // If no command was given, use `help`.
        if (empty($result->command_name))
            $result->command_name = 'help';

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
                        throw new PieCrustException("No PieCrust website in '{$cwd}' ('_content' not found!).");
                    }
                    $command->run($pieCrust, $result);
                    return;
                }
                catch (Exception $e)
                {
                    $this->parser->displayError(self::getErrorMessage($result, $e));
                    die();
                }
            }
        }
    }

    public static function getErrorMessage(Console_CommandLine_Result $result, Exception $e)
    {
        $message = $e->getMessage();
        if ($result->command->options['debug'])
        {
            $message .= PHP_EOL;
            $message .= PHP_EOL;
            $message .= "Debug Information" . PHP_EOL;
            while ($e)
            {
                $message .= "-----------------" . PHP_EOL;
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
    }
}

