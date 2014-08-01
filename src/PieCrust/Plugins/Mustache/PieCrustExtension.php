<?php

namespace PieCrust\Plugins\Mustache;

use \Twig_Environment;
use \Twig_Filter_Method;
use \Twig_Function_Method;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\PostInfo;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriBuilder;


class PieCrustExtension
{
    protected $pieCrust;
    protected $linkCollector;
    protected $twigEnvironment;
    
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->linkCollector = $pieCrust->getEnvironment()->getLinkCollector();
    }

    public function initRuntime(Twig_Environment $environment)
    {
        $this->twigEnvironment = $environment;
    }
    
    public function getName()
    {
        return "piecrust";
    }
    

    
    public function getFunctions()
    {
        $extension = $this;
        return array(
            'pcfail'   => function($message) use($extension) {
                    return $extension->throwError($message);
                } ,
            'pcurl'    => function($value) use($extension){
                    return $extension->getUrl($value);
                },
            'pcposturl' => function($text) use($extension){
                $values = explode('|',$text);
                    $year = $values[0];
                    $month= $values[1];
                    $day = $values[2];
                    $slug = $values[3];
                    $blogKey = null;
                    if(isset($values[4])){
                        $blogKey = $values[4];
                    }

                return $this->getPostUrl($year, $month, $day, $slug, $blogKey);
            },
            'pctagurl' =>function($text)use($extension){
                    $values = explode('|',$text);
                    $value = $values[0];
                    $blogKey = null;
                    if(isset($values[1])){
                        $blogKey = $values[1];
                    }
                   return $this->getTagUrl($value, $blogKey);
                },
            'pccaturl' =>function($text) use($extension){
                    $values = explode('|',$text);
                    $value = $values[0];
                    $blogKey = null;
                    if(isset($values[1])){
                        $blogKey = $values[1];
                    }
                    return $this->getTextFrom($value, $blogKey);
                },
            'textfrom' =>function($path) use($extension){
                    return $this->getTextFrom($path);
                },
            'urlencode'=>function($value) {
                    return urlencode($value);
                }
        );
    }
    
    public function getFilters()
    {
        return array(
            // Formatters
            'formatwith' => new Twig_Filter_Method($this, 'transformGeneric'),
            'markdown' => new Twig_Filter_Method($this, 'transformMarkdown'),
            'textile' => new Twig_Filter_Method($this, 'transformTextile'),
            // Utils
            'nocache' => new Twig_Filter_Method($this, 'addNoCacheParameter'),
            'wordcount' => new Twig_Filter_Method($this, 'getWordCount'),
            'stripoutertag' => new Twig_Filter_Method($this, 'stripOuterTag'),
            'stripslash' => new Twig_Filter_Method($this, 'stripSlash'),
            'titlecase' => new Twig_Filter_Method($this, 'titleCase'),
            'atomdate' => new Twig_Filter_Method($this, 'toAtomDate')
        );
    }

    public function throwError($message)
    {
        throw new PieCrustException($message);
    }
    
    public function getUrl($value)
    {
        return PieCrustHelper::formatUri($this->pieCrust, $value);
    }
    
    public function getPostUrl($year, $month, $day, $slug, $blogKey = null)
    {
        $postInfo = PostInfo::fromValues($year, $month, $day, $slug);
        $blogKey = $this->getSafeBlogKey($blogKey);
        return PieCrustHelper::formatUri(
            $this->pieCrust, 
            UriBuilder::buildPostUri($this->pieCrust, $blogKey, $postInfo)
        );
    }
    
    public function getTagUrl($value, $blogKey = null)
    {
        $blogKey = $this->getSafeBlogKey($blogKey);

        if ($this->linkCollector)
            $this->linkCollector->registerTagCombination($blogKey, $value);

        return PieCrustHelper::formatUri(
            $this->pieCrust, 
            UriBuilder::buildTagUri($this->pieCrust, $blogKey, $value)
        );
    }
    
    public function getCategoryUrl($value, $blogKey = null)
    {
        $blogKey = $this->getSafeBlogKey($blogKey);

        return PieCrustHelper::formatUri(
            $this->pieCrust, 
            UriBuilder::buildCategoryUri($this->pieCrust, $blogKey, $value)
        );
    }

    public function getTextFrom($path)
    {
        $path = PathHelper::getAbsolutePath($path, $this->pieCrust->getRootDir());
        if (!is_file($path))
            throw new PieCrustException("Invalid path for 'text_from': {$path}");
        return file_get_contents($path);
    }

    public function transformGeneric($value, $formatterName = null)
    {
        return PieCrustHelper::formatText($this->pieCrust, $value, $formatterName);
    }

    public function transformMarkdown($value)
    {
        return $this->transformGeneric($value, 'markdown');
    }

    public function transformTextile($value)
    {
        return $this->transformGeneric($value, 'textile');
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

    public function getWordCount($value)
    {
        $words = explode(" ", $value);
        return count($words);
    }

    public function stripOuterTag($value, $tag = null)
    {
        $tagPattern = '[a-z]+[a-z0-9]*';
        if ($tag != null)
            $tagPattern = preg_quote($tag, '/');
        $pattern = "/^\\<{$tagPattern}\\>(.*)\\<\\/{$tagPattern}\\>$/i";

        $matches = array();
        if (!preg_match($pattern, $value, $matches))
            return $value;
        return $matches[1];
    }

    public function stripSlash($value)
    {
        return rtrim($value, '/');
    }

    public function titleCase($value)
    {
        return ucwords($value);
    }

    public function toAtomDate($value)
    {
        return twig_date_format_filter($this->twigEnvironment, $value, $format = \DateTime::ATOM);
    }

    private function getSafeBlogKey($blogKey)
    {
        if ($blogKey == null)
        {
            return PieCrustHelper::getDefaultBlogKey($this->pieCrust);
        }
        else
        {
            $blogKeys = $this->pieCrust->getConfig()->getValueUnchecked('site/blogs');
            if (!in_array($blogKey, $blogKeys))
                throw new PieCrustException("No such blog in the website: {$blogKey}");
            return $blogKey;
        }
    }
}

