<?php

namespace PieCrust\Interop\Importers;

use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;


/**
 * Base class for most importers.
 */
abstract class ImporterBase implements IImporter
{
    protected $name;
    protected $description;
    protected $helpTopic;

    protected $logger;
    protected $pieCrust;
    protected $connection;
    protected $options;

    /**
     * Builds a new instance of ImporterBase.
     */
    protected function __construct($name, $description, $helpTopic)
    {
        $this->name = $name;
        $this->description = $description;
        $this->helpTopic = $helpTopic;
        $this->options = array();
    }

    /**
     * Gets the name of this importer.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the description of this importer.
     */
    public function getDescription()
    {
        return $this->description;
    }
    
    /**
     * Gets the help topic for this importer.
     */
    public function getHelpTopic()
    {
        return $this->helpTopic;
    }

    /**
     * Sets up any custom options for the importer.
     */
    public function setupParser(\Console_CommandLine $parser)
    {
    }

    /**
     * Imports the website.
     */
    public function import(IPieCrust $pieCrust, $connection, $logger, $options = array())
    {
        if ($logger == null)
            throw new PieCrustException("No logger was given for this importer.");
        $this->logger = $logger;

        $this->pieCrust = $pieCrust;
        $this->connection = $connection;
        $this->options = $options;

        $didClose = false;
        $this->open($connection);
        try
        {
            $this->setupConfig($this->pieCrust->getRootDir() . PieCrustDefaults::CONFIG_PATH);
            $this->importPages($this->pieCrust->getPagesDir());
            $this->importTemplates($this->pieCrust->getTemplatesDirs());
            $this->importPosts($this->pieCrust->getPostsDir());
            $this->importStatic($this->pieCrust->getRootDir());

            $didClose = true;
            $this->close();
        }
        catch (Exception $e)
        {
            $this->logger->error($e->getMessage());
            if (!$didClose)
                $this->close();
        }
    }

    // Abstract functions {{{

    protected abstract function open($connection);
    protected abstract function importPages($pagesDir);
    protected abstract function importTemplates($templatesDirs);
    protected abstract function importPosts($postsDir);
    protected abstract function importStatic($rootDir);
    protected abstract function close();
    
    // }}}

    // Extension functions {{{

    protected function setupConfig($configPath)
    {
    }

    protected function getCleanSlug($name)
    {
        return $name;
    }

    // }}}

    // Utility functions {{{
    
    protected function createPage($pagesDir, $name, $timestamp, $metadata, $content)
    {
        // Get a clean name.
        $name = $this->getCleanSlug($name);

        // Come up with the filename.
        $filename = $pagesDir . $name . '.html';

        // Build the config data that goes in the header.
        $configData = $metadata;
        $configData['date'] = date('Y-m-d', $timestamp);
        $configData['time'] = date('H:i:s', $timestamp);

        // Write it!
        $this->writePageFile($configData, $content, $filename);
    }

    protected function createPost($postsDir, $name, $timestamp, $metadata, $content)
    {
        // Get a clean name.
        $name = $this->getCleanSlug($name);

        // Come up with the filename.
        $fs = $this->pieCrust->getEnvironment()->getFileSystem();
        $captureGroups = array(
            'year' => date('Y', $timestamp),
            'month' => date('m', $timestamp),
            'day' => date('d', $timestamp),
            'slug' => $name,
            'ext' => 'html'
        );
        $pathInfo = $fs->getPostPathInfo(
            PieCrustDefaults::DEFAULT_BLOG_KEY, 
            $captureGroups,
            FileSystem::PATHINFO_CREATING
        );
        $postPath = $pathInfo['path'];

        // Build the config data that goes in the header.
        $configData = $metadata;
        if (!isset($configData['time']))
            $configData['time'] = date('H:i:s', $timestamp);

        // Write it!
        $this->writePageFile($configData, $content, $postPath);
    }

    protected function writePageFile($configData, $content, $filename)
    {
        // Get the YAML string for the config data.
        $header = Yaml::dump($configData, 3);

        // Write the post's contents.
        $this->logger->info(" > " . pathinfo($filename, PATHINFO_FILENAME));
        if (!is_dir(dirname($filename)))
            mkdir(dirname($filename), 0777, true);
        $f = fopen($filename, 'w');
        fwrite($f, "---\n");
        fwrite($f, $header);
        fwrite($f, "---\n");
        fwrite($f, $content);
        fclose($f);

    }

    // }}}
}
