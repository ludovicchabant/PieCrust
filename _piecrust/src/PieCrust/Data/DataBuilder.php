<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\Page\Linker;
use PieCrust\Page\Assetor;
use PieCrust\Page\Paginator;
use PieCrust\Util\Configuration;
use PieCrust\Util\PageHelper;


/**
 * Builds the data for page rendering.
 */
class DataBuilder
{
    /**
     * Gets the application's data for page rendering.
     */
    public static function getSiteData(IPieCrust $pieCrust, $pageData = null, $pageContentSegments = null, $wasPageCached = null)
     {
        $data = Configuration::mergeArrays(
            $pieCrust->getConfig()->get(), 
            array(
                'categories' => new PagePropertyData($pieCrust, 'category'),
                'tags' => new PagePropertyData($pieCrust, 'tags'),
                'piecrust' => new PieCrustData($pieCrust, $pageData, $pageContentSegments, $wasPageCached)
            )
         );
        return $data;
    }
    
   /**
    * Gets the page's data for page rendering.
    */
    public static function getPageData(IPage $page)
    {
        $pieCrust = $page->getApp();
        
        $paginator = new Paginator($page);
        $assetor = new Assetor($page);
        $linker = new Linker($page);
        
        $data = array(
            'page' => $page->getConfig()->get(),
            'asset'=> $assetor,
            'pagination' => $paginator,
            'link' => $linker
        );
        
        $data['page']['url'] = $pieCrust->formatUri($page->getUri());
        $data['page']['slug'] = $page->getUri();
        
        if ($page->getConfig()->getValue('date'))
            $timestamp = strtotime($page->getConfig()->getValue('date'));
        else
            $timestamp = $page->getDate();
        if ($page->getConfig()->getValue('time'))
            $timestamp = strtotime($page->getConfig()->getValue('time'), $timestamp);
        $data['page']['timestamp'] = $timestamp;
        $dateFormat = PageHelper::getConfigValue($page, 'date_format', ($page->getBlogKey() != null ? $page->getBlogKey() : 'site'));
        $data['page']['date'] = date($dateFormat, $timestamp);
        
        switch ($page->getPageType())
        {
            case IPage::TYPE_TAG:
                if (is_array($page->getPageKey()))
                {
                    $data['tag'] = implode(' + ', $page->getPageKey());
                }
                else
                {
                    $data['tag'] = $page->getPageKey();
                }
                break;
            case IPage::TYPE_CATEGORY:
                $data['category'] = $page->getPageKey();
                break;
        }
        
        $extraData = $page->getExtraPageData();
        if ($extraData)
        {
            if (is_array($extraData))
            {
                $data = Configuration::mergeArrays(
                    $data,
                    $extraData
                );
            }
            else
            {
                $data['extra'] = $extraData;
            }
        }
        
        return $data;
    }
}
