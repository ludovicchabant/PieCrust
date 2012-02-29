<?php

namespace PieCrust\Baker;

use \Exception;
use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Page\PageRenderer;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PageHelper;


/**
 * A class responsible for baking a PieCrust page.
 */
class PageBaker
{
    /**
     * Index filename.
     */
    const BAKE_INDEX_DOCUMENT = 'index.html';
    
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
    public function __construct($bakeDir, array $parameters = array())
    {
        $this->bakeDir = rtrim(str_replace('\\', '/', $bakeDir), '/') . '/';
        $this->parameters = array_merge(
            array(
                'copy_assets' => false
            ), 
            $parameters
        );
    }
    
    /**
     * Bakes the given page. Additional template data can be provided, along with
     * a specific set of posts for the pagination data.
     */
    public function bake(IPage $page, array $extraData = null)
    {
        try
        {
            $this->bakedFiles = array();
            $this->paginationDataAccessed = false;
            
            $pageRenderer = new PageRenderer($page);
            
            $hasMorePages = true;
            while ($hasMorePages)
            {
                $this->bakeSinglePage($pageRenderer, $extraData);
                
                $data = $page->getPageData();
                if ($data and isset($data['pagination']))
                {
                    $paginator = $data['pagination'];
                    $hasMorePages = ($paginator->wasPaginationDataAccessed() and $paginator->hasMorePages());
                    if ($hasMorePages)
                    {
                        $page->setPageNumber($page->getPageNumber() + 1);
                        // setPageNumber() resets the page's data, so when we enter bakeSinglePage again
                        // in the next loop, we have to re-set the extraData and all other stuff.
                    }
                }
            }
        }
        catch (Exception $e)
        {
            throw new PieCrustException("Error baking page '" . $page->getUri() . "' (p" . $page->getPageNumber() . "): " . $e->getMessage(), 0, $e);
        }
    }
    
    protected function bakeSinglePage(PageRenderer $pageRenderer, array $extraData = null)
    {
        $page = $pageRenderer->getPage();
        
        // Set the extraData and asset URL remapping before the page's data is computed.        
        if ($extraData != null)
            $page->setExtraPageData($extraData);
        if ($this->parameters['copy_assets'] === true)
            $page->setAssetUrlBaseRemap("%site_root%%uri%");
        
        // Render the page.
        $bakedContents = $pageRenderer->get();

        // Get some objects we need.
        $data = $page->getPageData();
        $assetor = $data['asset'];
        $paginator = $data['pagination'];
        
        // Figure out the output HTML path.
        $useDirectory = PageHelper::getConfigValue($page, 'pretty_urls', 'site');
        $contentType = $page->getConfig()->getValue('content_type');
        if ($contentType != 'html')
        {
            // If this is not an HTML file, don't use a directory as the output
            // (since this would bake it to an 'index.html' file).
            $useDirectory = false;
        }
        
        if ($paginator->wasPaginationDataAccessed() and !$page->getConfig()->getValue('single_page'))
        {
            // If pagination data was accessed, there may be sub-pages for this page,
            // so we need the 'directory' naming scheme to store them (unless this
            // page is forced to a single page).
            $useDirectory = true;
        }
        
        // Figure out the output file/directory for the page.
        if ($useDirectory)
        {
            $bakePath = ($this->bakeDir . 
                         $page->getUri() . 
                         (($page->getUri() == '') ? '' : '/') . 
                         (($page->getPageNumber() == 1) ? '' : ($page->getPageNumber() . '/')) .
                         self::BAKE_INDEX_DOCUMENT);
        }
        else
        {
            $extension = $this->getBakedExtension($contentType);
            $bakePath = $this->bakeDir . (($page->getUri() == '') ? 'index' : $page->getUri()) . '.' . $extension;
        }
        
        // Copy the page.
        FileSystem::ensureDirectory(dirname($bakePath));
        file_put_contents($bakePath, $bakedContents);
        $this->bakedFiles[] = $bakePath;
        
        // Copy any used assets for the first sub-page.
        if ($page->getPageNumber() == 1 and $this->parameters['copy_assets'] === true)
        {
            if ($useDirectory)
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
                FileSystem::ensureDirectory($bakeAssetDir);
                foreach ($assetPaths as $assetPath)
                {
                    $destinationAssetPath = $bakeAssetDir . basename($assetPath);
                    if (@copy($assetPath, $destinationAssetPath) == false)
                        throw new PieCrustException("Can't copy '".$assetPath."' to '".$destinationAssetPath."'.");
                }
            }
        }
        
        $this->paginationDataAccessed = ($this->paginationDataAccessed or $paginator->wasPaginationDataAccessed());
    }
    
    protected function getBakedExtension($contentType)
    {
        switch ($contentType)
        {
            case 'text':
                return 'txt';
            default:
                return $contentType;
        }
    }
}
