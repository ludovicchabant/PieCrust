<?php

namespace PieCrust\Baker;

use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;


/**
 * A class that keeps track of the transition from a previous
 * bake operation to a current one.
 */
class TransitionalBakeRecord
{
    // Deletion types {{{
    const DELETION_MISSING = 1;
    const DELETION_CHANGED = 2;
    // }}}

    protected $pageTransitions;
    protected $assetTransitions;

    protected $previous;
    /**
     * Gets the previous bake record.
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    protected $current;
    /**
     * Gets the current bake record.
     */
    public function getCurrent()
    {
        return $this->current;
    }

    public function __construct(IPieCrust $pieCrust, $previousBakeRecordPath = null)
    {
        $this->previous = new BakeRecord($pieCrust);
        $this->current = new BakeRecord($pieCrust);
        $this->current->addObserver($this);
        $this->pageTransitions = null;
        $this->assetTransitions = null;

        if ($previousBakeRecordPath)
        {
            $this->loadPrevious($previousBakeRecordPath);
        }
    }

    public function loadPrevious($path)
    {
        $this->previous->load($path);
        $this->ensurePageTransitions();
        $this->ensureAssetTransitions();
    }

    public function loadCurrent($path)
    {
        $this->current->load($path);
        $this->ensurePageTransitions();
        $this->ensureAssetTransitions();
        foreach ($this->current->getPageEntries() as $e)
        {
            $this->onBakeRecordPageEntryAdded($e);
        }
        foreach ($this->current->getAssetEntries() as $e)
        {
            $this->onBakeRecordAssetEntryAdded($e);
        }
    }

    public function saveCurrent($path)
    {
        $this->current->save($path);
    }

    public function collapse()
    {
        $this->ensurePageTransitions();
        foreach ($this->pageTransitions as $trans)
        {
            $prev = $trans[0];
            $cur =$trans[1];

            if ($prev && $cur && !$cur->wasBaked())
            {
                $cur->taxonomy = $prev->taxonomy;
                $cur->usedTaxonomyCombinations = $prev->usedTaxonomyCombinations;
                $cur->usedPages = $prev->usedPages;
                $cur->usedPosts = $prev->usedPosts;
                $cur->outputs = $prev->outputs;
            }
        }

        $this->ensureAssetTransitions();
        foreach ($this->assetTransitions as $trans)
        {
            $prev = $trans[0];
            $cur =$trans[1];

            if ($prev && $cur && !$cur->wasBaked())
            {
                $cur->outputs = $prev->outputs;
            }
        }
    }

    public function getDirtyTaxonomies($taxonomies)
    {
        $this->ensurePageTransitions();

        $result = array();
        foreach ($this->pageTransitions as $trans)
        {
            $prev = $trans[0];
            $cur = $trans[1];

            foreach ($taxonomies as $name => $metadata)
            {
                $terms = false;
                $blogKey = false;
                $isMultiple = $metadata['multiple'];
                if ($prev && !$cur)
                {
                    $blogKey = $prev->blogKey;
                    $terms = $prev->getTerms($name);
                    if ($terms && !$isMultiple)
                        $terms = array($terms);
                }
                else if (!$prev && $cur)
                {
                    $blogKey = $cur->blogKey;
                    $terms = $cur->getTerms($name);
                    if ($terms && !$isMultiple)
                        $terms = array($terms);
                }
                else if ($prev && $cur)
                {
                    $blogKey = $cur->blogKey;
                    $prevTerms = $prev->getTerms($name);
                    $curTerms = $cur->getTerms($name);
                    if ($prevTerms != $curTerms)
                    {
                        if ($isMultiple)
                        {
                            // Get the list of terms that have been added or
                            // deleted...
                            if (!$prevTerms) $prevTerms = array();
                            if (!$curTerms) $curTerms = array();
                            $deleted = array_diff($prevTerms, $curTerms);
                            $added = array_diff($curTerms, $prevTerms);
                            $terms = array_merge($deleted, $added);
                        }
                        else
                        {
                            // Bake both the old (removed) and new (added)
                            // term.
                            $terms = array();
                            if ($prevTerms)
                                $terms[] = $prevTerms;
                            if ($curTerms)
                                $terms[] = $curTerms;
                        }
                    }
                }
                if ($terms && $blogKey)
                {
                    if (!isset($result[$name]))
                        $result[$name] = array();
                    if (!isset($result[$name][$blogKey]))
                        $result[$name][$blogKey] = array();

                    $allTerms = &$result[$name][$blogKey];
                    $allTerms = array_merge($allTerms, $terms);
                }
            }
        }
        // Remove duplicates.
        foreach ($result as $blogKey => &$dirtyTaxonomies)
        {
            foreach ($dirtyTaxonomies as $name => &$dirtyTerms)
            {
                $dirtyTerms = array_map(
                    "unserialize",
                    array_unique(array_map("serialize", $dirtyTerms))
                );
            }
        }
        return $result;
    }

