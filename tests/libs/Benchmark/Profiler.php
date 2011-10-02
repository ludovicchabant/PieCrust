<?php
/**
 * Benchmark                                                      
 *
 * PHP version 4
 *
 * 2002-2006 Matthias Englert <Matthias.Englert@gmx.de>
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
 * @author    Matthias Englert <Matthias.Englert@gmx.de>
 * @copyright 2002-2006 Matthias Englert <Matthias.Englert@gmx.de>
 * @license   http://www.php.net/license/3_0.txt The PHP License, Version 3.0
 * @version   CVS: $Id: Profiler.php 268884 2008-11-12 20:57:49Z clockwerx $
 * @link      http://pear.php.net/package/Benchmark
 */

require_once 'PEAR.php';

/**
 * Provides timing and profiling information.
 *
 * Example 1: Automatic profiling start, stop, and output.
 *
 * <code>
 * <?php
 * require_once 'Benchmark/Profiler.php';
 *
 * $profiler = new Benchmark_Profiler(true);
 *
 * function myFunction() {
 *     global $profiler;
 *     $profiler->enterSection('myFunction');
 *     //do something
 *     $profiler->leaveSection('myFunction');
 *     return;
 * }
 *
 * //do something
 * myFunction();
 * //do more
 * ?>
 * </code>
 *
 * Example 2: Manual profiling start, stop, and output.
 *
 * <code>
 * <?php
 * require_once 'Benchmark/Profiler.php';
 *
 * $profiler = new Benchmark_Profiler();
 *
 * function myFunction() {
 *     global $profiler;
 *     $profiler->enterSection('myFunction');
 *     //do something
 *     $profiler->leaveSection('myFunction');
 *     return;
 * }
 *
 * $profiler->start();
 * //do something
 * myFunction();
 * //do more
 * $profiler->stop();
 * $profiler->display();
 * ?>
 * </code>
 *
 * @category  Benchmarking
 * @package   Benchmark
 * @author    Matthias Englert <Matthias.Englert@gmx.de>
 * @copyright 2002-2006 Matthias Englert <Matthias.Englert@gmx.de>
 * @license   http://www.php.net/license/3_0.txt The PHP License, Version 3.0
 * @link      http://pear.php.net/package/Benchmark
 * @since     1.2.0
 */
class Benchmark_Profiler extends PEAR
{

    /**
     * Contains the total ex. time of each section
     *
     * @var    array
     * @access private
     */
    var $_sections = array();

    /**
     * Calling stack
     *
     * @var    array
     * @access private
     */
    var $_stack = array();

    /**
     * Notes how often a section was entered
     *
     * @var    array
     * @access private
     */
    var $_numberOfCalls = array();

    /**
     * Notes for each section how much time is spend in sub-sections
     *
     * @var    array
     * @access private
     */
    var $_subSectionsTime = array();

    /**
     * Notes for each section how often it calls which section
     *
     * @var    array
     * @access private
     */
    var $_calls = array();

    /**
     * Notes for each section how often it was called by which section
     *
     * @var    array
     * @access private
     */
    var $_callers = array();

    /**
     * Auto-starts and stops profiler
     *
     * @var    boolean
     * @access private
     */
    var $_auto = false;

    /**
     * Max marker name length for non-html output
     *
     * @var    integer
     * @access private
     */
    var $_maxStringLength = 0;

    /**
     * Constructor, starts profiling recording
     *
     * @param bool $auto Automatically start benchmarking
     *
     * @access public
     */
    function Benchmark_Profiler($auto = false) 
    {
        $this->_auto = $auto;

        if ($this->_auto) {
            $this->start();
        }

        $this->PEAR();
    }

    /**
     * Close method, stop profiling recording and display output.
     *
     * @access public
     * @return void
     */
    function close() 
    {
        if (isset($this->_auto) && $this->_auto) {
            $this->stop();
            $this->display();
        }
    }

