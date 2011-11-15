<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\PieCrust;
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
                'layout' => PageHelper::isPost($page) ? PieCrust::DEFAULT_POST_TEMPLATE_NAME : PieCrust::DEFAULT_PAGE_TEMPLATE_NAME,
                'format' => $pieCrustConfig->getValueUnchecked('site/default_format'),
                'template_engine' => $pieCrustConfig->getValueUnchecked('site/default_template_engine'),
                'content_type' => 'html',
                'title' => 'Untitled Page',
                'blog' => ($page->getBlogKey() != null) ? $page->getBlogKey() : $blogKeys[0],
                'segments' => array()
            ),
            $config);
        return $validatedConfig;
    }
}
