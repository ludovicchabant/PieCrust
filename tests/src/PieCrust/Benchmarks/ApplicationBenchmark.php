<?php

namespace PieCrust\Benchmarks;

use \Benchmark_Iterate;
use PieCrust\Page\Page;
use PieCrust\Page\PageRenderer;
use PieCrust\PieCrust;


/**
 * Benchmark for the application class.
 */
class ApplicationBenchmark
{
    protected $runCount;
    protected $rootDir;
    protected $cacheDir;

    public function __construct(
        $runCount = 1000,
        $rootDir = PIECRUST_BENCHMARKS_ROOT_DIR, 
        $cacheDir = PIECRUST_BENCHMARKS_CACHE_DIR)
    {
        $this->runCount = $runCount;
        $this->rootDir = $rootDir;
        $this->cacheDir = $cacheDir;
    }

    public function runAllBenchmarks()
    {
        $benchmarks = array(
            'runBenchmarkAppInit',
            'runBenchmarkPageRendering'
        );

        $results = array();
        foreach ($benchmarks as $benchmark)
        {
            $result = $this->$benchmark();
            foreach ($result as $r)
            {
                $results[] = $r;
            }
        }
        return $results;
    }

    public function runBenchmarkAppInit()
    {
        $result = array();

        $result[] = array('name' => 'App Init (cache miss)');
        $bench = new Benchmark_Iterate();
        $bench->start();
        $bench->run($this->runCount, array($this, 'runAppInit'), true);
        $bench->stop();
        $result[0]['profiling'] = $this->getSummaryTimes($bench->getProfiling());

        $result[] = array('name' => 'App Init (cache hit)');
        $bench = new Benchmark_Iterate();
        $bench->start();
        $bench->run($this->runCount, array($this, 'runAppInit'), false);
        $bench->stop();
        $result[1]['profiling'] = $this->getSummaryTimes($bench->getProfiling());

        return $result;
    }

    public function runBenchmarkPageRendering()
    {
        $result = array();

        $result[] = array('name' => 'Page Rendering (cache miss)');
        $bench = new Benchmark_Iterate();
        $bench->start();
        $pieCrust = $this->runAppInit();
        $bench->run($this->runCount, array($this, 'runQuery'), $pieCrust, '/empty', true);
        $bench->stop();
        $result[0]['profiling'] = $this->getSummaryTimes($bench->getProfiling());

        $result[] = array('name' => 'Page Rendering (cache hit)');
        $bench = new Benchmark_Iterate();
        $bench->start();
        $pieCrust = $this->runAppInit();
        $bench->run($this->runCount, array($this, 'runQuery'), $pieCrust, '/empty', false);
        $bench->stop();
        $result[1]['profiling'] = $this->getSummaryTimes($bench->getProfiling());

        return $result;
    }

    public function runAppInit($cleanCache = false)
    {
        if ($cleanCache)
            ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);

        $pc = new PieCrust(array(
            'cache' => (bool)$this->cacheDir,
            'root' => $this->rootDir
        ));
        $pc->setCacheDir($this->cacheDir);
        $pc->getConfig();
        return $pc;
    }

    public function runQuery($pieCrust, $uri, $cleanCache = false)
    {
        if ($cleanCache)
            ensure_cache(PIECRUST_BENCHMARKS_CACHE_DIR, true);

        $page = Page::createFromUri($pieCrust, $uri);
        $renderer = new PageRenderer($page);
        return $renderer->get();
    }

    private function getSummaryTimes($prof)
    {
        $diffValues = array_map(
            array($this, 'mapDiffTime'), 
            array_filter($prof, array($this, 'filterEndMarker'))
        );

        $times = array();
        $times['times'] = $this->runCount;
        $times['median'] = median($diffValues) * 1000;
        $times['average'] = average($diffValues) * 1000;
        $times['max'] = max($diffValues) * 1000;
        return $times;
    }

    private function filterEndMarker($value)
    {
        return preg_match('/^end_/', $value['name']);
    }

    private function mapDiffTime($value)
    {
        return $value['diff'];
    }
}

