<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\Page\Linker;
use PieCrust\Page\Assetor;
use PieCrust\Page\Paginator;
use PieCrust\Page\Iteration\RecursiveLinkerIterator;
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
        self::mergeProviderData($page, $renderData);
        
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
        self::mergeProviderData($page, $renderData);

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
        // Replace the `site` section with an wrapper object
        // that adds some built-in stuff.
        $siteData = new SiteData($page);
        if (isset($data['site']))
        {
            $siteData->mergeUserData($data['site']);
        }
        $data['site'] = $siteData;
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
        $recursiveLinker = new RecursiveLinkerIterator($linker);

        if ($page->getPaginationDataSource() != null)
            $paginator->setPaginationDataSource($page->getPaginationDataSource());

        $data = array(
            'page' => $page->getConfig()->get(),
            'assets' => $assetor,
            'pagination' => $paginator,
            'siblings' => $linker,
            'family' => $recursiveLinker
        );

        $data['page']['url'] = PieCrustHelper::formatUri($pieCrust, $page->getUri());
        $data['page']['slug'] = $page->getUri();

        $data['page']['timestamp'] = $page->getDate(true);
        $dateFormat = PageHelper::getConfigValueUnchecked(
            $page,
            'date_format',
            $page->getConfig()->getValueUnchecked('blog')
        );
        $data['page']['date'] = date($dateFormat, $page->getDate(true));

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
                if (strpos($data['tag'], '-') >= 0)
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
                        
                        $flags = $pieCrust->getConfig()->getValue('site/slugify_flags');
                        if (is_array($page->getPageKey()))
                        {
                            $pageKey = $page->getPageKey();
                            foreach ($firstPostTags as $t)
                            {
                                $st = UriBuilder::slugify($t, $flags);
                                foreach ($pageKey as &$pk)
                                {
                                    if ($st == $pk)
                                    {
                                        $pk = $t;
                                        break;
                                    }
                                }
                            }
                            if ($page->getPageKey() == null)
                                $page->setPageKey($pageKey);
                            $data['tag'] = implode(' + ', $pageKey);
                        }
                        else
                        {
                            foreach ($firstPostTags as $t)
                            {
                                if (UriBuilder::slugify($t, $flags) == $data['tag'])
                                {
                                    if ($page->getPageKey() == null)
                                        $page->setPageKey($t);
                                    $data['tag'] = $t;
                                    break;
                                }
                            }
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
                        if ($page->getPageKey() == null)
                            $page->setPageKey($firstPost['category']);
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

    public static function mergeProviderData(IPage $page, array &$data)
    {
        foreach ($page->getApp()->getPluginLoader()->getDataProviders() as $provider)
        {
            $providerData = $provider->getPageData($page);
            if ($providerData !== null)
            {
                $endPoint = $provider->getName();
                if (isset($data[$endPoint]))
                    throw new PieCrustException("Can't load data provider: the page configuration already has a value at'{$endPoint}'.");
                $data[$endPoint] = $providerData;
            }
        }
    }
}
