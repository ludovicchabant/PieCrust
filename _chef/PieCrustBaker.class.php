<?php

define('PIECRUST_BAKE_INDEX_DOCUMENT', 'index.html');
define('PIECRUST_BAKE_TIMESTAMP', 'bakestamp.txt');

require_once 'PieCrust.class.php';
require_once 'Paginator.class.php';
require_once 'FileSystem.class.php';


/**
 * A class that 'bakes' a PieCrust website into a bunch of static HTML files.
 */
class PieCrustBaker
{
	protected $pieCrust;
	
	protected $parameters;
	
	protected $postInfos;
	protected $postTags;
	protected $postCategories;
	protected $tagsToBake;
	protected $categoriesToBake;
	
	protected $dependencies;
	/**
	 * Adds a directory to deploy along with the baked files.
	 */
	public function addDependencyDir($dir, $isRelative = true)
	{
		$this->dependencies[] = $dir;
	}
	
	protected $bakeDir;
	/**
	 * Gets the bake (output) directory.
	 */
	public function getBakeDir()
	{
		if ($this->bakeDir === null)
		{
			$defaultBakeDir = $this->pieCrust->getCacheDir() . 'baked/';
			FileSystem::ensureDirectory($defaultBakeDir);
            $this->setBakeDir($defaultBakeDir);
		}
		return $this->bakeDir;
	}
	
	/**
	 * Sets the bake (output) directory.
	 */
	public function setBakeDir($dir)
	{
		$this->bakeDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
		if (is_writable($this->bakeDir) === false)
		{
			throw new PieCrustException('The bake directory must be writable: ' . $this->bakeDir);
		}
	}
	
	protected $lastBakeTime;
	/**
	 *
	 */
	public function getLastBakeTime()
	{
		if ($this->lastBakeTime === null)
		{
			$bakestampPath = $this->getBakeDir() . PIECRUST_BAKE_TIMESTAMP;
			if (is_file($bakestampPath))
			{
				$bakestamp = file_get_contents($bakestampPath);
				$this->lastBakeTime = intval($bakestamp);
			}
			else
			{
				$this->lastBakeTime = false;
			}
		}
		return $this->lastBakeTime;
	}
	
	/**
	 * Creates a new instance of the PieCrustBaker.
	 */
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
		$pieCrust->setConfigValue('site', 'enable_cache', false);
		
		$this->dependencies = array('images', 'pictures', 'js', 'css', 'styles');
		
