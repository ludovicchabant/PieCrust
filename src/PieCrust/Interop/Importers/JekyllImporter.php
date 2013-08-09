<?php

namespace PieCrust\Interop\Importers;

use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\Configuration;
use PieCrust\Util\PieCrustHelper;


/**
 * A class that imports content from a Jekyll blog.
 */
class JekyllImporter extends ImporterBase
{
    protected $rootDir;

    protected $pageExtensions;

    protected $posts;
    protected $pages;
    protected $layouts;
    protected $includes;
    protected $static;

    protected $modified;

    public function __construct()
    {
        parent::__construct(
            'jekyll', 
            "Imports pages and posts from a Jekyll blog.",
            "The source must be a path to the root of a Jekyll website.");

        $this->pageExtensions = array('htm', 'html', 'textile', 'markdown');
    }

    // Importer functions {{{

    protected function open($connection)
    {
        if (!is_dir($connection))
            throw new PieCrustException("The given directory doesn't exist: {$connection}");
        $this->rootDir = rtrim($connection, '/\\') . DIRECTORY_SEPARATOR;

        $this->pages = array();
        $this->posts = array();
        $this->static = array();
        $this->layouts = array();
        $this->includes = array();

        $this->readDirectory();

        $this->logger->debug("Got ".count($this->pages)." pages, ".count($this->posts)." posts and ".count($this->static)." static files.");
    }

    protected function setupConfig($configPath)
    {
        $path = $this->rootDir . '_config.yml';
        if (!file_exists($path))
            return;

        // Ruby's YAML library seems to support unindented
        // lists whereas Symfony's YAML library doesn't.
        $markup = file_get_contents($path);
        $markup = preg_replace(
            '/^\-\s/m',
            '  - ',
            $markup
        );
        $config = Yaml::parse($markup);

        if (!isset($config['site']))
            $config['site'] = array();
        // Flat file organization for posts.
        $config['site']['posts_fs'] = 'flat';
        // Separate layouts from includes.
        $config['site']['templates_dirs'] = array('_content/includes');
        // Tag listing URL format.
        $config['site']['tag_url'] = 'tags/%tag%';
        // Posts URL format.
        if (isset($config['permalink']))
        {
            // Handle predefined permalinks.
            $permalink = $config['permalink'];
            if ($permalink == 'date') $permalink = "/:categories/:year/:month/:day/:title.html";
            if ($permalink == 'pretty') $permalink = "/:categories/:year/:month/:day/:title/";
            if ($permalink == 'none') $permalink = "/:categories/:title.html";

            //TODO: handle `:categories` tag.
            $postUrl = str_replace(
                array(':year', ':month', ':day', ':title', ':categories'),
                array('%year%', '%month%', '%day%', '%slug%', ''),
                $permalink
            );
            $postUrl = trim(str_replace('//', '/', $postUrl), '/');
            $config['site']['post_url'] = $postUrl;
        }
        // Excluded files.
        if (isset($config['exclude']))
        {
            if (!isset($config['baker']))
                $config['baker'] = array();
            $config['baker']['skip_patterns'] = array_filter(
                $config['exclude'],
                function ($i) {
                    return '/^' . preg_quote($i, '/') . '/';
                }
            );
        }

        // Do not auto-escape content in Twig.
        if (!isset($config['twig']))
            $config['twig'] = array();
        $config['twig']['auto_escape'] = false;

        // We need to wait a second before saving the PieCrust config
        // to avoid cache problems. This is because the cache for the
        // config was potentially created when running the current
        // `import` command, and that could have happened only a couple
        // milliseconds before now. If we save the config file right
        // away, the timestamp difference may be 0, as far as the
        // OS file-system goes, and the next time PieCrust runs it
        // wouldn't see the updated config.
        sleep(1);

        $contents = Yaml::dump($config, 3);
        file_put_contents($configPath, $contents);
    }

    protected function importPages($pagesDir)
    {
        foreach ($this->pages as $page)
        {
            $isIndex = false;
            $isStatic = false;
            if (preg_match('/^index\.('.implode('|', $this->pageExtensions).')$/', $page))
            {
                $outputPath = $pagesDir . PieCrustDefaults::INDEX_PAGE_NAME . '.html';
                $isIndex = true;
            }
            else
            {
                $pathinfo = pathinfo($page);
                $subDir = '';
                if ($pathinfo['dirname'] != '' and $pathinfo['dirname'] != '.')
                    $subDir = $pathinfo['dirname'] . DIRECTORY_SEPARATOR;
                $outputPath = $pagesDir . $subDir . $pathinfo['filename'] . '.html';

                // There could be static files (like `scss` or `less` files) that look
                // like page files because they have a YAML front matter.
                $isStatic = array_search($pathinfo['extension'], $this->pageExtensions) === false;
                if ($isStatic)
                    $outputPath = $this->pieCrust->getRootDir() . $page;
            }

            if ($isStatic)
            {
                $this->convertToStatic($page, $outputPath);
            }
            else
            {
                $this->convertPage($page, $outputPath);
                if ($isIndex)
                {
                    // Copy the index page to the tag listing page.
                    copy($outputPath, $pagesDir . PieCrustDefaults::TAG_PAGE_NAME . '.html');
                }
            }
        }
    }

