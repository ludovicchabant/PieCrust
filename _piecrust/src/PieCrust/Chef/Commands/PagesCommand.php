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


class PagesCommand extends ChefCommand
{
    public function getName()
    {
        return 'pages';
    }
    
    public function setupParser(Console_CommandLine $parser)
    {
        $parser->description = "Lists all pages and posts in the website, with optional filtering features.";
        $parser->addArgument('pattern', array(
            'description' => "The pattern to match.",
            'help_name'   => 'PATTERN',
            'optional'    => true
        ));
        $parser->addOption('pages_only', array(
            'long_name'   => '--pages',
            'description' => "Only return regular pages.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('posts_only', array(
            'long_name'   => '--posts',
            'description' => "Only return posts.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('blog', array(
            'long_name'   => '--blog',
            'description' => "Only return posts from the given blog.",
            'help_name'   => 'BLOG',
            'default'     => null
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

        // Validate options.
        if ($result->command->options['posts_only'] and
            $result->command->options['pages_only'])
            throw new PieCrustException("Can't specify both '--posts' and '--pages'.");

        // Get the regex pattern.
        $pattern = $result->command->args['pattern'];
        if ($pattern)
        {
            $pattern = PathHelper::globToRegex($pattern);
        }

        // Get the pages.
        $pages = array();
        if (!$result->command->options['posts_only'])
        {
            $pages = PageHelper::getPages($pieCrust);
        }
        if (!$result->command->options['pages_only'])
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
        $fullPath = $result->command->options['full_path'];
        foreach ($pages as $page)
        {
            if ($page->getUri() == PieCrustDefaults::CATEGORY_PAGE_NAME or
                $page->getUri() == PieCrustDefaults::TAG_PAGE_NAME)
                continue;

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

        return 0;
    }
}

