<?php
/**
 * Timer.php                                                      
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
 * @version   CVS: $Id: Timer.php 268884 2008-11-12 20:57:49Z clockwerx $
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
 * require_once 'Benchmark/Timer.php';
 *
 * $timer = new Benchmark_Timer(TRUE);
 * $timer->setMarker('Marker 1');
 * ?>
 * </code>
 *
 * Example 2: Manual profiling start, stop, and output.
 *
 * <code>
 * <?php
 * require_once 'Benchmark/Timer.php';
 *
 * $timer = new Benchmark_Timer();
 * $timer->start();
 * $timer->setMarker('Marker 1');
 * $timer->stop();
 *
 * $timer->display(); // to output html formated
 * // AND/OR :
 * $profiling = $timer->getProfiling(); // get profiler info as associative array
 * ?>
 * </code>
 *
 * @category  Benchmarking
 * @package   Benchmark
 * @author    Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @author    Ludovico Magnocavallo <ludo@sumatrasolutions.com>
 * @copyright 2002-2005 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license   http://www.php.net/license/3_0.txt The PHP License, Version 3.0
 * @link      http://pear.php.net/package/Benchmark
 */
class Benchmark_Timer extends PEAR
{
    /**
     * Contains the markers.
     *
     * @var    array
     * @access private
     */
    var $markers = array();

    /**
     * Auto-start and stop timer.
     *
     * @var    boolean
     * @access private
     */
    var $auto = false;

    /**
     * Max marker name length for non-html output.
     *
     * @var    integer
     * @access private
     */
    var $maxStringLength = 0;

    /**
     * Constructor.
     *
     * @param boolean $auto Automatically start timer
     *
     * @access public
     */
    function Benchmark_Timer($auto = false) 
    {
        $this->auto = $auto;

        if ($this->auto) {
            $this->start();
        }

        $this->PEAR();
    }

    /**
     * Close method. Stop timer and display output.
     *
     * @access public
     * @return void
     */
    function close() 
    {
        if ($this->auto) {
            $this->stop();
            $this->display();
        }
    }

    /**
     * Set "Start" marker.
     *
     * @see    setMarker(), stop()
     * @access public
     * @return void
     */
    function start() 
    {
        $this->setMarker('Start');
    }

    /**
     * Set "Stop" marker.
     *
     * @see    setMarker(), start()
     * @access public
     * @return void
     */
    function stop() 
    {
        $this->setMarker('Stop');
    }

    /**
     * Set marker.
     *
     * @param string $name Name of the marker to be set.
     *
     * @see    start(), stop()
     * @access public
     * @return void
     */
    function setMarker($name) 
    {
        $this->markers[$name] = $this->_getMicrotime();
    }

    /**
     * Returns the time elapsed betweens two markers.
     *
     * @param string $start start marker, defaults to "Start"
     * @param string $end   end marker, defaults to "Stop"
     *
     * @return double  $time_elapsed time elapsed between $start and $end
     * @access public
     */
    function timeElapsed($start = 'Start', $end = 'Stop') 
    {
        if ($end == 'Stop' && !isset($this->markers['Stop'])) {
            $this->markers['Stop'] = $this->_getMicrotime();
        }
        $end   = isset($this->markers[$end]) ? $this->markers[$end] : 0;
        $start = isset($this->markers[$start]) ? $this->markers[$start] : 0;

        if (extension_loaded('bcmath')) {
            return bcsub($end, $start, 6);
        } else {
            return $end - $start;
        }
    }

    /**
     * Returns profiling information.
     *
     * $profiling[x]['name']  = name of marker x
     * $profiling[x]['time']  = time index of marker x
     * $profiling[x]['diff']  = execution time from marker x-1 to this marker x
     * $profiling[x]['total'] = total execution time up to marker x
     *
     * @return array
     * @access public
     */
    function getProfiling() 
    {
        $i = $total = 0;

        $result = array();
        $temp   = reset($this->markers);

        $this->maxStringLength = 0;

        foreach ($this->markers as $marker => $time) {
            if (extension_loaded('bcmath')) {
                $diff  = bcsub($time, $temp, 6);
                $total = bcadd($total, $diff, 6);
            } else {
                $diff  = $time - $temp;
                $total = $total + $diff;
            }

            $result[$i]['name']  = $marker;
            $result[$i]['time']  = $time;
            $result[$i]['diff']  = $diff;
            $result[$i]['total'] = $total;

            $longer = strlen($marker) > $this->maxStringLength;

            if ($longer) {
                $this->maxStringLength = strlen($marker) + 1;
            }

            $temp = $time;
            $i++;
        }

        $result[0]['diff']  = '-';
        $result[0]['total'] = '-';

        $longer = strlen('total') > $this->maxStringLength;

        if ($longer) {
            $this->maxStringLength = strlen('total');
        }

        $this->maxStringLength += 2;

        return $result;
    }

