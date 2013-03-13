<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Util\Configuration;
use PieCrust\Util\PageHelper;


/**
 * The configuration header for a PieCrust page.
 */
class PageConfiguration extends Configuration
{
    protected $page;
    
    public function __construct(IPage $page, array $config = null, $validate = true)
    {
        $this->page = $page; // This needs to be set first because if $validate is 'true',
                             // we'll need access to the page's PieCrust application for
                             // validating configuration values.
        parent::__construct($config, $validate);
    }
    
    protected function validateConfig(array $config)
    {
        return self::getValidatedConfig($this->page, $config);
    }
    
    /**
     * Returns a validated version of the given site configuration.
     *
     * This is exposed as a public static function for convenience (unit tests,
     * etc.)
     */
    public static function getValidatedConfig(IPage $page, $config)
    {
        if (!$config)
        {
            $config = array();
        }
        
        // Add the default page config values.
        $pieCrustConfig = $page->getApp()->getConfig();
        $blogKeys = $pieCrustConfig->getValueUnchecked('site/blogs');
        $validatedConfig = array_merge(
            array(
                'layout' => PageHelper::isPost($page) ? PieCrustDefaults::DEFAULT_POST_TEMPLATE_NAME : PieCrustDefaults::DEFAULT_PAGE_TEMPLATE_NAME,
                'format' => $pieCrustConfig->getValueUnchecked('site/default_format'),
                'template_engine' => $pieCrustConfig->getValueUnchecked('site/default_template_engine'),
                'content_type' => 'html',
                'title' => 'Untitled Page',
                'blog' => ($page->getBlogKey() != null) ? $page->getBlogKey() : $blogKeys[0],
                'segments' => array()
            ),
            $config);

        // Detect common problems.
        if (isset($validatedConfig['category']))
        {
            if (is_array($validatedConfig['category']))
            {
                throw new PieCrustException("This page's `category` is an array -- it must be a string. For multiple values, use `tags` instead.");
            }
        }
        if (isset($validatedConfig['tags']))
        {
           if (!is_array($validatedConfig['tags']))
            {
                $validatedConfig['tags'] = array($validatedConfig['tags']);
            }
        }

        return $validatedConfig;
    }
}
