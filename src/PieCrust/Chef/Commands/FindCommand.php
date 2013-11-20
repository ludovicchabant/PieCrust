<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\IO\FileSystem;
use PieCrust\Page\Iteration\DateSortIterator;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriBuilder;


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
        $result->command->args['pattern'] = $pattern;

        $foundAny = false;

        // Find pages.
        if ($returnAllTypes or $result->command->options['pages'])
        {
            $pages = PageHelper::getPages($pieCrust);
            $foundAny |= $this->findPages($context, $pages);
        }

        // Find posts.
        if ($returnAllTypes or $result->command->options['posts'])
        {
            $blogKeys = $pieCrust->getConfig()->getValue('site/blogs');
            if ($result->command->options['blog'])
                $blogKeys = array($result->command->options['blog']);

            foreach ($blogKeys as $blogKey)
            {
                $pages = PageHelper::getPosts($pieCrust, $blogKey);
                $pagesIterator = new \ArrayIterator($pages);
                $sorter = new DateSortIterator($pagesIterator);
                $pages = iterator_to_array($sorter);
                $foundAny |= $this->findPages($context, $pages, $blogKey);
            }
        }

        // Find templates.
        if ($returnAllTypes or $result->command->options['templates'])
        {
            $templatesDirs = $pieCrust->getTemplatesDirs();
            foreach ($templatesDirs as $dir)
            {
                $foundAny |= $this->findTemplates($context, $dir);
            }
        }

        if (!$foundAny)
        {
            $pattern = $result->command->args['pattern'];
            $logger->info("No match found for '{$pattern}'.");
        }

        return 0;
    }

    private function findPages($context, $pages, $fromBlog = false)
    {
        $logger = $context->getLog();
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        $rootDir = $pieCrust->getRootDir();
        $rootDir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $rootDir);
        $exact = $result->command->options['exact'];
        $pattern = $result->command->args['pattern'];
        $fullPath = $result->command->options['full_path'];
        $returnComponents = $result->command->options['page_components'];

        $foundAny = false;

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
            {
                if (substr($path, 0, strlen($rootDir)) == $rootDir)
                    $path = PieCrustHelper::getRelativePath($pieCrust, $path);
            }

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
                    $timestamp = $page->getDate(true);
                    $components['type'] = 'post';
                    $components['year'] = date('Y', $timestamp);
                    $components['month'] = date('m', $timestamp);
                    $components['day'] = date('d', $timestamp);
                    $components['hour'] = date('H', $timestamp);
                    $components['minute'] = date('i', $timestamp);
                    $components['second'] = date('s', $timestamp);

                    $matches = array();
                    $postsPattern = UriBuilder::buildPostUriPattern(
                        $pieCrust->getConfig()->getValue($fromBlog . '/post_url'),
                        $fromBlog
                    );
                    if (preg_match($postsPattern, $page->getUri(), $matches))
                    {
                        $components['slug'] = $matches['slug'];
                    }
                }

                foreach ($components as $k => $v)
                {
                    $logger->info("{$k}: {$v}");
                }
                $logger->info("");
                $foundAny = true;
            }
            else
            {
                $logger->info($path);
                $foundAny = true;
            }
        }

        return $foundAny;
    }

    private function findTemplates($context, $templateDir)
    {
        $logger = $context->getLog();
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        $rootDir = $pieCrust->getRootDir();
        $rootDir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $rootDir);
        $exact = $result->command->options['exact'];
        $pattern = $result->command->args['pattern'];
        $fullPath = $result->command->options['full_path'];
        $returnComponents = $result->command->options['page_components'];

        $foundAny = false;
        $dirIt = new \RecursiveDirectoryIterator($templateDir);
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

        return $foundAny;
    }
}

