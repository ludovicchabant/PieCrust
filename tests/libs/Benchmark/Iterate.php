<?php
/**
 * Iterate.php                                                      
 *
 * PHP version 4
 *
 * Copyright (c) 2001-2006 Sebastian Bergmann <sb@sebastian-bergmann.de>. 
 * 
 * This source file is subject to the New BSD license, That is bundled    
 * with this package in the file LICENSE, and is available through        
 * the world-wide-web at                                                  
 * http://www.opensource.org/licenses/bsd-license.php                     
 * If you did not receive a copy of the new BSDlicense and are unable     
 * to obtain it through the world-wide-web, please send a note to         
 * license@php.net so we can mail you a copy immediately.                 
 *
 * @category  Benchmarking
 * @package   Benchmark
 * @author    Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright 2002-2005 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license   http://www.php.net/license/3_0.txt The PHP License, Version 3.0
 * @version   CVS: $Id: Iterate.php 268884 2008-11-12 20:57:49Z clockwerx $
 * @link      http://pear.php.net/package/Benchmark
 */

require_once 'Benchmark/Timer.php';

/**
 * Provides timing and profiling information.
 *
 * Example 1
 *
 * <code>
 * <?php
 * require_once 'Benchmark/Iterate.php';
 *
 * $benchmark = new Benchmark_Iterate;
 *
 * function foo($string) {
 *     print $string . '<br>';
 * }
 *
 * $benchmark->run(100, 'foo', 'test');
 * $result = $benchmark->get();
 * ?>
 * </code>
 *
 * Example 2
 *
 * <code>
 * <?php
 * require_once 'Benchmark/Iterate.php';
 *
 * $benchmark = new Benchmark_Iterate;
 *
 * class MyClass {
 *     function foo($string) {
 *         print $string . '<br>';
 *     }
 * }
 *
 * $benchmark->run(100, 'myclass::foo', 'test');
 * $result = $benchmark->get();
 * ?>
 * </code>
 *
 * Example 3
 *
 * <code>
 * <?php
 * require_once 'Benchmark/Iterate.php';
 *
 * $benchmark = new Benchmark_Iterate;
 *
 * class MyClass {
 *     function foo($string) {
 *         print $string . '<br>';
 *     }
 * }
 *
 * $o = new MyClass();
 *
 * $benchmark->run(100, 'o->foo', 'test');
 * $result = $benchmark->get();
 * ?>
 * </code>
 *
 * @category  Benchmarking
 * @package   Benchmark
 * @author    Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright 2002-2005 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license   http://www.php.net/license/3_0.txt The PHP License, Version 3.0
 * @link      http://pear.php.net/package/Benchmark
 */
class Benchmark_Iterate extends Benchmark_Timer
{

    /**
     * Benchmarks a function or method.
     *
     * @access public
     * @return void
     */
    function run() 
    {
        $arguments     = func_get_args();
        $iterations    = array_shift($arguments);
        $function_name = array_shift($arguments);

        if (strstr($function_name, '::')) {
            $function_name = explode('::', $function_name);
            $objectmethod  = $function_name[1];
        }

        if (strstr($function_name, '->')) {
            list($objectname, $objectmethod) = explode('->', $function_name);

            $object = $GLOBALS[$objectname];

            for ($i = 1; $i <= $iterations; $i++) {
                $this->setMarker('start_' . $i);
                call_user_func_array(array($object, $objectmethod), $arguments);
                $this->setMarker('end_' . $i);
            }

            return(0);
        }

        for ($i = 1; $i <= $iterations; $i++) {
            $this->setMarker('start_' . $i);
            call_user_func_array($function_name, $arguments);
            $this->setMarker('end_' . $i);
        }
    }

    /**
     * Returns benchmark result.
     *
     * $result[x           ] = execution time of iteration x
     * $result['mean'      ] = mean execution time
     * $result['iterations'] = number of iterations
     *
     * @param bool $simple_output Show just the total
     *
     * @return array
     * @access public
     */
    function get($simple_output = false) 
    {
        $result = array();
        $total  = 0;

        $iterations = count($this->markers)/2;

        for ($i = 1; $i <= $iterations; $i++) {
            $time = $this->timeElapsed('start_'.$i, 'end_'.$i);

            if (extension_loaded('bcmath')) {
                $total = bcadd($total, $time, 6);
            } else {
                $total = $total + $time;
            }

            if (!$simple_output) {
                $result[$i] = $time;
            }
        }

        if (extension_loaded('bcmath')) {
            $result['mean'] = bcdiv($total, $iterations, 6);
        } else {
            $result['mean'] = $total / $iterations;
        }

        $result['iterations'] = $iterations;

        return $result;
    }
}