    public function getUsedTaxonomyCombinations($taxonomies)
    {
        // Combinations can only exist for taxonomies that are "multiple".
        $multipleTaxonomies = array_filter(
            $taxonomies,
            function ($i) { return $i['multiple']; }
        );
        if (!$multipleTaxonomies)
            return array();

        $this->ensurePageTransitions();

        $result = array();
        foreach ($this->pageTransitions as $trans)
        {
            $cur = $trans[1];
            if (!$cur)
                continue;

            foreach ($multipleTaxonomies as $name => $metadata)
            {
                // Only look at states that are current (i.e. files that are
                // still there), and which used a taxonomy combination.
                if (isset($cur->usedTaxonomyCombinations[$name]))
                {
                    if (!isset($result[$name]))
                        $result[$name] = array();
                    $allCombinations = &$result[$name];

                    foreach ($cur->usedTaxonomyCombinations[$name] as $blogKey => $combinations)
                    {
                        if (!isset($allCombinations[$blogKey]))
                            $allCombinations[$blogKey] = array();
                        $allCombinationsForBlog = &$allCombinations[$blogKey];

                        $allCombinationsForBlog = array_merge(
                            $allCombinationsForBlog,
                            $cur->usedTaxonomyCombinations[$name][$blogKey]
                        );
                    }
                }
            }
        }
        return $result;
    }

    public function getPagesToDelete()
    {
        $this->ensurePageTransitions();

        $deletedPaths = array();
        foreach ($this->pageTransitions as $trans)
        {
            $prev = $trans[0];
            $cur = $trans[1];

            if ($prev && !$cur)
            {
                $deletedPaths[$prev->path] = array(
                    'type' => self::DELETION_MISSING,
                    'files' => $prev->outputs
                );
            }
            else if ($prev && $cur && $cur->wasBaked())
            {
                $garbageOutputs = array_diff(
                    $prev->outputs,
                    $cur->outputs
                );
                if ($garbageOutputs)
                {
                    $deletedPaths[$prev->path] = array(
                        'type' => self::DELETION_CHANGED,
                        'files' => $garbageOutputs
                    );
                }
            }
        }
        return $deletedPaths;
    }

    public function getAssetsToDelete()
    {
        $this->ensureAssetTransitions();

        $deletedPaths = array();
        foreach ($this->assetTransitions as $trans)
        {
            $prev = $trans[0];
            $cur = $trans[1];

            if ($prev && !$cur)
            {
                $deletedPaths[$prev->path] = $prev->outputs;
            }
        }
        return $deletedPaths;
    }

    public function onBakeRecordPageEntryAdded($entry)
    {
        $this->ensurePageTransitions();

        if (isset($this->pageTransitions[$entry->path]))
        {
            $this->pageTransitions[$entry->path][1] = $entry;
        }
        else
        {
            $this->pageTransitions[$entry->path] = array(null, $entry);
        }
    }

    public function onBakeRecordAssetEntryAdded($entry)
    {
        $this->ensureAssetTransitions();

        if (isset($this->assetTransitions[$entry->path]))
        {
            $this->assetTransitions[$entry->path][1] = $entry;
        }
        else
        {
            $this->assetTransitions[$entry->path] = array(null, $entry);
        }
    }

    protected function ensurePageTransitions()
    {
        if ($this->pageTransitions !== null)
            return;

        $this->pageTransitions = array();
        foreach ($this->previous->getPageEntries() as $entry)
        {
            $this->pageTransitions[$entry->path] = array($entry, null);
        }
    }

    protected function ensureAssetTransitions()
    {
        if ($this->assetTransitions !== null)
            return;

        $this->assetTransitions = array();
        foreach ($this->previous->getAssetEntries() as $entry)
        {
            $this->assetTransitions[$entry->path] = array($entry, null);
        }
    }
}

