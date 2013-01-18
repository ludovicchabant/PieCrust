<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\Page\Linker;
use PieCrust\Page\RecursiveLinkerIterator;
use PieCrust\Page\Assetor;
use PieCrust\Page\Paginator;
use PieCrust\Util\Configuration;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriBuilder;


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
        $appData = self::getAppData($page->getApp(), $siteData, $pageData, null, false);

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
        // Add the pages linker.
        if (!isset($data['site']))
            $data['site'] = array();
        $linker = new Linker($page, $pieCrust->getPagesDir());
        $linkerIterator = new RecursiveLinkerIterator($linker);
        $data['site']['pages'] = $linkerIterator;
        // Done!
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

        $data['page']['timestamp'] = $page->getDate();
        $dateFormat = PageHelper::getConfigValueUnchecked(
            $page,
            'date_format',
            $page->getConfig()->getValueUnchecked('blog')
        );
        $data['page']['date'] = date($dateFormat, $page->getDate());

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
                if (strpos($page->getPageKey(), '-') >= 0)
                {
                    // The tag may have been slugified. Let's cheat a bit by looking at
                    // the first tag that matches in the first pagination post, and
                    // using that instead.
                    $paginationPosts = $paginator->posts();
                    if (count($paginationPosts) > 0)
                    {
                        $firstPost = $paginationPosts[0];
                        $firstPostTags = $firstPost['tags'];
                        if (!is_array($firstPostTags))
                            $firstPostTags = array($firstPostTags);
                        foreach ($firstPostTags as $t)
                        {
                            if (UriBuilder::slugify($t) == $data['tag'])
                                $data['tag'] = $t;
                        }
                    }
                }
                break;
            case IPage::TYPE_CATEGORY:
                $data['category'] = $page->getPageKey();
                if (strpos($page->getPageKey(), '-') >= 0)
                {
                    // Same remark as for tags.
                    $paginationPosts = $paginator->posts();
                    if (count($paginationPosts) > 0)
                    {
                        $firstPost = $paginationPosts[0];
                        $data['category'] = $firstPost['category'];
                    }
                }
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
