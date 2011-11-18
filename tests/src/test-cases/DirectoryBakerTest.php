<?php

require_once 'unittest_setup.php';

require_once 'vfsStream/vfsStream.php';
require_once 'vfsStream/visitor/vfsStreamStructureVisitor.php';

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Baker\DirectoryBaker;


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
    }
}
