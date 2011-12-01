<?php

require_once 'PieCrustFormatterTokenParser.php';

use PieCrust\IPieCrust;
use PieCrust\Util\LinkCollector;
use PieCrust\Util\UriBuilder;


class PieCrustExtension extends Twig_Extension
{
    protected $pieCrust;
    protected $defaultBlogKey;
    protected $postUrlFormat;
    protected $tagUrlFormat;
    protected $categoryUrlFormat;
    
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        
        $blogKeys = $pieCrust->getConfig()->getValueUnchecked('site/blogs');
        $this->defaultBlogKey = $blogKeys[0];
        $this->postUrlFormat = $pieCrust->getConfig()->getValueUnchecked($blogKeys[0].'/post_url');
        $this->tagUrlFormat = $pieCrust->getConfig()->getValueUnchecked($blogKeys[0].'/tag_url');
        $this->categoryUrlFormat = $pieCrust->getConfig()->getValueUnchecked($blogKeys[0].'/category_url');
    }
    
    public function getName()
    {
        return "piecrust";
    }
    
    public function getTokenParsers()
    {
        return array(
            new PieCrustFormatterTokenParser(),
        );
    }
    
    public function getFunctions()
    {
        return array(
            'pcurl'    => new Twig_Function_Method($this, 'getUrl'),
            'pcposturl' => new Twig_Function_Method($this, 'getPostUrl'),
            'pctagurl' => new Twig_Function_Method($this, 'getTagUrl'),
            'pccaturl' => new Twig_Function_Method($this, 'getCategoryUrl')
        );
    }
    
    public function getFilters()
    {
        return array(
            'nocache' => new Twig_Filter_Method($this, 'addNoCacheParameter'),
        );
    }
    
    public function getUrl($value)
    {
        return $this->pieCrust->formatUri($value);
    }
    
    public function getPostUrl($year, $month, $day, $slug, $blogKey = null)
    {
        $postInfo = array(
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'name' => $slug
        );
        $format = ($blogKey == null) ? $this->postUrlFormat : $pieCrust->getConfig()->getValueUnchecked($blogKey.'/post_url');
        return $this->pieCrust->formatUri(UriBuilder::buildPostUri($format, $postInfo));
    }
    
    public function getTagUrl($value, $blogKey = null)
    {
        if (LinkCollector::isEnabled()) LinkCollector::instance()->registerTagCombination($blogKey == null ? $this->defaultBlogKey : $blogKey, $value);
        $format = ($blogKey == null) ? $this->tagUrlFormat : $pieCrust->getConfig()->getValueUnchecked($blogKey.'/tag_url');
        return $this->pieCrust->formatUri(UriBuilder::buildTagUri($format, $value));
    }
    
    public function getCategoryUrl($value, $blogKey = null)
    {
        $format = ($blogKey == null) ? $this->categoryUrlFormat : $pieCrust->getConfig()->getValueUnchecked($blogKey.'/category_url');
        return $this->pieCrust->formatUri(UriBuilder::buildCategoryUri($format, $value));
    }

    public function addNoCacheParameter($value, $parameterName = 't', $parameterValue = null)
    {
        if (!$parameterValue)
            $parameterValue = time();

        if (strpos($value, '?') === false)
            $value .= '?';
        else
            $value .= '&';
        $value .= $parameterName . '=' . $parameterValue;

        return $value;
    }
}