    /**
     * Return formatted profiling information.
     *
     * @param boolean $showTotal Optionnaly includes total in output, default no
     * @param string  $format    output format (auto, plain or html), default auto
     *
     * @return string
     * @see    getProfiling()
     * @access public
     */
    function getOutput($showTotal = false, $format = 'auto') 
    {
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

        $total  = $this->TimeElapsed();
        $result = $this->getProfiling();
        $dashes = '';

        if ($format == 'html') {
            $out  = '<table border="1">'."\n";
            $out .= '<tr>';
            $out .= '<td>&nbsp;</td>';
            $out .= '<td align="center"><b>time index</b></td>';
            $out .= '<td align="center"><b>ex time</b></td>';
            $out .= '<td align="center"><b>%</b></td>';

            if ($showTotal) {
                 $out .= '<td align="center"><b>elapsed</b></td>';
                 $out .= '<td align="center"><b>%</b></td>';
            }

            $out .= "</tr>\n";
        } else {
            $dashes = $out = str_pad("\n",
                $this->maxStringLength + ($showTotal ? 70 : 45), '-', STR_PAD_LEFT);

            $out .= str_pad('marker', $this->maxStringLength) .
                    str_pad("time index", 22) .
                    str_pad("ex time", 16) .
                    str_pad("perct ", 8) .
                    ($showTotal ? ' '.str_pad("elapsed", 16)."perct" : '')."\n" .
                    $dashes;
        }

        foreach ($result as $k => $v) {
            $perc  = (($v['diff'] * 100) / $total);
            $tperc = (($v['total'] * 100) / $total);

            $percentage = number_format($perc, 2, '.', '')."%";

            if ($format == 'html') {
                $out .= "<tr><td><b>" . $v['name'] .
                       "</b></td><td>" . $v['time'] .
                       "</td><td>" . $v['diff'] .
                       "</td><td align=\"right\">" . $percentage .
                       "</td>".
                       ($showTotal ?
                            "<td>" . $v['total'] .
                            "</td><td align=\"right\">" .
                            number_format($tperc, 2, '.', '') .
                            "%</td>" : '').
                       "</tr>\n";
            } else {


                $out .= str_pad($v['name'], $this->maxStringLength, ' ') .
                        str_pad($v['time'], 22) .
                        str_pad($v['diff'], 14) .
                        str_pad($percentage, 8, ' ', STR_PAD_LEFT) .
                        ($showTotal ? '   '.
                            str_pad($v['total'], 14) .
                            str_pad(number_format($tperc, 2, '.', '')."%",
                                             8, ' ', STR_PAD_LEFT) : '').
                        "\n";
            }

            $out .= $dashes;
        }

        if ($format == 'html') {
            $out .= "<tr style='background: silver;'>";
            $out .= "<td><b>total</b></td>";
            $out .= "<td>-</td>";
            $out .= "<td>${total}</td>";
            $out .= "<td>100.00%</td>";
            $out .= ($showTotal ? "<td>-</td><td>-</td>" : "");
            $out .= "</tr>\n";
            $out .= "</table>\n";
        } else {
            $out .= str_pad('total', $this->maxStringLength);
            $out .= str_pad('-', 22);
            $out .= str_pad($total, 15);
            $out .= "100.00%\n";
            $out .= $dashes;
        }

        return $out;
    }

    /**
     * Prints the information returned by getOutput().
     *
     * @param boolean $showTotal Optionnaly includes total in output, default no
     * @param string  $format    output format (auto, plain or html), default auto
     *
     * @see    getOutput()
     * @access public
     * @return void
     */
    function display($showTotal = false, $format = 'auto') 
    {
        print $this->getOutput($showTotal, $format);
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
