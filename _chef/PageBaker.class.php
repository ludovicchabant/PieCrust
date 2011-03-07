<?php

/**
 *
 */
class PageBaker
{
    protected $pieCrust;
    protected $bakeDir;
	
	protected $wasPaginationDataAccessed;
	/**
	 *
	 */
	public function wasPaginationDataAccessed()
	{
		return $this->wasPaginationDataAccessed;
	}
	
	protected $pageCount;
	/**
	 *
	 */
	public function getPageCount()
	{
		return $this->pageCount;
	}
    
    /**
     *
     */
    public function __construct(PieCrust $pieCrust, $bakeDir)
    {
        $this->pieCrust = $pieCrust;
        $this->bakeDir = $bakeDir;
    }
    
    /**
     *
     */
    public function bake(Page $page, array $postInfos = null, array $extraData = null)
	{
		$this->pageCount = 0;
		$this->wasPaginationDataAccessed = false;
		
		$pageRenderer = new PageRenderer($this->pieCrust);
		
		$hasMorePages = true;
		while ($hasMorePages)
		{
			echo '.';
			$hasMorePages = $this->bakeSinglePage($page, $pageRenderer, $postInfos, $extraData);
			if ($hasMorePages)
			{
				$page->setPageNumber($page->getPageNumber() + 1);
				// setPageNumber() resets the page's data, so when we enter bakeSinglePage again
				// in the next loop, we have to re-set the extraData and all other stuff.
			}
		}
		
		$this->pageCount = $page->getPageNumber();
	}
	
	protected function bakeSinglePage(Page $page, PageRenderer $pageRenderer, array $postInfos = null, array $extraData = null)
	{
		// Set the extraData and asset URL remapping before the page's data is computed.
		$page->setAssetUrlBaseRemap("%host%%url_base%%uri%");
		if ($extraData != null) $page->setExtraPageData($extraData);
		
		// Set the custom stuff.
		$assetor = $page->getAssetor();
		$paginator = $page->getPaginator();
		if ($postInfos != null) $paginator->buildPaginationData($postInfos);
		
		// Render the page.
		$bakedContents = $pageRenderer->get($page, null, false);
		
		// Bake the page into the correct HTML file, and figure out
		// if there are more pages to bake for this page.
		$useDirectory = $page->getConfigValue('pretty_urls');
		if ($useDirectory == null)
		{
			$useDirectory = ($this->pieCrust->getConfigValue('site', 'pretty_urls') == true);
		}
		
		if ($paginator->wasPaginationDataAccessed())
		{
			// If pagination data was accessed, there may be sub-pages for this page,
			// so we need the 'directory' naming scheme to store them.
			$useDirectory = true;
		}
		
		// Figure out the output file/directory for the page.
		if ($useDirectory)
		{
			$bakePath = ($this->bakeDir . 
						 $page->getUri() . 
						 (($page->getUri() == '') ? '' : DIRECTORY_SEPARATOR) . 
						 (($page->getPageNumber() == 1) ? '' : ($page->getPageNumber() . DIRECTORY_SEPARATOR)) .
						 PIECRUST_BAKE_INDEX_DOCUMENT);
		}
		else
		{
			$extension = $this->getBakedExtension($page->getConfigValue('content_type'));
			$bakePath = $this->bakeDir . $page->getUri() . '.' . $extension;
		}
		
		// Copy the page.
		FileSystem::ensureDirectory(dirname($bakePath));
		file_put_contents($bakePath, $bakedContents);
		
		// Copy any used assets.
		if ($useDirectory)
		{
			$bakeAssetDir = dirname($bakePath) . DIRECTORY_SEPARATOR;
		}
		else
		{
			$bakePathInfo = pathinfo($bakePath);
			$bakeAssetDir = $bakePathInfo['dirname'] . DIRECTORY_SEPARATOR . 
							(($page->getUri() == '') ? '' : $bakePathInfo['filename']) . DIRECTORY_SEPARATOR;
			FileSystem::ensureDirectory($bakeAssetDir);
		}
		$assetPaths = $assetor->getAssetPathnames();
		if ($assetPaths != null)
		{
			foreach ($assetPaths as $assetPath)
			{
				$assetPathInfo = pathinfo($assetPath);
				copy($assetPath, ($bakeAssetDir . $assetPathInfo['basename']));
			}
		}

		$this->wasPaginationDataAccessed = ($this->wasPaginationDataAccessed or $paginator->wasPaginationDataAccessed());
		$hasMorePages = ($paginator->wasPaginationDataAccessed() and $paginator->hasMorePages());
		return $hasMorePages;
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