		$this->postInfos = array();
		$this->postTags = array();
		$this->postCategories = array();
		$this->tagsToBake = array();
		$this->categoriesToBake = array();
	}
	
	/**
	 * Bakes the website.
	 */
	public function bake(array $parameters = array())
	{
		$this->parameters = array_merge(array(
			'smart' => true
		), $parameters);
		
		echo "PieCrust Baker v." . PieCrust::VERSION . "\n\n";
		echo "  Baking:  " . $this->pieCrust->getRootDir() . "\n";
		echo "  Into:    " . $this->getBakeDir() . "\n";
		echo "  For URL: " . $this->pieCrust->getHost() . $this->pieCrust->getUrlBase() . "\n";
		echo "\n\n";
	
		echo "====== CLEANING CACHE ======\n\n";
		FileSystem::deleteDirectory($this->pieCrust->getCacheDir(), true);
		echo "\n\n";
		
		echo "====== BAKING: " . $this->pieCrust->getUrlBase() . " ======\n\n";
		
		$this->bakePages();
		$this->bakePosts();
		$this->bakeTags();
		$this->bakeCategories();
		
		$this->writeLastBakeTime(time());
	}
	
	protected function bakePages()
	{
		echo "Baking pages:\n";
		
		$pagesDir = $this->pieCrust->getPagesDir();
		$directory = new RecursiveDirectoryIterator($pagesDir);
		$iterator = new RecursiveIteratorIterator($directory);
		foreach ($iterator as $path)
		{
			$relativePath = str_replace('\\', '/', substr($path->getPathname(), strlen($pagesDir)));
			$relativePathInfo = pathinfo($relativePath);
			if ($relativePathInfo['filename'] == PIECRUST_CATEGORY_PAGE_NAME or
				$relativePathInfo['filename'] == PIECRUST_TAG_PAGE_NAME or
				$relativePathInfo['extension'] != 'html')
			{
				continue;
			}
			
			$uri = (($relativePathInfo['dirname'] == '.') ? '' : ($relativePathInfo['dirname'] . '/')) . $relativePathInfo['filename'];
			$uri = str_replace('_index', '', $uri);
			echo ' > ' . $relativePath;
			
			$page = Page::create(
				$this->pieCrust,
				$uri,
				$path->getPathname()
			);
			$this->bakePage($page);
			
			echo "\n";
		}
		
		echo "\n";
	}
	
	protected function bakePosts()
	{
		echo "Baking posts:\n";
		
		$fs = new FileSystem($this->pieCrust);
		$postsFs = $this->pieCrust->getConfigValue('site', 'posts_fs');
		switch ($postsFs)
		{
		case 'hierarchy':
			$postInfos = $fs->getHierarchicalPostFiles();
			break;
		case 'flat':
		default:
			$postInfos = $fs->getFlatPostFiles();
			break;
		}
		
		$postIndex = 0;
		$postsUrlFormat = $this->pieCrust->getConfigValue('site', 'posts_urls');
		foreach ($postInfos as $postInfo)
		{
			$uri = Paginator::buildPostUrl($postsUrlFormat, $postInfo);
			$page = Page::create(
				$this->pieCrust,
				$uri,
				$postInfo['path'],
				true
			);
			
			$pageWasBaked = false;
			if ($this->shouldRebakeFile($postInfo['path']))
			{
				echo ' > ' . $postInfo['name'];
				$this->bakePage($page);
				$pageWasBaked = true;
				echo "\n";
			}
			
			$this->postInfos[] = $postInfo;
			$tags = $page->getConfigValue('tags');
			if ($tags != null)
			{
				foreach ($tags as $tag)
				{
					if (!isset($this->postTags[$tag]))
					{
						$this->postTags[$tag] = array();
					}
					$this->postTags[$tag][] = $postIndex;
					
					if ($pageWasBaked) $this->tagsToBake[$tag] = true;
				}
			}
			$category = $page->getConfigValue('category');
			if ($category != null)
			{
				if (!isset($this->postCategories[$category]))
				{
					$this->postCategories[$category] = array();
				}
				$this->postCategories[$category][] = $postIndex;
				
				if ($pageWasBaked) $this->categoriesToBake[$category] = true;
			}
			$postIndex++;
		}
		
		echo "\n";
	}
	
	protected function bakeTags()
	{
		$tagPagePath = $this->pieCrust->getPagesDir() . PIECRUST_TAG_PAGE_NAME . '.html';
		if (!is_file($tagPagePath)) return;
		
		echo "Baking tags:\n";
		
		foreach (array_keys($this->tagsToBake) as $tag)
		{
			$postIndices = $this->postTags[$tag];
			
			$postInfos = array();
			foreach ($postIndices as $i)
			{
				$postInfos[] = $this->postInfos[$i];
			}
			
			echo ' > ' . $tag . ' (' . count($postInfos) . ' posts)';
			
			$uri = Paginator::buildTagUrl($this->pieCrust->getConfigValue('site', 'tags_urls'), $tag);
			$page = Page::create(
				$this->pieCrust,
				$uri,
				$tagPagePath
			);
			$this->bakePage($page, $postInfos, array('tag' => $tag));
			
			echo "\n";
		}
		
		echo "\n";
	}
	
	protected function bakeCategories()
	{
		$categoryPagePath = $this->pieCrust->getPagesDir() . PIECRUST_CATEGORY_PAGE_NAME . '.html';
		if (!is_file($categoryPagePath)) return;
		
		echo "Baking categories:\n";
		
		foreach (array_keys($this->categoriesToBake) as $category)
		{
			$postIndices = $this->postCategories[$category];
			
			$postInfos = array();
			foreach ($postIndices as $i)
			{
				$postInfos[] = $this->postInfos[$i];
			}
			
			echo ' > ' . $category . ' (' . count($postInfos) . ' posts)';
			
			$uri = Paginator::buildCategoryUrl($this->pieCrust->getConfigValue('site', 'categories_urls'), $category);
			$page = Page::create(
				$this->pieCrust, 
				$uri, 
				$categoryPagePath
			);
			$this->bakePage($page, $postInfos, array('category' => $category));
			
			echo "\n";
		}
		
		echo "\n";
	}
	
	protected function bakePage(Page $page, array $postInfos = null, array $extraData = null)
	{
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
			$bakePath = ($this->getBakeDir() . 
						 $page->getUri() . 
						 (($page->getUri() == '') ? '' : DIRECTORY_SEPARATOR) . 
						 (($page->getPageNumber() == 1) ? '' : ($page->getPageNumber() . DIRECTORY_SEPARATOR)) .
						 PIECRUST_BAKE_INDEX_DOCUMENT);
		}
		else
		{
			$extension = $this->getBakedExtension($page->getConfigValue('content_type'));
			$bakePath = $this->getBakeDir() . $page->getUri() . '.' . $extension;
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
	
	protected function shouldRebakeFile($path)
	{
		if ($this->parameters['smart'] and $this->getLastBakeTime() !== false)
		{
			if (filemtime($path) < $this->getLastBakeTime())
			{
				return false;
			}
		}
		return true;
	}

	protected function writeLastBakeTime($time)
	{
		$bakestampPath = $this->getBakeDir() . PIECRUST_BAKE_TIMESTAMP;
		file_put_contents($bakestampPath, $time);
	}
}
