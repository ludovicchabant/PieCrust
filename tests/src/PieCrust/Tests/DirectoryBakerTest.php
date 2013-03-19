<?php

namespace PieCrust\Tests;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Baker\DirectoryBaker;
use PieCrust\Baker\ProcessingTreeBuilder;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\Baker\Processors\CopyFileProcessor;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;
use PieCrust\Mock\MockPlugin;
use PieCrust\Mock\MockProcessor;


class DirectoryBakerTest extends PieCrustTestCase
{
    public function testEmptyBake()
    {
        $fs = MockFileSystem::create(false)
            ->withDir('kitchen')
            ->withDir('counter');

        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('kitchen');
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();

        $bakeDir = $fs->url('counter');
        $parameters = array(
            'processors' => array('copy')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $this->assertEquals(
            array($fs->getRootName() => array(
                'kitchen' => array(),
                'counter' => array()
            )),
            $fs->getStructure()
        );
        $baker->bake();
        $this->assertEquals(
            array($fs->getRootName() => array(
                'kitchen' => array(),
                'counter' => array()
            )),
            $fs->getStructure()
        );
        $this->assertEmpty($baker->getBakedFiles());
    }
    
    public function testOneFileBake()
    {
        $fs = MockFileSystem::create(false)
            ->withFile('kitchen/something.html', 'This is some test file.')
            ->withDir('cache')
            ->withDir('counter');
        
        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('kitchen/');
        $pc->cacheDir = $fs->url('cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();

        $bakeDir = $fs->url('counter');
        $parameters = array(
            'processors' => array('copy')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $this->assertEquals(
            array($fs->getRootName() => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.'
                ),
                'counter' => array(),
                'cache' => array())
            ),
            $fs->getStructure()
        );
        $baker->bake();
        $this->assertEquals(
            array($fs->getRootName() => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.'
                ),
                'counter' => array(
                    'something.html' => 'This is some test file.'
                ),
                'cache' => array()
            )),
            $fs->getStructure()
        );
        $this->assertEquals(
            array(
                $fs->url('kitchen/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array($fs->url('counter/something.html')),
                    'was_baked' => true,
                    'was_overridden' => false
                )
            ),
            $baker->getBakedFiles()
        );
    }
    
    public function testOneFileBakeInsideKitchen()
    {
        $fs = MockFileSystem::create(false)
            ->withDir('kitchen/_counter')
            ->withDir('kitchen/_cache')
            ->withFile('kitchen/something.html', "This is some test file.");
        
        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('kitchen/');
        $pc->cacheDir = $fs->url('kitchen/_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();

        $bakeDir = $fs->url('kitchen/_counter');
        $parameters = array(
            'processors' => array('copy'),
            'skip_patterns' => array('/_counter/')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $this->assertEquals(
            array($fs->getRootName() => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.',
                    '_counter' => array(),
                    '_cache' => array()
                ))
            ),
            $fs->getStructure()
        );
        $baker->bake();
        $this->assertEquals(
            array($fs->getRootName() => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.',
                    '_counter' => array(
                        'something.html' => 'This is some test file.'
                    ),
                    '_cache' => array()
                )
            )),
            $fs->getStructure()
        );
        $this->assertEquals(
            array(
                $fs->url('kitchen/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array($fs->url('kitchen/_counter/something.html')),
                    'was_baked' => true,
                    'was_overridden' => false
                )
            ),
            $baker->getBakedFiles()
        );
    }

    public function testSkipPattern()
    {
        $fs = MockFileSystem::create(false)
            ->withAsset('something.html', 'This is a test page.')
            ->withAsset('_hidden.html', 'This is hidden')
            ->withAsset('subdir/_important.html', 'This should not be hidden.')
            ->withDir('_counter')
            ->withDir('_cache');
        
        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('kitchen/');
        $pc->cacheDir = $fs->url('_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();

        $bakeDir = $fs->url('_counter');
        $parameters = array(
            'processors' => array('copy'),
            'skip_patterns' => array('/^_/')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $baker->bake();
        $this->assertTrue(is_file($fs->url('_counter/something.html')));
        $this->assertTrue(is_file($fs->url('_counter/subdir/_important.html')));
        $this->assertFalse(is_file($fs->url('_counter/_hidden.html')));
        $this->assertEquals(
            array(
                $fs->url('kitchen/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array($fs->url('_counter/something.html')),
                    'was_baked' => true,
                    'was_overridden' => false
                ),
                $fs->url('kitchen/subdir/_important.html') => array(
                    'relative_input' => 'subdir/_important.html',
                    'relative_outputs' => array('subdir/_important.html'),
                    'outputs' => array($fs->url('_counter/subdir/_important.html')),
                    'was_baked' => true,
                    'was_overridden' => false
                )
            ),
            $baker->getBakedFiles()
        );
    }

    public function testUpToDate()
    {
        $fs = MockFileSystem::create(false)
            ->withFile('something.html', 'This is a test page.')
            ->withDir('_counter')
            ->withDir('_cache');

        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('');
        $pc->cacheDir = $fs->url('_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = $fs->url('_counter');
        $parameters = array(
            'processors' => array('copy')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $outFile = $fs->url('_counter/something.html');
        $this->assertFalse(is_file($outFile));
        sleep(1);
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals('This is a test page.', file_get_contents($outFile));
        $this->assertEquals(
            array(
                $fs->url('something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array($fs->url('_counter/something.html')),
                    'was_baked' => true,
                    'was_overridden' => false
                )
            ),
            $baker->getBakedFiles()
        );
        clearstatcache();
        $mtime = filemtime($outFile);
        $this->assertGreaterThan(filemtime($fs->url('something.html')), $mtime);

        sleep(1);
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals(
            array(
                $fs->url('something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array($fs->url('_counter/something.html')),
                    'was_baked' => false,
                    'was_overridden' => false
                )
            ),
            $baker->getBakedFiles()
        );
        clearstatcache();
        $this->assertEquals($mtime, filemtime($outFile));
        $this->assertEquals('This is a test page.', file_get_contents($outFile));

        sleep(1);
        file_put_contents($fs->url('something.html'), 'New content!');
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals(
            array(
                $fs->url('something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array($fs->url('_counter/something.html')),
                    'was_baked' => true,
                    'was_overridden' => false
                )
            ),
            $baker->getBakedFiles()
        );
        clearstatcache();
        $this->assertGreaterThan($mtime, filemtime($outFile));
        $this->assertEquals('New content!', file_get_contents($outFile));
    }
    
    public function testForceBake()
    {
        $fs = MockFileSystem::create(false)
            ->withFile('forced.html', 'This is a test page.')
            ->withDir('_counter')
            ->withDir('_cache');

        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('');
        $pc->cacheDir = $fs->url('_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = $fs->url('_counter');
        $parameters = array(
            'processors' => array('copy'),
            'force_patterns' => array('/forced/')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $outFile = $fs->url('_counter/forced.html');
        $this->assertFalse(is_file($outFile));
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals('This is a test page.', file_get_contents($outFile));
        $mtime = filemtime($outFile);

        sleep(1);
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        clearstatcache();
        $this->assertGreaterThan($mtime, filemtime($outFile));
        $this->assertEquals('This is a test page.', file_get_contents($outFile));
    }

    public function testChainedProcessors()
    {
        $fs = MockFileSystem::create(false)
            ->withFile('something.foo', 'Some contents.')
            ->withDir('_cache')
            ->withDir('_counter');

        $pc = new MockPieCrust();
        $pc->rootDir = $fs->url('');
        $pc->cacheDir = $fs->url('_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new MockProcessor(
            'uppercase',
            'foo',
            function ($c) { return strtoupper($c); },
            IProcessor::PRIORITY_HIGH
        );
        $pc->getPluginLoader()->processors[] = new MockProcessor(
            'prefix',
            'html',
            function ($c) { return 'prefixed: ' . $c; }
        );
        $pc->getPluginLoader()->processors[] = new MockProcessor(
            'wraptag',
            array('foo' => 'html'),
            function ($c) { return "<b>{$c}</b>"; }
        );
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = $fs->url('_counter');
        $parameters = array(
            'processors' => array('*')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $outFile = $fs->url('_counter/something.html');
        $this->assertFalse(is_file($outFile));
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals('prefixed: <b>SOME CONTENTS.</b>', file_get_contents($outFile));
    }

    public function testProcessingTree()
    {
        $fs = MockFileSystem::create(false)
            ->withFile('something.foo', 'Some contents.')
            ->withDir('_cache')
            ->withDir('_counter');

        // Order by priority by hand...
        $processors = array();
        $processors[] = new MockProcessor(
            'uppercase',
            'foo',
            function ($c) { return strtoupper($c); },
            IProcessor::PRIORITY_HIGH
        );
        $processors[] = new MockProcessor(
            'prefix',
            'html',
            function ($c) { return 'prefixed: ' . $c; }
        );
        $processors[] = new MockProcessor(
            'wraptag',
            array('foo' => 'html'),
            function ($c) { return "<b>{$c}</b>"; }
        );
        $processors[] = new CopyFileProcessor();

        $builder = new ProcessingTreeBuilder($processors);
        $treeRoot = $builder->build('something.foo');

        $this->assertEquals('something.foo', $treeRoot->getPath());
        $this->assertEquals('uppercase', $treeRoot->getProcessor()->getName());
        $outputs = $treeRoot->getOutputs();
        $this->assertEquals(1, count($outputs));
        $this->assertEquals('something.foo', $outputs[0]->getPath());
        $this->assertEquals('wraptag', $outputs[0]->getProcessor()->getName());
        $outputs = $outputs[0]->getOutputs();
        $this->assertEquals(1, count($outputs));
        $this->assertEquals('something.html', $outputs[0]->getPath());
        $this->assertEquals('prefix', $outputs[0]->getProcessor()->getName());
        $outputs = $outputs[0]->getOutputs();
        $this->assertEquals(1, count($outputs));
        $this->assertEquals('something.html', $outputs[0]->getPath());
        $this->assertEquals('copy', $outputs[0]->getProcessor()->getName());
    }

    public function testProcessorOrdering()
    {
        $first = new MockProcessor('FIRST', 'ext', null, IProcessor::PRIORITY_HIGH);
        $second = new MockProcessor('SECOND', 'ext', null, IProcessor::PRIORITY_DEFAULT);
        $third = new MockProcessor('THIRD', 'ext', null, IProcessor::PRIORITY_LOW);

        $app = new MockPieCrust();
        $mockPlugin = new MockPlugin();
        $mockPlugin->processors = array($third, $first, $second);

        $stub = $this->getMockBuilder('PieCrust\Plugins\PluginLoader')
                     ->setMethods(array('getPlugins', 'ensureLoaded'))
                     ->setConstructorArgs(array($app))
                     ->getMock();
        $stub->expects($this->any())
             ->method('getPlugins')
             ->will($this->returnValue(array($mockPlugin)));

        $this->assertEquals(array($first, $second, $third), $stub->getProcessors());
    }

    public function testThemeMount()
    {
        $fs = MockFileSystem::create()
            ->withAsset('normal-styles.css', ".rule { color: blue; }")
            ->withAsset('_content/theme/_content/theme_config.yml', '')
            ->withAsset('_content/theme/theme-styles.css', ".other { color: black; }");

        $app = $fs->getApp();
        $bakeDir = $fs->url('counter');
        $baker = new DirectoryBaker($app, $bakeDir);
        $baker->bake();

        $this->assertFileEquals(
            $fs->url('kitchen/normal-styles.css'),
            $fs->url('counter/normal-styles.css')
        );
        $this->assertFileEquals(
            $fs->url('kitchen/_content/theme/theme-styles.css'),
            $fs->url('counter/theme-styles.css')
        );
    }

    public function testThemeMountOverride()
    {
        $fs = MockFileSystem::create()
            ->withAsset('normal-styles.css', ".rule { color: blue; }")
            ->withAsset('extra-styles.css', ".override { color: white; }")
            ->withAsset('_content/theme/_content/theme_config.yml', '')
            ->withAsset('_content/theme/extra-styles.css', ".other { color: black; }");

        $app = $fs->getApp();
        $bakeDir = $fs->url('counter');
        $baker = new DirectoryBaker($app, $bakeDir);
        $baker->bake();

        $this->assertFileEquals(
            $fs->url('kitchen/normal-styles.css'),
            $fs->url('counter/normal-styles.css')
        );
        $this->assertFileEquals(
            $fs->url('kitchen/extra-styles.css'),
            $fs->url('counter/extra-styles.css')
        );

        $bakedFiles = $baker->getBakedFiles();
        $overriddenInfo = $bakedFiles[$fs->url('kitchen/_content/theme/extra-styles.css')];
        $this->assertFalse($overriddenInfo['was_baked']);
        $this->assertTrue($overriddenInfo['was_overridden']);
    }
}
