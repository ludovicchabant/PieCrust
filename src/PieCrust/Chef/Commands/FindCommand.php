<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;


class FindCommand extends ChefCommand
{
    public function getName()
    {
        return 'find';
    }
    
    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
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
        $parser->addOption('exact', array(
            'long_name'   => '--exact',
            'description' => "Treat the command argument as an exact path to a file, as opposed to a pattern to match.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('page_components', array(
            'long_name'   => '--components',
            'description' => "Return the page components, instead of the path, separated by a pipe ('|').",
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
        $exact = $result->command->options['exact'];
        $fullPath = $result->command->options['full_path'];
        // If no type filters are given, return all types.
        $returnAllTypes = (
            $result->command->options['pages'] == false and
            $result->command->options['posts'] == false and
            $result->command->options['templates'] == false
        );
        // Validate the argument.
        $pattern = $result->command->args['pattern'];
        if ($exact)
        {
            // Check we have a path to match, and get its absolute value.
            if (!$pattern)
                throw new PieCrustException("You need to specify a path when using the `--exact` option.");
            $pattern = PathHelper::getAbsolutePath($pattern);
        }
        else
        {
            // If a pattern was given, get the Regex'd version.
            if ($pattern)
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

        // Get some other stuff.
        $returnComponents = $result->command->options['page_components'];

        // Get a regex for the posts file-naming convention.
        $fs = FileSystem::create($pieCrust);
        $pathComponentsRegex = preg_quote($fs->getPostPathFormat(), '/');
        $pathComponentsRegex = str_replace(
            array('%year%', '%month%', '%day%', '%slug%'),
            array('(\d{4})', '(\d{2})', '(\d{2})', '(.+)'),
            $pathComponentsRegex
        );
        $pathComponentsRegex = '/' . $pathComponentsRegex . '/';

        $foundAny = false;

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

            if ($exact)
            {
                // Match the path exactly, or pass.
                if (str_replace('\\', '/', $pattern) != str_replace('\\', '/', $page->getPath()))
                    continue;
            }
            else if ($pattern)
            {
                // Match the regex, or pass.
                if (!preg_match($pattern, $page->getUri()))
                    continue;
            }

            $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $page->getPath());
            if (!$fullPath)
                $path = PieCrustHelper::getRelativePath($pieCrust, $path);

            if ($returnComponents)
            {
                $components = array(
                    'path' => $path,
                    'type' => 'page',
                    'uri' => $page->getUri(),
                    'slug' => $page->getUri()
                );

                if (PageHelper::isPost($page))
                {
                    $matches = array();
                    if (preg_match(
                        $pathComponentsRegex, 
                        str_replace('\\', '/', $path), 
                        $matches) !== 1)
                        throw new PieCrustException("Can't extract path components from path: {$path}");
                    
                    $components['type'] = 'post';
                    $components['year'] = $matches[1];
                    $components['month'] = $matches[2];
                    $components['day'] = $matches[3];
                    $components['slug'] = $matches[4];
                }

                $str = '';
                foreach ($components as $k => $v)
                {
                    $str .= $k . ': ' . $v . PHP_EOL;
                }
                $logger->info($str);
                $foundAny = true;
            }
            else
            {
                $logger->info($path);
                $foundAny = true;
            }
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

                    $relativePath = PieCrustHelper::getRelativePath($pieCrust, $path->getPathname());

                    if ($exact)
                    {
                        // Match the path exactly, or pass.
                        if (str_replace('\\', '/', $pattern) != str_replace('\\', '/', $path->getPathname()))
                            continue;
                    }
                    else if ($pattern)
                    {
                        // Match the regex, or pass.
                        if (!preg_match($pattern, $relativePath))
                            continue;
                    }

                    // Get the path to print.
                    $finalPath = $relativePath;
                    if ($fullPath)
                        $finalPath = $path->getPathname();
                    $finalPath = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $finalPath);

                    // Print the information!
                    if ($returnComponents)
                    {
                        $logger->info("path: {$finalPath}");
                        $logger->info("type: template");
                        $foundAny = true;
                    }
                    else
                    {
                        $logger->info($finalPath);
                        $foundAny = true;
                    }
                }
            }
        }

        if (!$foundAny)
        {
            $pattern = $result->command->args['pattern'];
            $logger->info("No match found for '{$pattern}'.");
        }

        return 0;
    }
}

