<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PathHelper;


class PrepareCommand extends ChefCommand
{
    public function getName()
    {
        return 'prepare';
    }

    public function setupParser(Console_CommandLine $parser)
    {
        $parser->description = "Helps with the creation of pages and posts.";
        $parser->addArgument('type', array(
            'description' => "Whether to create a page or post.",
            'help_name'   => 'TYPE',
            'optional'    => false
        ));
        $parser->addArgument('slug', array(
            'description' => "The slug of the new page or post.",
            'help_name'   => 'SLUG',
            'optional'    => false
        ));
        $parser->addOption('blog', array(
            'short_name'  => '-b',
            'long_name'   => '--blog',
            'description' => "Create a post for the given blog (default to the first declared blog).",
            'default'     => null,
            'help_name'   => 'BLOG'
        ));
    }

    public function run(ChefContext $context)
    {
        $result = $context->getResult();
        $log = $context->getLog();

        switch ($result->command->args['type'])
        {
        case 'post':
            $this->preparePost($context);
            break;
        case 'page':
            $this->preparePage($context);
            break;
        default:
            throw new PieCrustException("The preparation type must be 'page' or 'post'.");
        }
    }

    protected function preparePost(ChefContext $context)
    {
        $result = $context->getResult();
        $app = $context->getApp();
        $log = $context->getLog();

        // Create the posts directory if it doesn't exist.
        if ($app->getPostsDir() == false)
        {
            $postsDir = $app->getRootDir() . PieCrustDefaults::CONTENT_POSTS_DIR;
            $log->info("Creating posts directory: {$postsDir}");
            mkdir($postsDir, 0644, true);
            $app->setPostsDir($postsDir);
        }

        // Create the relative path of the new post by using the
        // path format of the website's post file-system.
        $slug = $result->command->args['slug'];
        $replacements = array(
            '%day%' => date('d'),
            '%month%' => date('m'),
            '%year%' => date('Y'),
            '%slug%' => $slug
        );
        $fs = FileSystem::create($app);
        $pathFormat = $fs->getPostPathFormat();
        $path = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $pathFormat
        );

        // Figure out which blog to create this post for (if the website
        // is hosting several blogs).
        $blogKey = $result->command->options['blog'];
        $blogKeys = $app->getConfig()->getValue('site/blogs');
        if ($blogKey == null)
        {
            $blogKey = $blogKeys[0];
        }
        else if (!in_array($blogKey, $blogKeys))
        {
            throw new PieCrustException("Specified blog '{$blogKey}' is not one of the known blogs in this website: " . implode(', ', $blogKeys));
        }

        // Get the blog subdir for the post.
        $blogSubDir = $blogKey . '/';
        if ($blogKey == PieCrustDefaults::DEFAULT_BLOG_KEY)
        {
            $blogSubDir = '';
        }

        // Create the full path.
        $fullPath = $app->getPostsDir() . $blogSubDir . $path;
        $relativePath = PathHelper::getRelativePath($app, $fullPath);
        if (file_exists($fullPath))
            throw new PieCrustException("Post already exists: {$relativePath}");
        $log->info("Creating new post: {$relativePath}");

        // Create the title and time of post.
        $title = preg_replace('/[\-_]+/', ' ', $slug);
        $title = ucwords($title);
        $time = date('H:i:s');

        // Write the contents.
        if (!is_dir(dirname($fullPath)))
            mkdir(dirname($fullPath), 0644, true);
        $f = fopen($fullPath, 'w');
        fwrite($f, "---\n");
        fwrite($f, "title: {$title}\n");
        fwrite($f, "time: {$time}\n");
        fwrite($f, "---\n");
        fwrite($f, "My new blog post!\n");
        fclose($f);
    }

    protected function preparePage(ChefContext $context)
    {
        $result = $context->getResult();
        $app = $context->getApp();
        $log = $context->getLog();

        // Create the pages directory if it doesn't exist.
        if ($app->getPagesDir() == false)
        {
            $pagesDir = $app->getRootDir() . PieCrustDefaults::CONTENT_PAGES_DIR;
            $log->info("Creating pages directory: {$pagesDir}");
            mkdir($pagesDir, 0644, true);
            $app->setPagesDir($pagesDir);
        }

        // Create the path of the new page.
        $slug = $result->command->args['slug'];
        $slug = ltrim($slug, '/\\');
        $fullPath = $app->getPagesDir() . $slug . '.html';
        $relativePath = PathHelper::getRelativePath($app, $fullPath);
        if (file_exists($fullPath))
            throw new PieCrustException("Page already exists: {$relativePath}");
        $log->info("Creating new page: {$relativePath}");

        // Create the title and date/time of post.
        $title = preg_replace('/[\-_]+/', ' ', $slug);
        $title = ucwords($title);
        $date = date('Y-m-d H:i');

        // Write the contents.
        if (!is_dir(dirname($fullPath)))
            mkdir(dirname($fullPath), 0644, true);
        $f = fopen($fullPath, 'w');
        fwrite($f, "---\n");
        fwrite($f, "title: {$title}\n");
        fwrite($f, "date: {$date}\n");
        fwrite($f, "---\n");
        fwrite($f, "A new page.\n");
        fclose($f);
    }
}

