<?php

namespace PieCrust\Plugins;

use PieCrust\PieCrustPlugin;


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
            new \PieCrust\Formatters\TextileFormatter()
        );
    }

    public function getTemplateEngines()
    {
        return array(
            new \PieCrust\TemplateEngines\DwooTemplateEngine(),
            new \PieCrust\TemplateEngines\MustacheTemplateEngine(),
            new \PieCrust\TemplateEngines\PassThroughTemplateEngine(),
            new \PieCrust\TemplateEngines\TwigTemplateEngine()
        );
    }

    public function getProcessors()
    {
        return array(
            new \PieCrust\Baker\Processors\CopyFileProcessor(),
            new \PieCrust\Baker\Processors\LessProcessor(),
            new \PieCrust\Baker\Processors\SitemapProcessor()
        );
    }

    public function getImporters()
    {
        return array(
            new \PieCrust\Interop\Importers\WordpressImporter()
        );
    }

    public function getCommands()
    {
        return array(
            new \PieCrust\Chef\Commands\BakeCommand(),
            new \PieCrust\Chef\Commands\ImportCommand(),
            new \PieCrust\Chef\Commands\InitCommand(),
            new \PieCrust\Chef\Commands\ServeCommand(),
            new \PieCrust\Chef\Commands\UploadCommand()
        );
    }
}

