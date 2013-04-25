<?php

namespace PieCrust\Baker;

use \Exception;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Page\PageRenderer;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;


/**
 * A class responsible for baking a PieCrust page.
 */
class PageBaker
{
    /**
     * Index filename.
     */
    const BAKE_INDEX_DOCUMENT = 'index.html';
    
    protected $logger;
    protected $bakeDir;
    protected $parameters;
    
    protected $paginationDataAccessed;
    /**
     * Gets whether pagination data was accessed during baking.
     */
    public function wasPaginationDataAccessed()
    {
        return $this->paginationDataAccessed;
    }
    
    /**
     * Gets the number of baked pages.
     */
    public function getPageCount()
    {
        return count($this->bakedFiles);
    }
    
    protected $bakedFiles;
    /**
     * Gets the files that were baked by the last call to 'bake()'.
     */
    public function getBakedFiles()
    {
        return $this->bakedFiles;
    }
    
    /**
     * Creates a new instance of PageBaker.
     */
    public function __construct($bakeDir, array $parameters = array(), $logger = null)
    {
        $this->bakeDir = rtrim(str_replace('\\', '/', $bakeDir), '/') . '/';
        $this->parameters = array_merge(
            array(
                'copy_assets' => false,
                'bake_record' => null
            ), 
            $parameters
        );

        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
    }

    public function getOutputPath(IPage $page)
    {
        $bakePath = $this->bakeDir;
        $isSubPage = ($page->getPageNumber() > 1);
        $decodedUri = rawurldecode($page->getUri());
        $prettyUrls = PageHelper::getConfigValue($page, 'pretty_urls', 'site');
        if ($prettyUrls)
        {
            // Output will be one of:
            // - `uri/name/index.html` (if not a sub-page).
            // - `uri/name/<n>/index.html` (if a sub-page, where <n> is the page number).
            // This works also for URIs with extensions, as it will produce:
            // - `uri/name.ext/index.html`
            // - `uri/name.ext/2/index.html`
            // But wait! If the page has `single_page` set to `true`, and it's not
            // an HTML page, then just use the filename itself:
            // - `uri/name.ext`
            if ($page->getConfig()->getValue('single_page'))
            {
                if ($isSubPage)
                {
                    $pageRelativePath = PageHelper::getRelativePath($page);
                    throw new PieCrustException("Page '{$pageRelativePath}' has `single_page` set to `true` but we're baking sub-page {$page->getPageNumber()}. What the hell?");
                }

                $extension = pathinfo($decodedUri, PATHINFO_EXTENSION);
                if ($extension)
                    $bakePath .= $decodedUri;
                else
                    $bakePath .= $decodedUri . 
                    (($decodedUri == '') ? '' : '/') . 
                    self::BAKE_INDEX_DOCUMENT;
            }
            else
            {
                $bakePath .= $decodedUri . (($decodedUri == '') ? '' : '/');
                if ($isSubPage)
                    $bakePath .= $page->getPageNumber() . '/';
                $bakePath .= self::BAKE_INDEX_DOCUMENT;
            }
        }
        else
        {
            // Output will be one of:
            // - `uri/name.html` (if not a sub-page).
            // - `uri/name/<n>.html` (if a sub-page, where <n> is the page number).
            // If the page has an extension different than `.html`, use that instead, like so:
            // - `uri/name.ext`
            // - `uri/name/<n>.ext`
            // (So in all examples, `name` refers to the name without the extension)
            $name = $decodedUri;
            $extension = pathinfo($decodedUri, PATHINFO_EXTENSION);
            if ($extension)
            {
                // If the page is a tag/category listing, we don't want to pick
                // up any extension from the tag/category name itself! (like if 
                // the tag's name is `blah.php`)
                if (!PageHelper::isTag($page) && !PageHelper::isCategory($page))
                    $name = substr($name, 0, strlen($name) - strlen($extension) - 1);
                else
                    $extension = false;
            }
            if ($decodedUri == '')
            {
                // For the homepage, we have:
                // - `uri/index.html`
                // - `uri/2.html` (if a sub-page)
                if ($isSubPage)
                    $bakePath .= $page->getPageNumber();
                else
                    $bakePath .= 'index';
            }
            else
            {
                $bakePath .= $name;
                if ($isSubPage)
                    $bakePath .= '/' . $page->getPageNumber();
            }
            $bakePath .= '.' . ($extension ? $extension : 'html');
        }
        return $bakePath;
    }
    
    /**
     * Bakes the given page. Additional template data can be provided, along with
     * a specific set of posts for the pagination data.
     */
    public function bake(IPage $page, array $extraData = null)
    {
        $didBake = false;
        try
        {
            $this->bakedFiles = array();
            $this->paginationDataAccessed = false;
            $this->logger->debug("Baking '{$page->getUri()}'...");
            
            $pageRenderer = new PageRenderer($page);
            
            $hasMorePages = true;
            while ($hasMorePages)
            {
                $didBake |= $this->bakeSinglePage($pageRenderer, $extraData);
                
                $data = $page->getPageData();
                if ($data and isset($data['pagination']))
                {
                    $paginator = $data['pagination'];
                    $hasMorePages = ($paginator->wasPaginationDataAccessed() and 
                                     $paginator->hasMorePages());
                    if ($hasMorePages)
                    {
                        $page->setPageNumber($page->getPageNumber() + 1);
                        // setPageNumber() resets the page's data, so when we 
                        // enter bakeSinglePage again in the next loop, we have 
                        // to re-set the extraData and all other stuff.
                    }
                }
            }
        }
        catch (Exception $e)
        {
            $pageRelativePath = PageHelper::getRelativePath($page);
            throw new PieCrustException("Error baking page '{$pageRelativePath}' (p{$page->getPageNumber()})", 0, $e);
        }

        return $didBake;
    }
    
