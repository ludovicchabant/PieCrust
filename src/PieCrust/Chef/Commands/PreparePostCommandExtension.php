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


class PreparePostCommandExtension extends ChefCommandExtension
{
    public function getName()
    {
        return 'post';
    }

    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Creates a post.";
        $parser->addArgument('slug', array(
            'description' => "The slug of the new post.",
            'help_name'   => 'SLUG',
            'optional'    => false
        ));
        $parser->addOption('blog', array(
            'short_name'  => '-b',
            'long_name'   => '--blog',
            'description' => "Create a post for the given blog (defaults to the first declared blog).",
            'default'     => null,
            'help_name'   => 'BLOG'
        ));
    }

    public function run(ChefContext $context)
    {
        $result = $context->getResult();
        $app = $context->getApp();
        $log = $context->getLog();

        // Create the posts directory if it doesn't exist.
        if ($app->getPostsDir() == false)
        {
            $postsDir = $app->getRootDir() . PieCrustDefaults::CONTENT_POSTS_DIR;
            $log->info("Creating posts directory: {$postsDir}");
            mkdir($postsDir, 0777, true);
            $app->setPostsDir($postsDir);
        }
        
        // Get the post template.
        $templateRelPath = 'prepare/post';
        $ext = 'html';
        $templatePath = PieCrustDefaults::RES_DIR() . $templateRelPath . '.' . $ext;
        
        // Look for the post template in the various supported formatter extensions.
        $formats = $app->getConfig()->getValue('site/auto_formats');
        $extensions = array_keys($formats);
        foreach ($extensions as $maybe_ext) {
            $alternativeTemplatePath = PieCrustDefaults::CONTENT_DIR . $templateRelPath . '.' . $maybe_ext;
            if (file_exists($alternativeTemplatePath)) {
                $templatePath = $alternativeTemplatePath;
                $ext = $maybe_ext;
                break;
            }
        }

        // Create the relative path of the new post by using the
        // path format of the website's post file-system.
        $slug = $result->command->command->args['slug'];
        $captureGroups = array(
            'day' => date('d'),
            'month' => date('m'),
            'year' => date('Y'),
            'slug' => $slug,
            'ext' => $ext
        );

        // Figure out which blog to create this post for (if the website
        // is hosting several blogs).
        $blogKey = $result->command->command->options['blog'];
        $blogKeys = $app->getConfig()->getValue('site/blogs');
        if ($blogKey == null)
        {
            $blogKey = $blogKeys[0];
        }
        else if (!in_array($blogKey, $blogKeys))
        {
            throw new PieCrustException("Specified blog '{$blogKey}' is not one of the known blogs in this website: " . implode(', ', $blogKeys));
        }

        // Create the full path.
        $fs = $app->getEnvironment()->getFileSystem();
        $pathInfo = $fs->getPostPathInfo(
            $blogKey,
            $captureGroups,
            FileSystem::PATHINFO_CREATING
        );

        $fullPath = $pathInfo['path'];
        $relativePath = PieCrustHelper::getRelativePath($app, $fullPath);
        if (file_exists($fullPath))
            throw new PieCrustException("Post already exists: {$relativePath}");
        $log->info("Creating new post: {$relativePath}");

        // Create the title.
        $title = preg_replace('/[\-_]+/', ' ', $slug);
        $title = ucwords($title);
        
        // Read in the template
        $template = file_get_contents($templatePath);

        // Render the template with the default template engine
        $engine = PieCrustHelper::getTemplateEngine($app, 'html');
        ob_start();
        $engine->renderString($template, compact('title'));
        $output = ob_get_clean();

        // Write the contents.
        if (!is_dir(dirname($fullPath)))
            mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $output);
    }
}

