<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
use PieCrust\Page\PageRepository;
use PieCrust\Util\UriBuilder;


class PagePropertyData implements \ArrayAccess, \Iterator
{
    protected $pieCrust;
    protected $propertyName;
    protected $blogKey;

    protected $values;

    public function __construct(IPieCrust $pc, $propertyName)
    {
        $this->pieCrust = $pc;
        $this->propertyName = $propertyName;

        $blogKeys = $pc->getConfig()->getValueUnchecked('site/blogs');
        $this->blogKey = $blogKeys[0];
    }

    // {{{ Template Methods
    public function blog($blogName)
    {
        if ($this->values)
            $this->values = null;

        $blogKeys = $pc->getConfig()->getValueUnchecked('site/blogs');
        if (!in_array($blogName, $blogKeys))
            throw new PieCrustException("No blog named '" . $blogName . "' was declared in the configuration file.");
        $this->blogKey = $blogName;
    }
    // }}}

    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureLoaded();
        return isset($this->values[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensureLoaded();
        return $this->values[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException('The ' . $this->propertyName . ' list is read-only.');
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException('The ' . $this->propertyName . ' list is read-only.');
    }
    // }}}
    
    // {{{ Iterator members
    public function rewind()
    {
        $this->ensureLoaded();
        return reset($this->values);
    }
  
    public function current()
    {
        $this->ensureLoaded();
        return current($this->values);
    }
  
    public function key()
    {
        $this->ensureLoaded();
        return key($this->values);
    }
  
    public function next()
    {
        $this->ensureLoaded();
        return next($this->values);
    }
  
    public function valid()
    {
        $this->ensureLoaded();
        return key($this->values) !== null;
    }
    // }}}
 
    protected function ensureLoaded()
    {
        if ($this->values)
            return;

        $this->values = array();

        $postsUrlFormat = $this->pieCrust->getConfig()->getValueUnchecked($this->blogKey.'/post_url');

        $fs = FileSystem::create($this->pieCrust, $this->blogKey);
        $postInfos = $fs->getPostFiles();
        
        foreach ($postInfos as $postInfo)
        {
            if (isset($postInfo['page']))
            {
                $page = $postInfo['page'];
            }
            else
            {
                $page = PageRepository::getOrCreatePage(
                    $this->pieCrust,
                    UriBuilder::buildPostUri($postsUrlFormat, $postInfo), 
                    $postInfo['path'],
                    IPage::TYPE_POST,
                    $this->blogKey);
            }

            $propertyValues = $page->getConfig()->getValue($this->propertyName);
            if ($propertyValues)
            {
                if (!is_array($propertyValues))
                    $propertyValues = array($propertyValues);

                foreach ($propertyValues as $v)
                {
                    if (!isset($this->values[$v]))
                    {
                        $this->values[$v] = array(
                            'post_count' => 0,
                            'name' => $v
                        );
                    }
                    $this->values[$v]['post_count'] += 1;
                }
            }
        }

        ksort($this->values);
    }
}

