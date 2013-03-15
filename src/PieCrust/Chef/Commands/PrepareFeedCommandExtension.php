<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PieCrustHelper;


class PrepareFeedCommandExtension extends ChefCommandExtension
{
    public function getName()
    {
        return 'feed';
    }

    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Creates an RSS or Atom feed.";
        $parser->addArgument('url', array(
            'description' => "The URL of the feed.",
            'help_name'   => 'URL',
            'optional'    => false
        ));
        $parser->addOption('use_atom', array(
            'long_name'   => '--atom',
            'description' => "Use Atom schema instead of RSS 2.0.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
    }

    public function run(ChefContext $context)
    {
        $result = $context->getResult();
        $app = $context->getApp();
        $log = $context->getLog();

        // Create the pages directory if it doesn't exist.
        if ($app->getPagesDir() == false)
        {
            $pagesDir = $app->getRootDir() . PieCrustDefaults::CONTENT_PAGES_DIR;
            $log->info("Creating pages directory: {$pagesDir}");
            mkdir($pagesDir, 0777, true);
            $app->setPagesDir($pagesDir);
        }

        // Create the path of the feed.
        $slug = $result->command->command->args['url'];
        $slug = ltrim($slug, '/\\');
        $fullPath = $app->getPagesDir() . $slug;
        if (!preg_match('/\.[a-z0-9]+$/i', $slug))
            $fullPath .= '.html';
        $relativePath = PieCrustHelper::getRelativePath($app, $fullPath);
        if (file_exists($fullPath))
            throw new PieCrustException("Page already exists: {$relativePath}");
        $log->info("Creating feed: {$relativePath}");

        // Get the feed template.
        $templatePath = PieCrustDefaults::RES_DIR() . 'prepare/rss.html';
        if ($result->command->command->options['use_atom'])
            $templatePath = PieCrustDefaults::RES_DIR() . 'prepare/atom.html';
        $template = file_get_contents($templatePath);

        // Write the contents.
        if (!is_dir(dirname($fullPath)))
            mkdir(dirname($fullPath), 0777, true);
        $f = fopen($fullPath, 'w');
        fwrite($f, $template);
        fclose($f);

        $fullUrl = $app->getConfig()->getValue('site/root') . $slug;
        $log->info("Don't forget to add a link into your main page's header like so:");
        $log->info("<link rel=\"alternate\" type=\"application/rss+xml\" href=\"{$fullUrl}\" />");
    }
}

