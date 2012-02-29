<?php

require_once 'unittest_setup.php';

require_once 'vfsStream/vfsStream.php';
require_once 'vfsStream/visitor/vfsStreamStructureVisitor.php';

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Baker\DirectoryBaker;
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
        $pc->rootDir = vfsStream::url('root/kitchen');
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
            'counter' => array()
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen');
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
                'counter' => array())
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
                )
            )),
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
        $this->assertEquals(
            array(
                vfsStream::url('root/kitchen/something.html') => array(
                    'relativeInput' => 'something.html',
                    'relativeOutputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/counter/something.html'))
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
                '_counter' => array()
            )
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen');
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
                    '_counter' => array()
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
                    )
                )
            )),
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
        $this->assertEquals(
            array(
                vfsStream::url('root/kitchen/something.html') => array(
                    'relativeInput' => 'something.html',
                    'relativeOutputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/kitchen/_counter/something.html'))
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
            '_counter' => array()
        );
        $root = vfsStream::create($structure);
        
        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root/kitchen');
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
                    'relativeInput' => 'something.html',
                    'relativeOutputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html'))
                ),
                vfsStream::url('root/kitchen/subdir/_important.html') => array(
                    'relativeInput' => 'subdir/_important.html',
                    'relativeOutputs' => array('subdir/_important.html'),
                    'outputs' => array(vfsStream::url('root/_counter/subdir/_important.html'))
                )
            ),
            $baker->getBakedFiles()
        );
    }

    public function testUpToDate()
    {
        $structure = array(
            'something.html' => 'This is a test page.',
            '_counter' => array()
        );
        $root = vfsStream::create($structure);

        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root');
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
                    'relativeInput' => 'something.html',
                    'relativeOutputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html'))
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
        $this->assertEmpty($baker->getBakedFiles());
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
                    'relativeInput' => 'something.html',
                    'relativeOutputs' => array('something.html'),
                    'outputs' => array(vfsStream::url('root/_counter/something.html'))
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
            '_counter' => array()
        );
        $root = vfsStream::create($structure);

        $pc = new MockPieCrust();
        $pc->rootDir = vfsStream::url('root');
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

    public function globToRegexDataProvider()
    {
        return array(
            array('blah', '/blah/'),
            array('/blah/', '/blah/'),
            array('/^blah.*\\.css/', '/^blah.*\\.css/'),
            array('blah.*', '/blah\\.[^\\/\\\\]*/'),
            array('blah?.css', '/blah[^\\/\\\\]\\.css/')
        );
    }

    /**
     * @dataProvider globToRegexDataProvider
     */
    public function testGlobToRegex($in, $expectedOut)
    {
        $out = DirectoryBaker::globToRegex($in);
        $this->assertEquals($expectedOut, $out);
    }

    public function testGlobToRegexExample()
    {
        $pattern = DirectoryBaker::globToRegex('blah*.css');
        $this->assertTrue(preg_match($pattern, 'dir/blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah2.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blahblah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah.blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah.blah.css/something') == 1);
        $this->assertFalse(preg_match($pattern, 'blah/something.css') == 1);

        $pattern = DirectoryBaker::globToRegex('blah?.css');
        $this->assertFalse(preg_match($pattern, 'dir/blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah1.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blahh.css') == 1);
        $this->assertFalse(preg_match($pattern, 'dir/blah/yo.css') == 1);
    }

    public function testProcessorOrdering()
    {
        $first = new MockProcessor('FIRST', 'ext', IProcessor::PRIORITY_HIGH);
        $second = new MockProcessor('SECOND', 'ext', IProcessor::PRIORITY_DEFAULT);
        $third = new MockProcessor('THIRD', 'ext', IProcessor::PRIORITY_LOW);

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
