<?php

require_once 'TestEnvironment.inc.php';
require_once 'Combinatorics.inc.php';


class CombinatoricsTest extends PHPUnit_Framework_TestCase
{
    public function arrayCombinationsDataProvider()
    {
        return array(
            array(
                array("one"),
                array(
                      array("one")
                )
            ),
            array(
                array("one", "two"),
                array(
                      array("one"),
                      array("two"),
                      array("one", "two")
                )
            ),
            array(
                array("one", "two", "three"),
                array(
                      array("one"),
                      array("two"),
                      array("three"),
                      array("one", "two"),
                      array("one", "three"),
                      array("two", "three"),
                      array("one", "two", "three")
                )
            )
        );
    }
    
    /**
     * @dataProvider arrayCombinationsDataProvider
     */
    public function testArrayCombinations($input, $expected)
    {
        array_deepsort($expected);
        
        $actual = array_combinations($input);
        array_deepsort($actual);
        
        $this->assertEquals($expected, $actual);
    }
}
