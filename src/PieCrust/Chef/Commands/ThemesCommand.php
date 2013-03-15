<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Chef\Commands\PluginsCommand;
use PieCrust\Repositories\ThemeInstallContext;
use PieCrust\Util\PieCrustHelper;


class ThemesCommand extends ChefCommand
{
    public function getName()
    {
        return 'themes';
    }

    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Manages themes for your PieCrust website.";
 
        $listParser = $parser->addCommand('info', array(
            'description' => "Shows info about the currently installed theme."
        ));

        $listParser = $parser->addCommand('find', array(
            'description' => "Finds themes to install from the internet."
        ));
        $listParser->addArgument('query', array(
            'description' => "Filters the themes matching the given query.",
            'help_name'   => 'PATTERN',
            'optional'    => true
        ));

        $installParser = $parser->addCommand('install', array(
            'description' => "Installs the given theme."
        ));
        $installParser->addArgument('name', array(
            'description' => "The name of the theme to install.",
            'help_name'   => 'NAME',
            'optional'    => false
        ));
    }

    public function run(ChefContext $context)
    {
        $result = $context->getResult();

        $action = 'info';
        if ($result->command->command_name)
            $action = $result->command->command_name;
        $action .= 'Themes';
        if (method_exists($this, $action))
        {
            return $this->$action($context);
        }

        throw new PieCrustException("Unknown action '{$action}'.");
    }

    protected function infoThemes(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();

        $themeDir = $app->getThemeDir();
        if ($themeDir === false)
        {
            $log->err("No theme is currently installed.");
            return 1;
        }
        if (!is_file($themeDir . 'theme_info.yml'))
        {
            $log->err("The current theme is missing its 'theme_info.yml' file.");
            return 2;
        }

        $themeInfo = Yaml::parse($themeDir . 'theme_info.yml');
        $log->info($themeInfo['name'] . ': ' . $themeInfo['description']);
    }

    protected function findThemes(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();
        $result = $context->getResult();

        $sources = $this->getSources($app, $log);
        $query = $result->command->command->args['query'];
        $themes = $this->getThemeMetadata($app, $sources, $query, false, $log);
        foreach ($themes as $theme)
        {
            $log->info("{$theme['name']} : {$theme['description']}");
        }
    }

    protected function installThemes(ChefContext $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();
        $result = $context->getResult();

        $sources = $this->getSources($app, $log);
        $themeName = $result->command->command->args['name'];
        $themes = $this->getThemeMetadata($app, $sources, $themeName, true, $log);
        if (count($themes) != 1)
            throw new PieCrustException("Can't find a single theme named: {$themeName}");

        $theme = $themes[0];
        $log->info($log->convertColors("%gGET%n %m{$theme['source']}%n [{$theme['name']}]"));
        $className = $theme['repository_class'];
        $repository = new $className;
        $installContext = new ThemeInstallContext($app, $log);
        $repository->installTheme($theme, $installContext);
        $this->installRequirements($theme, $context);
        $log->info("Theme {$theme['name']} is now installed.");
    }

    protected function getSources(IPieCrust $pieCrust, $log)
    {
        $sources = $pieCrust->getConfig()->getValue('site/themes_sources');
        if ($log)
        {
            $log->debug("Got site theme sources: ");
            foreach ($sources as $s)
            {
                $log->debug(" - " . $s);
            }
        }
        return $sources;
    }

    protected function getThemeMetadata(IPieCrust $app, $sources, $pattern, $exact, $log)
    {
        $metadata = array();
        foreach ($sources as $source)
        {
            $repository = PieCrustHelper::getRepository($app, $source);
            $repositoryClass = get_class($repository);

            if ($log)
            {
                $log->debug("Loading themes metadata from: " . $source);
            }
            $themes = $repository->getThemes($source);
            foreach ($themes as $theme)
            {
                // Make sure we have the required properties.
                if (!isset($theme['name']))
                    $theme['name'] = 'UNNAMED THEME';
                if (!isset($theme['description']))
                    $theme['description'] = 'NO DESCRIPTION AVAILABLE.';
                $theme['repository_class'] = $repositoryClass;

                // Find if the theme matches the query.
                $matches = true;
                if ($exact)
                {
                    $matches = strcasecmp($theme['name'], $pattern) == 0;
                }
                elseif ($pattern)
                {
                    $matchesName = (stristr($theme['name'], $pattern) != false);
                    $matchesDescription = (stristr($theme['description'], $pattern) != false);
                    $matches = ($matchesName or $matchesDescription);
                }

                if ($matches)
                {
                    // Get the theme, and exit if we only want one.
                    $metadata[] = $theme;
                    if ($exact)
                        break;
                }
            }
        }
        return $metadata;
    }

    protected function installRequirements(array $theme, ChefContext $context)
    {
        if (!isset($theme['requires']))
            return;

        $log = $context->getLog();

        $requirements = $theme['requires'];
        if (isset($requirements['plugins']))
        {
            $requiredPlugins = $requirements['plugins'];
            if (!is_array($requiredPlugins))
                $requiredPlugins = array($requiredPlugins);

            $log->info("Installing required plugins: ");
            $installer = new PluginsCommand();
            foreach ($requiredPlugins as $pluginName)
            {
                $log->debug("Installing {$pluginName}...");
                $installer->installPlugin($pluginName, $context);
            }
        }
    }
}
