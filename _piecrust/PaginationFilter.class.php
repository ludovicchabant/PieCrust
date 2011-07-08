<?php

define('PAGINATIONFILTER_HAS_SETTING', 1);
define('PAGINATIONFILTER_IS_SETTING', 2);


class PaginationFilter
{
    protected $clauses;
    
    public function __construct()
    {
        $this->clauses = array();
    }
    
    public function hasClauses()
    {
        return count($this->clauses) > 0;
    }
    
    public function addClauses(array $filterInfo)
    {
        foreach ($filterInfo as $key => $value)
        {
            if (substr($key, 0, 4) === 'has_')
            {
                $settingName = substr($key, 4);
                $this->addClause(PAGINATIONFILTER_HAS_SETTING, $settingName, $value);
            }
            else if (substr($key, 0, 3) === 'is_')
            {
                $settingName = substr($key, 3);
                $this->addClause(PAGINATIONFILTER_IS_SETTING, $settingName, $value);
            }
        }
    }
    
    public function addHasClause($settingName, $settingValue)
    {
        $this->addClause(PAGINATIONFILTER_HAS_SETTING, $settingName, $settingValue);
    }
    
    public function addIsClause($settingName, $settingValue)
    {
        $this->addClause(PAGINATIONFILTER_IS_SETTING, $settingName, $settingValue);
    }
    
    public function addClause($clauseType, $settingName, $settingValue)
    {
        $this->clauses[] = array(
            'type' => $clauseType,
            'key' => $settingName,
            'value' => $settingValue
        );
    }
    
    public function postMatches($post)
    {
        foreach ($this->clauses as $clause)
        {
            $setting = $post->getConfigValue($clause['key']);
            
            switch ($clause['type'])
            {
                case PAGINATIONFILTER_IS_SETTING:
                    return $setting != null && $setting == $clause['value'];
                case PAGINATIONFILTER_HAS_SETTING:
                    return $setting != null && in_array($clause['value'], $setting);
            }
        }
        return false;
    }
}
