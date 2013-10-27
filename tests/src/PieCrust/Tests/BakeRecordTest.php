<?php

namespace PieCrust\Tests;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Baker\BakeRecordPageEntry;
use PieCrust\Baker\BakeRecordAssetEntry;
use PieCrust\Baker\TransitionalBakeRecord;
use PieCrust\Mock\MockPieCrust;


class BakeRecordTest extends PieCrustTestCase
{
    public function getDirtyTaxonomiesDataProvider()
    {
        return array(
            array(
                self::makeRecord(),
                self::makeRecord(),
                array()
            ),
            // Single taxonomy
            array(
                array(),
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                array('category' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                array(),
                array('category' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                array()
            ),
            array(
                self::makeRecord(),
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                array('category' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'bar'
                ))),
                array('category' => array(
                    'blog' => array('foo', 'bar')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'category' => 'foo'
                ))),
                self::makeRecord(),
                array('category' => array(
                    'blog' => array('foo')
                ))
            ),
            // Multiple taxonomy
            array(
                array(),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                array('tags' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                array(),
                array('tags' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                array()
            ),
            array(
                self::makeRecord(),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                array('tags' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('bar')
                ))),
                array('tags' => array(
                    'blog' => array('foo', 'bar')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                self::makeRecord(),
                array('tags' => array(
                    'blog' => array('foo')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo', 'bar')
                ))),
                array('tags' => array(
                    'blog' => array('bar')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo', 'bar')
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo')
                ))),
                array('tags' => array(
                    'blog' => array('bar')
                ))
            ),
            array(
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo', 'bar')
                ))),
                self::makeRecord(array('taxonomy' => array(
                    'tags' => array('foo', 'other')
                ))),
                array('tags' => array(
                    'blog' => array('bar', 'other')
                ))
            )
        );
    }
    
    /**
     * @dataProvider getDirtyTaxonomiesDataProvider
     */
    public function testGetDirtyTaxonomies($oldRecord, $currentRecord, $expectedTaxonomies)
    {
        $app = new MockPieCrust();
        $record = new TransitionalBakeRecord($app, $oldRecord);
        $record->loadCurrent($currentRecord);
        $taxonomies = array(
            'tags' => array('multiple' => true, 'singular' => 'tag'),
            'category' => array('multiple' => false)
        );
        $actualTaxonomies = $record->getDirtyTaxonomies($taxonomies);
        $this->assertEquals($expectedTaxonomies, $actualTaxonomies);
    }

    protected static function makeRecord(array $userData = array())
    {
        return array('pageEntries' => array(self::makePageEntry($userData)));
    }

    protected static function makePageEntry(array $userData = array())
    {
        $data = array(
            'path' => '/tmp/something',
            'pageType' => IPage::TYPE_POST,
            'blogKey' => 'blog',
            'pageKey' => null,
            'taxonomy' => array(),
            'usedTaxonomyCombinations' => array(),
            'usedPages' => false,
            'usedPosts' => array(),
            'outputs' => array()
        );
        $data = array_merge($data, $userData);
        return $data;
    }
}