    /**
     * Returns profiling informations for a given section.
     *
     * @param string $section Section to retrieve
     *
     * @return array
     * @access public
     */
    function getSectionInformations($section = 'Global') 
    {
        if (isset($this->_sections[$section])) {
            $calls = array();

            if (isset($this->_calls[$section])) {
                $calls = $this->_calls[$section];
            }

            $callers = array();

            if (isset($this->_callers[$section])) {
                $callers = $this->_callers[$section];
            }

            $informations = array();

            if (isset($this->_sections['Global'])) {
                $value = $this->_sections[$section] / $this->_sections['Global'];
                $value = $value * 100;

                $informations['percentage'] = number_format($value, 2, '.', '');
            } else {
                $informations['percentage'] = 'N/A';
            }

            $informations['time']      = $this->_sections[$section];
            $informations['calls']     = $calls;
            $informations['num_calls'] = $this->_numberOfCalls[$section];
            $informations['callers']   = $callers;

            $value = $this->_sections[$section];

            if (isset($this->_subSectionsTime[$section])) {
                $value -= $this->_subSectionsTime[$section];
            }

            $informations['netto_time'] = $value;

            return $informations;
        } else {
            $this->raiseError("The section '$section' does not exists.\n",
                              null, PEAR_ERROR_TRIGGER, E_USER_WARNING);
        }
    }

    /**
     * Returns profiling informations for all sections.
     *
     * @access public
     * @return array
     */
    function getAllSectionsInformations() 
    {
        $informations = array();

        foreach ($this->_sections as $section => $time) {
            $informations[$section] = $this->getSectionInformations($section);
        }

        return $informations;
    }

    /**
     * Returns formatted profiling information.
     *
     * @param string $format output format (auto, plain or html), default auto
     *
     * @see    display()
     * @access private
     * @return string
     */
    function _getOutput($format) 
    {
        
        /* Quickly find out the maximun length: Ineffecient, but will do for now! */
        $informations = $this->getAllSectionsInformations();

        $names = array_keys($informations);
        
        $maxLength = 0;
        foreach ($names as $name) {
            if ($maxLength < strlen($name)) {
                $maxLength = strlen($name);
            }
        }
        $this->_maxStringLength = $maxLength;

        if ($format == 'auto') {
            if (function_exists('version_compare') &&
                version_compare(phpversion(), '4.1', 'ge')) {
                $format = isset($_SERVER['SERVER_PROTOCOL']) ? 'html' : 'plain';
            } else {
                global $HTTP_SERVER_VARS;
                $use_html = isset($HTTP_SERVER_VARS['SERVER_PROTOCOL']);

                $format = $use_html ? 'html' : 'plain';
            }
        }

        if ($format == 'html') {
            $out  = '<table style="border: 1px solid #000000; ">'."\n";
            $out .=
                '<tr><td>&nbsp;</td><td align="center"><b>total ex. time</b></td>'.
                '<td align="center"><b>netto ex. time</b></td>'.
                '<td align="center"><b>#calls</b></td>'.
                '<td align="center"><b>%</b></td>'.
                '<td align="center"><b>calls</b></td>' .
                '<td align="center"><b>callers</b></td></tr>'.
                "\n";
        } else {
            $dashes = str_pad("\n", ($this->_maxStringLength + 75), '-',
                              STR_PAD_LEFT);

            $out  = $dashes;
            $out .= str_pad('Section', $this->_maxStringLength + 10);
            $out .= str_pad("Total Ex Time", 22);
            $out .= str_pad("Netto Ex Time", 22);
            $out .= str_pad("#Calls", 10);
            $out .= "Percentage\n";
            $out .= $dashes;
        }
           
        foreach ($informations as $name => $values) {
            $percentage = $values['percentage'];
            $calls_str  = "";

            foreach ($values['calls'] as $key => $val) {
                if ($calls_str) {
                    $calls_str .= ", ";
                }

                $calls_str .= "$key ($val)";
            }

            $callers_str = "";

            foreach ($values['callers'] as $key => $val) {
                if ($callers_str) {
                    $callers_str .= ", ";
                }

                $callers_str .= "$key ($val)";
            }

            $percentage = $values['percentage'];
            if (is_numeric($values['percentage'])) {
                $percentage .= '%';
            }

            if ($format == 'html') {
                $out .= "<tr>";
                $out .= "<td><b>$name</b></td>";
                $out .= "<td>{$values['time']}</td>";
                $out .= "<td>{$values['netto_time']}</td>";
                $out .= "<td>{$values['num_calls']}</td>";
                $out .= "<td>{$percentage}</td>";
                

                $out .= "<td>$calls_str</td><td>$callers_str</td></tr>";
            } else {
                $out .= str_pad($name, $this->_maxStringLength + 10);
                $out .= str_pad($values['time'], 22);
                $out .= str_pad($values['netto_time'], 22);
                $out .= str_pad($values['num_calls'], 10);             
                $out .= str_pad($percentage . "\n", 8, ' ', STR_PAD_LEFT);
            }
        }
        
        if ($format == 'html') {
            return $out . '</table>';
        } else {
            return $out;
        }
    }