    protected function importTemplates($templatesDirs)
    {
        $layoutTemplates = $this->pieCrust->getRootDir() . '_content/templates/';
        if (!is_dir($layoutTemplates))
            mkdir($layoutTemplates, 0755, true);
        foreach ($this->layouts as $layout)
        {
            $outputPath = $layoutTemplates . pathinfo($layout, PATHINFO_BASENAME);
            $this->convertPage($layout, $outputPath, true);
        }

        $includeTemplates = $this->pieCrust->getRootDir() . '_content/includes/';
        if (!is_dir($includeTemplates))
            mkdir($includeTemplates, 0755, true);
        foreach ($this->includes as $include)
        {
            $outputPath = $includeTemplates . pathinfo($include, PATHINFO_BASENAME);
            $this->convertPage($include, $outputPath, true);
        }
    }

    protected function importPosts($postsDir)
    {
        foreach ($this->posts as $post)
        {
            $filename = pathinfo($post, PATHINFO_FILENAME);
            // PieCrust uses an underscore to separate the date from the slug.
            $filename = preg_replace('/(\d{4}\-\d{2}\-\d{2})\-(.*)$/', '\\1_\\2', $filename);
            $outputPath = $postsDir . $filename . '.html';
            $this->convertPage($post, $outputPath);
        }
    }

    protected function importStatic($rootDir)
    {
        foreach ($this->static as $static)
        {
            $source = $this->rootDir . $static;
            $destination = $this->pieCrust->getRootDir() . $static;
            if (!is_dir(dirname($destination)))
                mkdir(dirname($destination), 0755, true);
            copy($source, $destination);
        }
    }

    protected function close()
    {
        $this->logger->info("The Jekyll website was successfully imported.");

        // Print the converted pages.
        $twigConverted = array_filter(
            $this->modified, 
            function ($i) { return isset($i['liquid_to_twig']); }
        );
        if (count($twigConverted) > 0)
        {
            $this->logger->info("");
            $this->logger->info("The following pages were converted to the Twig syntax:");
            foreach ($twigConverted as $relative => $mod)
            {
                $this->logger->info(" - {$relative}");
            }
            $this->logger->info("Those files have a backup of their original content next to them, so you can review the automatic changes.");
        }

        $this->logger->info("");
    }

    // }}}

    // Scanning functions {{{

    protected function readDirectory($dir = '', $action = null)
    {
        if ($action == null)
        {
            $action = array($this, 'readPage');
        }

        $it = new \FilesystemIterator($this->rootDir . $dir);
        foreach ($it as $path)
        {
            if (substr($path->getFilename(), 0, 1) == '.')
                continue;

            $relative = $dir . DIRECTORY_SEPARATOR . $path->getFilename();
            $relative = ltrim($relative, '/\\');

            if ($path->isDir())
            {
                if ($relative == '_layouts')
                {
                    $this->readDirectory($relative, array($this, 'readLayout'));
                }
                else if ($relative == '_includes')
                {
                    $this->readDirectory($relative, array($this, 'readInclude'));
                }
                else if ($relative == '_posts')
                {
                    $this->readDirectory($relative, array($this, 'readPost'));
                }
                else
                {
                    $this->readDirectory($relative);
                }
            }
            else if ($path->isFile() and !$path->isLink() and $relative != '_config.yml')
            {
                call_user_func($action, $relative);
            }
        }
    }

    protected function readPage($relative)
    {
        $absolute = $this->rootDir . $relative;
        $beginning = file_get_contents($absolute, false, null, 0, 3);
        if ($beginning === false)
        {
            $this->logger->err("Can't read: {$absolute}");
            continue;
        }
        if ($beginning === '---')
        {
            $this->pages[] = $relative;
        }
        else
        {
            $this->static[] = $relative;
        }
    }

    protected function readPost($relative)
    {
        $this->posts[] = $relative;
    }

    protected function readLayout($relative)
    {
        $this->layouts[] = $relative;
    }

    protected function readInclude($relative)
    {
        $this->includes[] = $relative;
    }

    // }}}

    // Conversion functions {{{

    protected function convertToStatic($relative, $outputPath)
    {
        $this->logger->debug("Converting {$relative}");

        $absolute = $this->rootDir . $relative;
        $contents = file_get_contents($absolute);
        $header = Configuration::parseHeader($contents);
        $text = substr($contents, $header->textOffset);

        $pieCrustRelative = PieCrustHelper::getRelativePath($this->pieCrust, $outputPath);
        $this->logger->debug(" -> {$pieCrustRelative}");
        if (!is_dir(dirname($outputPath)))
            mkdir(dirname($outputPath), 0755, true);
        file_put_contents($outputPath, $text);
    }

