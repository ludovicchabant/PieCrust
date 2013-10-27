<?php

namespace PieCrust\Plugins;

use PieCrust\IPieCrust;
use PieCrust\PieCrustPlugin;
use PieCrust\Chef\ChefEnvironment;


class BuiltinPlugin extends PieCrustPlugin
{
    public function getName()
    {
        return "__builtin__";
    }

    public function getFormatters()
    {
        return array(
            new \PieCrust\Formatters\MarkdownFormatter(),
            new \PieCrust\Formatters\PassThroughFormatter(),
            new \PieCrust\Formatters\SmartyPantsFormatter(),
            new \PieCrust\Formatters\TextileFormatter(),
            new \PieCrust\Formatters\HamlFormatter()
        );
    }

    public function getTemplateEngines()
    {
        return array(
            new \PieCrust\TemplateEngines\MustacheTemplateEngine(),
            new \PieCrust\TemplateEngines\PassThroughTemplateEngine(),
            new \PieCrust\TemplateEngines\TwigTemplateEngine()
        );
    }

    public function getFileSystems()
    {
        return array(
            new \PieCrust\IO\FlatFileSystem(),
            new \PieCrust\IO\ShallowFileSystem(),
            new \PieCrust\IO\HierarchicalFileSystem(),
            new \PieCrust\IO\DropboxFileSystem()
        );
    }

    public function getProcessors()
    {
        return array(
            new \PieCrust\Baker\Processors\CopyFileProcessor(),
            new \PieCrust\Baker\Processors\LessProcessor(),
            new \PieCrust\Baker\Processors\SassProcessor(),
            new \PieCrust\Baker\Processors\CompassProcessor(),
            new \PieCrust\Baker\Processors\YUICompressorProcessor(),
            new \PieCrust\Baker\Processors\SitemapProcessor()
        );
    }

    public function getImporters()
    {
        return array(
            new \PieCrust\Interop\Importers\WordpressImporter(),
            new \PieCrust\Interop\Importers\JekyllImporter(),
            new \PieCrust\Interop\Importers\JoomlaImporter()
        );
    }

    public function getCommands()
    {
        return array(
            new \PieCrust\Chef\Commands\HelpCommand(),
            new \PieCrust\Chef\Commands\BakeCommand(),
            new \PieCrust\Chef\Commands\CategoriesCommand(),
            new \PieCrust\Chef\Commands\ImportCommand(),
            new \PieCrust\Chef\Commands\InitCommand(),
            new \PieCrust\Chef\Commands\PluginsCommand(),
            new \PieCrust\Chef\Commands\ThemesCommand(),
            new \PieCrust\Chef\Commands\ServeCommand(),
            new \PieCrust\Chef\Commands\RootCommand(),
            new \PieCrust\Chef\Commands\StatsCommand(),
            new \PieCrust\Chef\Commands\TagsCommand(),
            new \PieCrust\Chef\Commands\UploadCommand(),
            new \PieCrust\Chef\Commands\PurgeCommand(),
            new \PieCrust\Chef\Commands\PrepareCommand(),
            new \PieCrust\Chef\Commands\FindCommand(),
            new \PieCrust\Chef\Commands\ShowConfigCommand(),
            new \PieCrust\Chef\Commands\SelfUpdateCommand()
        );
    }

    public function getRepositories()
    {
        return array(
            new \PieCrust\Repositories\BitBucketRepository(),
            new \PieCrust\Repositories\FileSystemRepository()
        );
    }

    public function initialize(IPieCrust $pieCrust)
    {
        $environment = $pieCrust->getEnvironment();
        if ($environment instanceof ChefEnvironment)
        {
            $environment->addCommandExtension(
                'prepare',
                new \PieCrust\Chef\Commands\PreparePageCommandExtension()
            );
            $environment->addCommandExtension(
                'prepare',
                new \PieCrust\Chef\Commands\PreparePostCommandExtension()
            );
            $environment->addCommandExtension(
                'prepare',
                new \PieCrust\Chef\Commands\PrepareFeedCommandExtension()
            );
        }
    }
}

