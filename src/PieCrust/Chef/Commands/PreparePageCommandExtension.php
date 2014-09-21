<?php

namespace PieCrust\Chef\Commands;

use \Exception;
use \Console_CommandLine;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Util\PieCrustHelper;


class PreparePageCommandExtension extends ChefCommandExtension
{
    public function getName()
    {
        return 'page';
    }

    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Creates a page.";
        $parser->addArgument('slug', array(
            'description' => "The slug of the new page.",
            'help_name'   => 'SLUG',
            'optional'    => false
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
        
        // Get the post template.
        $templateRelPath = 'prepare/page';
        $ext = 'html';
        $templatePath = PieCrustDefaults::RES_DIR() . $templateRelPath . '.' . $ext;
        
        // Look for the page template in the various supported formatter extensions.
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

        // Create the path of the new page.
        $slug = $result->command->command->args['slug'];
        $slug = ltrim($slug, '/\\');
        $fullPath = $app->getPagesDir() . $slug;
        if (!preg_match('/\.[a-z0-9]+$/i', $slug))
           $fullPath .= '.' . $ext;
        $relativePath = PieCrustHelper::getRelativePath($app, $fullPath);
        if (file_exists($fullPath))
            throw new PieCrustException("Page already exists: {$relativePath}");
        $log->info("Creating new page: {$relativePath}");

        // Create the title and date/time of post.
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

