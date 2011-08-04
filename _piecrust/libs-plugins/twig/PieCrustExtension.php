<?php

require_once (PIECRUST_APP_DIR . 'Paginator.class.php');


class PieCrustExtension extends Twig_Extension
{
    protected $pieCrust;
    protected $tagUrlFormat;
    protected $categoryUrlFormat;
    
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        
        $this->tagUrlFormat = $pieCrust->getConfigValueUnchecked('site', 'tag_url');
        $this->categoryUrlFormat = $pieCrust->getConfigValueUnchecked('site', 'category_url');
    }
    
    public function getName()
    {
        return "piecrust";
    }
    
    public function getFunctions()
    {
        return array(
            'pcurl'    => new Twig_Function_Method($this, 'getUrl'),
            'pctagurl' => new Twig_Function_Method($this, 'getTagUrl'),
            'pccaturl' => new Twig_Function_Method($this, 'getCategoryUrl')
        );
    }
    
    public function getUrl($value)
    {
        return $this->pieCrust->formatUri($value);
    }
    
    public function getTagUrl($value)
    {
        return $this->pieCrust->formatUri(UriBuilder::buildTagUri($this->tagUrlFormat, $value));
    }
    
    public function getCategoryUrl($value)
    {
        return $this->pieCrust->formatUri(UriBuilder::buildCategoryUri($this->categoryUrlFormat, $value));
    }
}
