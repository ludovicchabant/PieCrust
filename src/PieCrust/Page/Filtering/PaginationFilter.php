<?php

namespace PieCrust\Page\Filtering;

use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Util\UriBuilder;


/**
 * Filters posts based on a simple tree of filtering clauses.
 */
class PaginationFilter
{
    protected $rootClause;
    
    public function __construct()
    {
        $this->rootClause = null;
    }

    public function getRootClause()
    {
        return $this->getSafeRootClause();
    }
    
    public function hasClauses()
    {
        return $this->rootClause != null;
    }
    
    public function addClause(IClause $clause)
    {
        $this->getSafeRootClause()->addClause($clause);
    }
    
    public function addClauses(array $filterInfo)
    {
        $this->addClausesRecursive($filterInfo, $this->getSafeRootClause());
    }
    
    public function addPageClauses(IPage $page, array $userFilterInfo = null)
    {
        // If the current page is a tag/category page, add filtering
        // for that.
        $pageClause = null;
        $pieCrust = $page->getApp();
        $flags = $pieCrust->getConfig()->getValue('site/slugify_flags');
        switch ($page->getPageType())
        {
        case IPage::TYPE_TAG:
            $pageKey = $page->getPageKey();
            if (is_array($pageKey))
            {
                $pageClause = new AndBooleanClause();
                foreach ($pageKey as $k)
                {
                    $pageClause->addClause(
                        new HasFilterClause(
                            'tags',
                            $k,
                            function($t) use ($flags) {
                                return UriBuilder::slugify($t, $flags);
                            }
                        )
                    );
                }
            }
            else
            {
                $pageClause = new HasFilterClause(
                    'tags',
                    $pageKey,
                    function($t) use ($flags) {
                        return UriBuilder::slugify($t, $flags);
                    }
                );
            }
            break;
        case IPage::TYPE_CATEGORY:
            $pageClause = new IsFilterClause(
                'category',
                $page->getPageKey(),
                function($c) use ($flags) {
                    return UriBuilder::slugify($c, $flags);
                }
            );
            break;
        }

        if ($pageClause != null)
        {
            // Combine the default page filters with some user filters,
            // if any.
            if ($userFilterInfo != null)
            {
                $combinedClause = new AndBooleanClause();
                $combinedClause->addClause($pageClause);
                $this->addClausesRecursive($userFilterInfo, $combinedClause);
                $this->addClause($combinedClause);
            }
            else
            {
                $this->addClause($pageClause);
            }
        }
    }
    
    public function postMatches(IPage $post)
    {
        if ($this->rootClause == null)
            return true;
        return $this->rootClause->postMatches($post);
    }
    
    protected function addClausesRecursive(array $filterInfo, IClause $clause)
    {
        foreach ($filterInfo as $key => $value)
        {
            if (is_int($key))
            {
                $this->addClausesRecursive($value, $clause);
            }
            else if ($key == 'and')
            {
                if (!is_array($value) or count($value) == 0)
                    throw new PieCrustException("The given boolean 'AND' filter clause doesn't have an array of child clauses.");
                $subClause = new AndBooleanClause();
                $clause->addClause($subClause);
                $this->addClausesRecursive($value, $subClause);
            }
            else if ($key == 'or')
            {
                if (!is_array($value) or count($value) == 0)
                    throw new PieCrustException("The given boolean 'AND' filter clause doesn't have an array of child clauses.");
                $subClause = new OrBooleanClause();
                $clause->addClause($subClause);
                $this->addClausesRecursive($value, $subClause);
            }
            else if ($key == 'not')
            {
                if (!is_array($value) or count($value) != 1)
                    throw new PieCrustException("'NOT' filter clauses must have exactly one child clause.");
                $subClause = new NotClause();
                $clause->addClause($subClause);
                $this->addClausesRecursive($value, $subClause);
            }
            else if (substr($key, 0, 4) === 'has_')
            {
                $settingName = substr($key, 4);
                if (is_array($value))
                {
                    $wrapper = new AndBooleanClause();
                    foreach ($value as $v)
                    {
                        $wrapper->addClause(new HasFilterClause($settingName, $v));
                    }
                    $clause->addClause($wrapper);
                }
                else
                {
                    $clause->addClause(new HasFilterClause($settingName, $value));
                }
            }
            else if (substr($key, 0, 3) === 'is_')
            {
                $settingName = substr($key, 3);
                $clause->addClause(new IsFilterClause($settingName, $value));
            }
            else
            {
                throw new PieCrustException("Unknown filter clause: " . $key);
            }
        }
    }
    
    protected function getSafeRootClause()
    {
        if ($this->rootClause == null)
            $this->rootClause = new AndBooleanClause();
        return $this->rootClause;
    }
}
