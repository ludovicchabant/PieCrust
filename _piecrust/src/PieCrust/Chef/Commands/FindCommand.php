<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;


class FindCommand extends ChefCommand
{
    public function getName()
    {
        return 'find';
    }
    
    public function setupParser(Console_CommandLine $parser)
    {
        $parser->description = "Find all pages, posts and templates in the website, with optional filtering features.";
        $parser->addArgument('pattern', array(
            'description' => "The pattern to match.",
            'help_name'   => 'PATTERN',
            'optional'    => true
        ));
        $parser->addOption('pages', array(
            'long_name'   => '--pages',
            'description' => "Return pages.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('posts', array(
            'long_name'   => '--posts',
            'description' => "Return posts.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('templates', array(
            'long_name'   => '--templates',
            'description' => "Return templates.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('blog', array(
            'long_name'   => '--blog',
            'description' => "Only return posts from the given blog.",
            'help_name'   => 'BLOG',
            'default'     => null
        ));
        $parser->addOption('no_special', array(
            'long_name'   => '--no-special',
            'description' => "Don't return special pages like '_tag' and '_category'.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('full_path', array(
            'short_name'  => '-f',
            'long_name'   => '--full-path',
            'description' => "Return full paths instead of root-relative paths.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
    }

    public function run(ChefContext $context)
    {
        $logger = $context->getLog();
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        // Get some options.
        $fullPath = $result->command->options['full_path'];
        // If no type filters are given, return all types.
        $returnAllTypes = (
            $result->command->options['pages'] == false and
            $result->command->options['posts'] == false and
            $result->command->options['templates'] == false
        );
        // Get the regex pattern.
        $pattern = $result->command->args['pattern'];
        if ($pattern)
        {
            $pattern = PathHelper::globToRegex($pattern);
        }

        // Get the pages and posts.
        $pages = array();
        if ($returnAllTypes or $result->command->options['pages'])
        {
            $pages = PageHelper::getPages($pieCrust);
        }
        if ($returnAllTypes or $result->command->options['posts'])
        {
            $blogKeys = $pieCrust->getConfig()->getValue('site/blogs');
            if ($result->command->options['blog'])
                $blogKeys = array($result->command->options['blog']);

            foreach ($blogKeys as $blogKey)
            {
                $pages = array_merge($pages, PageHelper::getPosts($pieCrust, $blogKey));
            }
        }

        // Print the matching pages.
        foreach ($pages as $page)
        {
            if ($result->command->options['no_special'])
            {
                // Skip special pages.
                if ($page->getUri() == PieCrustDefaults::CATEGORY_PAGE_NAME or
                    $page->getUri() == PieCrustDefaults::TAG_PAGE_NAME)
                    continue;
            }

            if ($pattern)
            {
                if (!preg_match($pattern, $page->getUri()))
                    continue;
            }

            $path = $page->getPath();
            if (!$fullPath)
                $path = PathHelper::getRelativePath($pieCrust, $path);
            $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
            $logger->info($path);
        }

        // Get the template files and print them.
        if ($returnAllTypes or $result->command->options['templates'])
        {
            $templatesDirs = $pieCrust->getTemplatesDirs();
            foreach ($templatesDirs as $dir)
            {
                $dirIt = new \RecursiveDirectoryIterator($dir);
                $it = new \RecursiveIteratorIterator($dirIt);

                foreach ($it as $path)
                {
                    if ($it->isDot())
                        continue;

                    $relativePath = PathHelper::getRelativePath($pieCrust, $path->getPathname());
                    if ($pattern)
                    {
                        if (!preg_match($pattern, $relativePath))
                            continue;
                    }

                    $finalPath = $relativePath;
                    if ($fullPath)
                        $finalPath = $path->getPathname();
                    $finalPath = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $finalPath);
                    $logger->info($finalPath);
                }
            }
        }

        return 0;
    }
}

