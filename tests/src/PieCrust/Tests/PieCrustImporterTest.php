<?php

namespace PieCrust\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PieCrust\PieCrust;
use PieCrust\IO\FileSystemFactory;
use PieCrust\Interop\PieCrustImporter;
use PieCrust\Mock\MockFileSystem;


class PieCrustImporterTest extends PieCrustTestCase
{
    public function testImportWordpress()
    {
        $fs = MockFileSystem::create()
            ->withPagesDir()
            ->withPostsDir();
        $app = new PieCrust(array(
            'root' => $fs->getAppRoot()
        ));
        $importer = new PieCrustImporter($app);

        $sampleXml = PIECRUST_UNITTESTS_DATA_DIR . 
            'import/wordpress.test-data.2011-01-17.xml';
        $importer->import(
            'wordpress',
            $sampleXml,
            array()
        );

        // Re-create the app to load from the new values.
        $app = new PieCrust(array(
            'root' => $fs->getAppRoot()
        ));

        // Check the content.
        $pcFs = FileSystemFactory::create($app);
        $pageFiles = $pcFs->getPageFiles();
        $this->assertCount(11, $pageFiles);
        $postFiles = $pcFs->getPostFiles('blog');
        $this->assertCount(22, $postFiles);
    }
}

