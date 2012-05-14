<?php

require_once 'unittest_setup.php';

require_once 'vfsStream/vfsStream.php';
require_once 'vfsStream/visitor/vfsStreamStructureVisitor.php';

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Baker\DirectoryBaker;
use PieCrust\Baker\ProcessingTreeBuilder;
use PieCrust\Baker\Processors\IProcessor;
use PieCrust\Baker\Processors\CopyFileProcessor;


class DirectoryBakerTest extends PHPUnit_Framework_TestCase
{
    public function testEmptyBake()
    {
        $structure = array(
            'kitchen' => array(),
            'counter' => array()
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen/');
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = vfsStream::url('root/counter');
        $parameters = array(
            'processors' => array('copy')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $this->assertEquals(
            array('root' => array(
                'kitchen' => array(),
                'counter' => array()
            )),
            vfsStream::inspect(new vfsStreamStructureVisitor(), $root)->getStructure()
        );
        $baker->bake();
        $this->assertEquals(
            array('root' => array(
                'kitchen' => array(),
                'counter' => array()
            )),
            vfsStream::inspect(new vfsStreamStructureVisitor(), $root)->getStructure()
        );
        $this->assertEmpty($baker->getBakedFiles());
    }
    
    public function testOneFileBake()
    {
        $structure = array(
            'kitchen' => array(
                'something.html' => 'This is some test file.'
            ),
            'counter' => array(),
            'cache' => array()
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen/');
        $pc->cacheDir = vfsStream::url('root/cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = vfsStream::url('root/counter');
        $parameters = array(
            'processors' => array('copy')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $this->assertEquals(
            array('root' => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.'
                ),
                'counter' => array(),
                'cache' => array())
            ),
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
        $baker->bake();
        $this->assertEquals(
            array('root' => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.'
                ),
                'counter' => array(
                    'something.html' => 'This is some test file.'
                ),
                'cache' => array()
            )),
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
        $this->assertEquals(
            array(
                vfsStream::url('root/kitchen/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/counter/something.html')),
                    'was_baked' => true
                )
            ),
            $baker->getBakedFiles()
        );
    }
    
    public function testOneFileBakeInsideKitchen()
    {
        $structure = array(
            'kitchen' => array(
                'something.html' => 'This is some test file.',
                '_counter' => array(),
                '_cache' => array()
            )
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen/');
        $pc->cacheDir = vfsStream::url('root/kitchen/_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = vfsStream::url('root/kitchen/_counter');
        $parameters = array(
            'processors' => array('copy'),
            'skip_patterns' => array('/_counter/')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $this->assertEquals(
            array('root' => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.',
                    '_counter' => array(),
                    '_cache' => array()
                ))
            ),
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
        $baker->bake();
        $this->assertEquals(
            array('root' => array(
                'kitchen' => array(
                    'something.html' => 'This is some test file.',
                    '_counter' => array(
                        'something.html' => 'This is some test file.'
                    ),
                    '_cache' => array()
                )
            )),
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
        $this->assertEquals(
            array(
                vfsStream::url('root/kitchen/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/kitchen/_counter/something.html')),
                    'was_baked' => true
                )
            ),
            $baker->getBakedFiles()
        );
    }

    public function testSkipPattern()
    {
        $structure = array(
            'kitchen' => array(
                'something.html' => 'This is a test page.',
                '_hidden.html' => 'This is hidden',
                'subdir' => array(
                    '_important.html' => 'This should not be hidden.'
                )
            ),
            '_counter' => array(),
            '_cache' => array()
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen/');
        $pc->cacheDir = vfsStream::url('root/_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = vfsStream::url('root/_counter');
        $parameters = array(
            'processors' => array('copy'),
            'skip_patterns' => array('/^_/')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $baker->bake();
        $this->assertTrue(is_file(vfsStream::url('root/_counter/something.html')));
        $this->assertTrue(is_file(vfsStream::url('root/_counter/subdir/_important.html')));
        $this->assertFalse(is_file(vfsStream::url('root/_counter/_hidden.html')));
        $this->assertEquals(
            array(
                vfsStream::url('root/kitchen/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html')),
                    'was_baked' => true
                ),
                vfsStream::url('root/kitchen/subdir/_important.html') => array(
                    'relative_input' => 'subdir/_important.html',
                    'relative_outputs' => array('subdir/_important.html'),
                    'outputs' => array(vfsStream::url('root/_counter/subdir/_important.html')),
                    'was_baked' => true
                )
            ),
            $baker->getBakedFiles()
        );
    }

    public function testUpToDate()
    {
        $structure = array(
            'something.html' => 'This is a test page.',
            '_counter' => array(),
            '_cache' => array()
        );
        $root = vfsStream::create($structure);

        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/');
        $pc->cacheDir = vfsStream::url('root/_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = vfsStream::url('root/_counter');
        $parameters = array(
            'processors' => array('copy')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $outFile = vfsStream::url('root/_counter/something.html');
        $this->assertFalse(is_file($outFile));
        sleep(1);
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals('This is a test page.', file_get_contents($outFile));
        $this->assertEquals(
            array(
                vfsStream::url('root/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html')),
                    'was_baked' => true
                )
            ),
            $baker->getBakedFiles()
        );
        clearstatcache();
        $mtime = filemtime($outFile);
        $this->assertGreaterThan(filemtime(vfsStream::url('root/something.html')), $mtime);

        sleep(1);
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals(
            array(
                vfsStream::url('root/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html')),
                    'was_baked' => false
                )
            ),
            $baker->getBakedFiles()
        );
        clearstatcache();
        $this->assertEquals($mtime, filemtime($outFile));
        $this->assertEquals('This is a test page.', file_get_contents($outFile));

        sleep(1);
        file_put_contents(vfsStream::url('root/something.html'), 'New content!');
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals(
            array(
                vfsStream::url('root/something.html') => array(
                    'relative_input' => 'something.html',
                    'relative_outputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html')),
                    'was_baked' => true
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
        $structure = array(
            'forced.html' => 'This is a test page.',
            '_counter' => array(),
            '_cache' => array()
        );
        $root = vfsStream::create($structure);

        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/');
        $pc->cacheDir = vfsStream::url('root/_cache/');
        $pc->isCachingEnabled = true;
        $pc->getPluginLoader()->processors[] = new CopyFileProcessor();
        $bakeDir = vfsStream::url('root/_counter');
        $parameters = array(
            'processors' => array('copy'),
            'force_patterns' => array('/forced/')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $outFile = vfsStream::url('root/_counter/forced.html');
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
        $structure = array(
            'something.foo' => 'Some contents.',
            '_cache' => array(),
            '_counter' => array()
        );
        $root = vfsStream::create($structure);

        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/');
        $pc->cacheDir = vfsStream::url('root/_cache/');
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
        $bakeDir = vfsStream::url('root/_counter');
        $parameters = array(
            'processors' => array('*')
        );
        $baker = new DirectoryBaker($pc, $bakeDir, $parameters);
        
        $outFile = vfsStream::url('root/_counter/something.html');
        $this->assertFalse(is_file($outFile));
        $baker->bake();
        $this->assertTrue(is_file($outFile));
        $this->assertEquals('prefixed: <b>SOME CONTENTS.</b>', file_get_contents($outFile));
    }

    public function testProcessingTree()
    {
        $structure = array(
            'something.foo' => 'Some contents.',
            '_cache' => array(),
            '_counter' => array()
        );
        $root = vfsStream::create($structure);

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

        $builder = new ProcessingTreeBuilder(
            vfsStream::url('root/'),
            vfsStream::url('root/_cache/bake_tmp/'),
            vfsStream::url('root/_counter'),
            $processors
        );
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
}