    protected function convertPage($relative, $outputPath, $isTemplate = false)
    {
        $this->logger->debug("Converting {$relative}");

        $pieCrustRelative = PieCrustHelper::getRelativePath($this->pieCrust, $outputPath);
        $this->logger->debug(" -> {$pieCrustRelative}");
        $this->modified[$pieCrustRelative] = array();

        $absolute = $this->rootDir . $relative;
        $contents = file_get_contents($absolute);

        $wrapContentTag = true;
        $header = Configuration::parseHeader($contents);
        $text = substr($contents, $header->textOffset);
        $textBeforeConversion = $text;

        if ($isTemplate)
        {
            $config = $header->config;
            if (isset($config['layout']))
            {
                // Liquid doesn't support template inheritance,
                // but Twig does.
                $text = "{% extends '{$config['layout']}.html' %}\n\n" . 
                    "{% block jekyllcontent %}\n" .
                    $text . "\n" .
                    "{% endblock %}\n";
                $wrapContentTag = false;
                $this->modified[$pieCrustRelative]['layout_extends'] = true;
            }
        }
        else
        {
            // Convert the config.
            $config = $header->config;
            if (isset($config['layout']))
            {
                // PieCrust uses 'none' instead of 'nil'.
                if ($config['layout'] == 'nil')
                    $config['layout'] = 'none';
            }
            // PieCrust defines everything in the config header,
            // including the format of the text.
            $pathinfo = pathinfo($relative);
            if ($pathinfo['extension'] != 'html')
                $config['format'] = $pathinfo['extension'];
            else
                $config['format'] = 'none';
        }

        // Convert the template stuff we can:
        // - content tag may have to be wrapped in a `jekyllcontent` 
        //   because Jekyll uses implicit layout inheritance 
        //   placements.
        if ($wrapContentTag)
        {
            $text = preg_replace(
                '/{{\s*content\s*}}/', 
                '{% block jekyllcontent %}{{ content }}{% endblock %}', 
                $text);
        }
        // - list of posts
        $text = preg_replace(
            '/(?<=\{%|{)([^\}]*)site.posts/',
            '\\1blog.posts', 
            $text);
        $text = preg_replace(
            '/(?<=\{%|{)([^\}]*)paginator.posts/',
            '\\1pagination.posts', 
            $text);
        // - list of categories or tags
        $text = preg_replace(
            '/(?<=\{%|{)([^\}]*)site.categories/',
            '\\1blog.categories', 
            $text);
        $text = preg_replace(
            '/(?<=\{%|{)([^\}]*)site.tags/',
            '\\1blog.tags', 
            $text);
        // - list of related posts
        $text = preg_replace(
            '/(?<=\{%|{)(?<!%\})site.related_posts/',
            '\\1pagination.related_posts', 
            $text);
        // - enumeration limits
        $text = preg_replace(
            '/{%\s*for\s+([^}]+)\s+limit\:\s*(\d+)/', 
            '{% for \\1 | slice(0, \\2)', 
            $text);
        $text = preg_replace(
            '/{%\s*for\s+([^}]+)\s+offset\:\s*(\d+)/', 
            '{% for \\1 | slice(\\2)', 
            $text);
        // - code highlighting
        $text = preg_replace(
            '/{%\s*highlight\s+([\w\d]+)\s*%}/', 
            '{% geshi \'\\1\' %}', 
            $text);
        $text = preg_replace(
            '/{%\s*endhighlight\s*%}/', 
            '{% endgeshi %}', 
            $text);
        // - unless tag
        $text = preg_replace(
            '/{%\s*unless\s+([^}]+)\s*%}/', 
            '{% if not \\1 %}', 
            $text);
        $text = preg_replace(
            '/{%\s*endunless\s*%}/', 
            '{% endif %}', 
            $text);
        // - variable assignment
        $text = preg_replace(
            '/\{%\s*assign\s+/',
            '{% set ',
            $text);
        // - include tag
        $text = preg_replace(
            '/\{%\s*include\s+([\w\d\.\-_]+)\s*%}/',
            '{% include "\\1" %}',
            $text);
        // - truncate filter
        $text = preg_replace(
            '/\|\s*truncate\:\s*(\d+)/',
            '|slice(0, \\1)',
            $text);
        // - date filter
        $text = preg_replace(
            '/\|\s*date\:\s*"([^"]+)"/',
            '|date("\\1")',
            $text);
        // - some filters we don't need
        $text = preg_replace(
            '/\|\s*date_to_string/', 
            '', 
            $text);

        // Create the destination directory if needed.
        if (!is_dir(dirname($outputPath)))
            mkdir(dirname($outputPath), 0755, true);

        // Create a backup file if we converted a lot of stuff.
        if ($text != $textBeforeConversion)
        {
            $this->modified[$pieCrustRelative]['liquid_to_twig'] = true;

            // Add a backup of the original content.
            $backupPath = $outputPath . '.original';
            file_put_contents($backupPath, $contents);
        }

        // Save the converted contents.
        $convertedContents = '';
        if (!$isTemplate and count($config) > 0)
        {
            $convertedContents .= "---\n";
            $convertedContents .= Yaml::dump($config, 3);
            $convertedContents .= "---\n";
        }
        $convertedContents .= $text;
        file_put_contents($outputPath, $convertedContents);
    }

    // }}}
}

