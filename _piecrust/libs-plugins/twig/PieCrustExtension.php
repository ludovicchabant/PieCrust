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
        
        $blogKeys = $pieCrust->getConfigValueUnchecked('site', 'blogs');
        $this->tagUrlFormat = $pieCrust->getConfigValueUnchecked($blogKeys[0], 'tag_url');
        $this->categoryUrlFormat = $pieCrust->getConfigValueUnchecked($blogKeys[0], 'category_url');
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
    
    public function getTagUrl($value, $blogKey = null)
    {
        $format = ($blogKey == null) ? $this->tagUrlFormat : $pieCrust->getConfigValueUnchecked($blogKey, 'tag_url');
        return $this->pieCrust->formatUri(UriBuilder::buildTagUri($format, $value));
    }
    
    public function getCategoryUrl($value, $blogKey = null)
    {
        $format = ($blogKey == null) ? $this->categoryUrlFormat : $pieCrust->getConfigValueUnchecked($blogKey, 'category_url');
        return $this->pieCrust->formatUri(UriBuilder::buildCategoryUri($format, $value));
    }
}
