<?php

require_once (PIECRUST_APP_DIR . 'Paginator.class.php');


class PieCrustExtension extends Twig_Extension
{
    protected $pieCrust;
	protected $pathPrefix;
    protected $tagUrlFormat;
    protected $categoryUrlFormat;
    
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        
        $usePrettyUrls = ($pieCrust->getConfigValue('site','pretty_urls') === true);		
		$this->pathPrefix = ($pieCrust->getHost() . $pieCrust->getUrlBase() . ($usePrettyUrls ? '' : '?/'));
        
        $this->tagUrlFormat = $pieCrust->getConfigValue('site', 'tags_urls');
        $this->categoryUrlFormat = $pieCrust->getConfigValue('site', 'categories_urls');
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
        return $this->pathPrefix . $value;
    }
    
    public function getTagUrl($value)
    {
        return $this->pathPrefix . Paginator::buildTagUrl($this->tagUrlFormat, $value);
    }
    
    public function getCategoryUrl($value)
    {
        return $this->pathPrefix . Paginator::buildCategoryUrl($this->categoryUrlFormat, $value);
    }
}
