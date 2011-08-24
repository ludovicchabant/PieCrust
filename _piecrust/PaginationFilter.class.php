<?php

interface IClause
{
    public function addClause(IClause $clause);
    public function postMatches(Page $post);
}

abstract class BooleanClause implements IClause
{
    protected $clauses;
    
    protected function __construct()
    {
        $this->clauses = array();
    }
    
    public function addClause(IClause $clause)
    {
        $this->clauses[] = $clause;
    }
}

class OrBooleanClause extends BooleanClause
{
    public function __construct()
    {
        BooleanClause::__construct();
    }
    
    public function postMatches(Page $post)
    {
        foreach ($this->clauses as $c)
        {
            if ($c->postMatches($post))
                return true;
        }
        return false;
    }
}

class AndBooleanClause extends BooleanClause
{
    public function __construct()
    {
        BooleanClause::__construct();
    }
    
    public function postMatches(Page $post)
    {
        foreach ($this->clauses as $c)
        {
            if (!$c->postMatches($post))
                return false;
        }
        return true;
    }
}

abstract class FilterClause implements IClause
{
    protected $settingName;
    protected $settingValue;
    
    protected function __construct($settingName, $settingValue)
    {
        $this->settingName = $settingName;
        $this->settingValue = $settingValue;
    }
    
    public function addClause(IClause $clause)
    {
        throw new PieCrustException("Filter clauses can't have child clauses. Use a boolean clause instead.");
    }
}

class HasFilterClause extends FilterClause
{
    public function __construct($settingName, $settingValue)
    {
        FilterClause::__construct($settingName, $settingValue);
    }
    
    public function postMatches(Page $post)
    {
        $actualValue = $post->getConfigValue($this->settingName);
        return $actualValue != null && in_array($this->settingValue, $actualValue);
    }
}

class IsFilterClause extends FilterClause
{
    public function __construct($settingName, $settingValue)
    {
        FilterClause::__construct($settingName, $settingValue);
    }
    
    public function postMatches(Page $post)
    {
        $actualValue = $post->getConfigValue($this->settingName);
        return $actualValue != null && $actualValue == $this->settingValue;
    }
}


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
    
    public function postMatches($post)
    {
        if ($this->rootClause == null)
            return true;
        return $this->rootClause->postMatches($post);
    }
    
    protected function addClausesRecursive(array $filterInfo, IClause $clause)
    {
        foreach ($filterInfo as $key => $value)
        {
            if ($key == 'and')
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
        }
    }
    
    protected function getSafeRootClause()
    {
        if ($this->rootClause == null)
            $this->rootClause = new AndBooleanClause();
        return $this->rootClause;
    }
}