    protected function bakeSinglePage(PageRenderer $pageRenderer, array $extraData = null)
    {
        $page = $pageRenderer->getPage();
        
        // Set the extra template data before the page's data is computed.        
        if ($extraData != null)
            $page->setExtraPageData($extraData);

        // This is usually done in the PieCrustBaker, but we'll do it here too
        // because the `PageBaker` could be used on its own.
        if ($this->parameters['copy_assets'])
            $page->setAssetUrlBaseRemap("%site_root%%uri%");

        // Figure out the output HTML path.
        $bakePath = $this->getOutputPath($page);
        $this->logger->debug("  p{$page->getPageNumber()} -> {$bakePath}");

        // Figure out if we should re-bake this page.
        $doBake = true;
        if (is_file($bakePath) && $this->parameters['smart'])
        {
            // Don't rebake if the output seems up-to-date, and
            // the page isn't known to be using posts.
            if (filemtime($page->getPath()) < filemtime($bakePath))
            {
                $bakeRecord = $this->parameters['bake_record'];
                if ($bakeRecord)
                {
                    $relativePath = PageHelper::getRelativePath($page);
                    if (!$bakeRecord->wasAnyPostBaked() ||
                        !$bakeRecord->isPageUsingPosts($relativePath))
                    {
                        $doBake = false;
                    }
                }
            }
        }
        if (!$doBake)
        {
            $this->logger->debug("Not baking '{$page->getUri()}/{$page->getPageNumber()}' because '{$bakePath}' is up-to-date.");
            return false;
        }

        // Backward compatibility warning and file-copy.
        // [TODO] Remove in a couple of versions.
        $copyToOldPath = false;
        $contentType = $page->getConfig()->getValue('content_type');
        $nativeExtension = pathinfo($page->getPath(), PATHINFO_EXTENSION);
        if ($contentType != 'html' && $nativeExtension == 'html')
        {
            $copyToOldPath = $this->bakeDir . $page->getUri();
            if ($page->getPageNumber() > 1)
                $copyToOldPath .= $page->getPageNumber() . '/';
            $copyToOldPath .= '.' . $contentType;
        }

        // If we're using portable URLs, change the site root to a relative
        // path from the page's directory.
        $savedSiteRoot = $this->setPortableSiteRoot($page->getApp(), $bakePath);
        
        // Render the page.
        $bakedContents = $pageRenderer->get();

        // Get some objects we need.
        $data = $page->getPageData();
        $assetor = $data['assets'];
        $paginator = $data['pagination'];
        
        // Copy the page.
        PathHelper::ensureDirectory(dirname($bakePath));
        file_put_contents($bakePath, $bakedContents);
        $this->bakedFiles[] = $bakePath;

        // [TODO] See previous TODO.
        if ($copyToOldPath)
        {
            $this->logger->warning("Page '{$page->getUri()}' has 'content_type' specified but is an HTML file.");
            $this->logger->warning("Changing a baked file's extension using 'content_type' is deprecated and will be removed in a future version.");
            $this->logger->warning("For backwards compatibility, the page will also be baked to: " . substr($copyToOldPath, strlen($this->bakeDir)));
            $this->logger->warning("To fix the problem, change the source file's extension to the desired output extension.");
            $this->logger->warning("Otherwise, just ignore these messages.");
            file_put_contents($copyToOldPath, $bakedContents);
        }
        
        // Copy any used assets for the first sub-page.
        if ($page->getPageNumber() == 1 and
            $this->parameters['copy_assets'])
        {
            $prettyUrls = PageHelper::getConfigValue($page, 'pretty_urls', 'site');
            if ($prettyUrls)
            {
                $bakeAssetDir = dirname($bakePath) . '/';
            }
            else
            {
                $bakePathInfo = pathinfo($bakePath);
                $bakeAssetDir = $bakePathInfo['dirname'] . '/' . 
                                (($page->getUri() == '') ? '' : $bakePathInfo['filename']) . '/';
            }
            
            $assetPaths = $assetor->getAssetPathnames();
            if ($assetPaths != null)
            {
                PathHelper::ensureDirectory($bakeAssetDir);
                foreach ($assetPaths as $assetPath)
                {
                    $destinationAssetPath = $bakeAssetDir . basename($assetPath);
                    if (@copy($assetPath, $destinationAssetPath) == false)
                        throw new PieCrustException("Can't copy '{$assetPath}' to '{$destinationAssetPath}'.");
                }
            }
        }
        
        // Remember a few things.
        $this->paginationDataAccessed = ($this->paginationDataAccessed or $paginator->wasPaginationDataAccessed());

        // Cleanup.
        if ($savedSiteRoot)
            $page->getApp()->getConfig()->setValue('site/root', $savedSiteRoot);

        return true;
    }

    protected function setPortableSiteRoot(IPieCrust $app, $currentPath)
    {
        $portableUrls = $app->getConfig()->getValue('baker/portable_urls');
        if (!$portableUrls)
            return null;

        $siteRoot = '';
        $curDir = dirname($currentPath);
        while (strlen($curDir) > strlen($this->bakeDir))
        {
            $siteRoot .= '../';
            $curDir = dirname($curDir);
        }
        if ($siteRoot == '')
            $siteRoot = './';

        $savedSiteRoot = $app->getConfig()->getValueUnchecked('site/root');
        $app->getConfig()->setValue('site/root', $siteRoot);

        // We need to unload all loaded pages because their rendered
        // contents will probably be invalid now that the site root changed.
        $repo = $app->getEnvironment()->getPageRepository();
        foreach ($repo->getPages() as $p)
        {
            $p->unload();
        }

        return $savedSiteRoot;
    }
}