    /**
     * Returns formatted profiling information.
     *
     * @param string $format output format (auto, plain or html), default auto
     *
     * @access public
     * @return void
     */
    function display($format = 'auto') 
    {
        echo $this->_getOutput($format);
    }

    /**
     * Enters "Global" section.
     *
     * @see    enterSection(), stop()
     * @access public
     * @return void
     */
    function start() 
    {
        $this->enterSection('Global');
    }

    /**
     * Leaves "Global" section.
     *
     * @see    leaveSection(), start()
     * @access public
     * @return void
     */
    function stop() 
    {
        $this->leaveSection('Global');
    }

    /**
     * Enters code section.
     *
     * @param string $name The code section
     *
     * @see    start(), leaveSection()
     * @access public
     * @return void
     */
    function enterSection($name) 
    {
        if (count($this->_stack)) {
            $item = end($this->_stack);

            if (isset($this->_callers[$name][$item["name"]])) {
                $this->_callers[$name][$item]++;
            } else {
                $this->_callers[$name][$item] = 1;
            }

            if (isset($this->_calls[$item][$name])) {
                $this->_calls[$item["name"]][$name]++;
            } else {
                $this->_calls[$item["name"]][$name] = 1;
            }
        } else {
            if ($name != 'Global') {
                $msg = "tried to enter section " . $name 
                     . " but profiling was not started\n";

                $this->raiseError($msg, null, PEAR_ERROR_DIE);
            }
        }

        if (isset($this->_numberOfCalls[$name])) {
            $this->_numberOfCalls[$name]++;
        } else {
            $this->_numberOfCalls[$name] = 1;
        }

        $data = array("name" => $name, "time" => $this->_getMicrotime());
        array_push($this->_stack, $data);
    }

    /**
     * Leaves code section.
     *
     * @param string $name The marker to be set
     *
     * @see    stop(), enterSection()
     * @access public
     * @return void
     */
    function leaveSection($name) 
    {
        $microtime = $this->_getMicrotime();

        if (!count($this->_stack)) {
            $msg = "tried to leave section " . $name 
                 . " but profiling was not started\n";

            $this->raiseError($msg, null, PEAR_ERROR_DIE);
        }

        $x = array_pop($this->_stack);

        if ($x["name"] != $name) {
            $msg = "reached end of section " . $name 
                 . " but expecting end of " . $x["name"] . "\n";

            $this->raiseError($msg, null, PEAR_ERROR_DIE);
        }

        if (isset($this->_sections[$name])) {
            $this->_sections[$name] += $microtime - $x["time"];
        } else {
            $this->_sections[$name] = $microtime - $x["time"];
        }

        $parent = array_pop($this->_stack);

        if (isset($parent)) {
            if (isset($this->_subSectionsTime[$parent['name']])) {
                $this->_subSectionsTime[$parent['name']] += $microtime - $x['time'];
            } else {
                $this->_subSectionsTime[$parent['name']] = $microtime - $x['time'];
            }

            array_push($this->_stack, $parent);
        }
    }

    /**
     * Wrapper for microtime().
     *
     * @return float
     * @access private
     * @since  1.3.0
     */
    function _getMicrotime() 
    {
        $microtime = explode(' ', microtime());
        return $microtime[1] . substr($microtime[0], 1);
    }
}
