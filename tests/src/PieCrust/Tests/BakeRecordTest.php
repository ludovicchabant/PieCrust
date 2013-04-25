<?php

namespace PieCrust\Tests;

use PieCrust\PieCrustDefaults;
use PieCrust\Baker\BakeRecord;


class BakeRecordTest extends PieCrustTestCase
{
    public function testShouldDoFullBake1()
    {
        $record = new BakeRecord(
            array('blog'),
            array('version' => '0.0.0'));
        $this->assertTrue($record->shouldDoFullBake());
    }
    
    public function testShouldDoFullBake2()
    {
        $record = new BakeRecord(
            array('blog'),
            array('version' => PieCrustDefaults::VERSION));
        $this->assertTrue($record->shouldDoFullBake());
    }
    
    public function testShouldDoFullBake3()
    {
        $record = new BakeRecord(
            array('blog'),
            array(
                'time' => time(), 
                'version' => PieCrustDefaults::VERSION,
                'record_version' => BakeRecord::VERSION
            ));
        $this->assertFalse($record->shouldDoFullBake());
    }
    
    public function postInfoDataProvider()
    {
        return array(
            array(
                array(),
                array(
                    'wasAnyPostBaked' => false
                )
            ),
            array(
                array(
                    self::makePostInfo('one')
                ),
                array(
                    'wasAnyPostBaked' => true
                )
            ),
            array(
                array(
                    self::makePostInfo('one', array('tag1'))
                ),
                array(
                    'wasAnyPostBaked' => true,
                    'tagsToBake' => array('blog' => array('tag1'))
                )
            ),
            array(
                array(
                    self::makePostInfo('one', array(), 'cat1')
                ),
                array(
                    'wasAnyPostBaked' => true,
                    'categoriesToBake' => array('blog' => array('cat1'))
                )
            ),
            array(
                array(
                    self::makePostInfo('one', array('tag1'), 'cat1')
                ),
                array(
                    'wasAnyPostBaked' => true,
                    'tagsToBake' => array('blog' => array('tag1')),
                    'categoriesToBake' => array('blog' => array('cat1'))
                )
            ),
            array(
                array(
                    self::makePostInfo('1-one', array('tag1'), 'cat1'),
                    self::makePostInfo('1-two', array('tag1', 'tag2'), 'cat1'),
                    self::makePostInfo('1-three', array('tag3'), null),
                    self::makePostInfo('1-four', array('tag4'), 'cat2'),
                    self::makePostInfo('1-five', array('tag2', 'tag3', 'tag5'), null)
                ),
                array(
                    'wasAnyPostBaked' => true,
                    'tagsToBake' => array('blog' => array('tag1', 'tag2', 'tag3', 'tag4', 'tag5')),
                    'categoriesToBake' => array('blog' => array('cat1', 'cat2'))
                )
            ),
            array(
                array(
                    self::makePostInfo('1-one', array('tag1'), 'cat1'),
                    self::makePostInfo('1-two', array('tag1', 'tag2'), 'cat1', false),
                    self::makePostInfo('1-three', array('tag3'), null, false),
                    self::makePostInfo('1-four', array('tag4'), 'cat2'),
                    self::makePostInfo('1-five', array('tag2', 'tag3', 'tag5'), null, false)
                ),
                array(
                    'wasAnyPostBaked' => true,
                    'tagsToBake' => array('blog' => array('tag1', 'tag4')),
                    'categoriesToBake' => array('blog' => array('cat1', 'cat2'))
                )
            )
        );
    }
    
    /**
     * @dataProvider postInfoDataProvider
     */
    public function testPostInfo(array $postInfos, array $expectedData)
    {
        $record = new BakeRecord(
            array('blog'),
            array('time' => time(), 'version' => PieCrustDefaults::VERSION));
        
        $postsTagged = array();
        $postsInCategories = array();
        foreach ($postInfos as $pi)
        {
            $record->addPostInfo($pi);
            
            $key = $pi['blogKey'];
            if (!isset($postsTagged[$key]))
                $postsTagged[$key] = array();
            if (!isset($postsInCategories[$key]))
                $postsInCategories[$key] = array();
            
            if ($pi['tags'])
            {
                foreach ($pi['tags'] as $tag)
                {
                    if (!isset($postsTagged[$key][$tag]))
                        $postsTagged[$key][$tag] = array();
                    $postsTagged[$key][$tag][] = $pi;
                }
            }
            
            if ($pi['category'])
            {
                $cat = $pi['category'];
                if (!isset($postsInCategories[$key][$cat]))
                    $postsInCategories[$key][$cat] = array();
                $postsInCategories[$key][$cat][] = $pi;
            }
        }
        
        $this->assertEquals($expectedData['wasAnyPostBaked'], $record->wasAnyPostBaked());
        
        if (isset($expectedData['tagsToBake']))
        {
            foreach ($expectedData['tagsToBake'] as $key => $tags)
            {
                $this->assertEquals($tags, $record->getTagsToBake($key));
            }
        }
        else
        {
            $this->assertEmpty($record->getTagsToBake('blog'));
        }
        
        if (isset($expectedData['categoriesToBake']))
        {
            foreach ($expectedData['categoriesToBake'] as $key => $cats)
            {
                $this->assertEquals($cats, $record->getCategoriesToBake($key));
            }
        }
        else
        {
            $this->assertEmpty($record->getCategoriesToBake('blog'));
        }
        
        foreach ($postsTagged as $key => $postsPerTag)
        {
            foreach ($postsPerTag as $tag => $expectedPostsWithTag)
            {
                $actualPostsWithTag = $record->getPostsTagged($key, $tag);
                $this->assertEquals($expectedPostsWithTag, $actualPostsWithTag);
            }
        }
        
        foreach ($postsInCategories as $key => $postsPerCategory)
        {
            foreach ($postsPerCategory as $cat => $expectedPostsInCategory)
            {
                $actualPostsInCategory = $record->getPostsInCategory($key, $cat);
                $this->assertEquals($expectedPostsInCategory, $actualPostsInCategory);
            }
        }
    }
    
    protected static function makePostInfo($uri, array $tags = array(), $category = null, $wasBaked = true, $blogKey = 'blog')
    {
        return array(
            'uri' => $uri,
            'blogKey' => $blogKey,
            'wasBaked' => $wasBaked,
            'tags' => $tags,
            'category' => $category
        );
    }
}
