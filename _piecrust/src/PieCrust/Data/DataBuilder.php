<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\Page\Linker;
use PieCrust\Page\Assetor;
use PieCrust\Page\Paginator;
use PieCrust\Util\Configuration;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


/**
 * Builds the data for page rendering.
 */
class DataBuilder
{
    /**
     * Gets all the template data for rendering a page's contents.
     */
    public static function getPageRenderingData(IPage $page)
    {
        $pageData = $page->getPageData();
        $siteData = self::getSiteData($page);
        $appData = DataBuilder::getAppData($page->getApp(), $siteData, $pageData, null, false);

        $renderData = Configuration::mergeArrays(
            $pageData,
            $siteData,
            $appData
        );
        return $renderData;
    }

    /**
     * Gets all the template data for rendering a layout.
     */
    public static function getTemplateRenderingData(IPage $page)
    {
        $pieCrust = $page->getApp();
        $pageData = $page->getPageData();
        $pageContentSegments = $page->getContentSegments();
        $siteData = self::getSiteData($page);
        $appData = self::getAppData(
            $pieCrust, 
            $siteData, 
            $pageData, 
            $pageContentSegments, 
            $page->wasCached()
        );
        $renderData = Configuration::mergeArrays(
            $appData,
            $siteData,
            $pageData,
            $pageContentSegments
        );
        return $renderData;
    }

    /**
     * Gets the application's data for page rendering.
     */
    public static function getAppData(IPieCrust $pieCrust, $siteData = null, $pageData = null, $pageContentSegments = null, $wasPageCached = null)
    {
        return array(
            'piecrust' => new PieCrustData($pieCrust, $siteData, $pageData, $pageContentSegments, $wasPageCached)
        );
    }

    /**
     * Gets the site's data for page rendering.
     */
    public static function getSiteData(IPage $page)
    {
        $pieCrust = $page->getApp();
        // Get the site configuration.
        $data = $pieCrust->getConfig()->get();
        // Combine it with each blog's data.
        foreach ($pieCrust->getConfig()->getValueUnchecked('site/blogs') as $blogKey)
        {
            $blogData = new BlogData($page, $blogKey);
            if (isset($data[$blogKey]))
            {
                $blogData->mergeUserData($data[$blogKey]);
            }
            $data[$blogKey] = $blogData;
        }
        return $data;
    }

    /**
     * Gets the page's data for page rendering.
     *
     * It's better to call IPage::getData, which calls this function, because it
     * will also cache the results. It's useful for example when pagination
     * results needs to be re-used.
     */
    public static function getPageData(IPage $page)
    {
        $pieCrust = $page->getApp();
        
        $paginator = new Paginator($page);
        $assetor = new Assetor($page);
        $linker = new Linker($page);
 
        if ($page->getPaginationDataSource() != null)
            $paginator->setPaginationDataSource($page->getPaginationDataSource());

        $data = array(
            'page' => $page->getConfig()->get(),
            'asset'=> $assetor,
            'pagination' => $paginator,
            'link' => $linker
        );
        
        $data['page']['url'] = PieCrustHelper::formatUri($pieCrust, $page->getUri());
        $data['page']['slug'] = $page->getUri();
        
        if ($page->getConfig()->getValue('date'))
            $timestamp = strtotime($page->getConfig()->getValue('date'));
        else
            $timestamp = $page->getDate();
        if ($page->getConfig()->getValue('time'))
            $timestamp = strtotime($page->getConfig()->getValue('time'), $timestamp);
        $data['page']['timestamp'] = $timestamp;
        $dateFormat = PageHelper::getConfigValue(
            $page, 
            'date_format', 
            ($page->getBlogKey() != null ? $page->getBlogKey() : 'site')
        );
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
