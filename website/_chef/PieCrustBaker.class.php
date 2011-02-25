<?php

define('PIECRUST_BAKE_INDEX_DOCUMENT', 'index.html');

require_once 'PieCrust.class.php';
require_once 'Paginator.class.php';


/**
 * A class that 'bakes' a PieCrust website into a bunch of static HTML files.
 */
class PieCrustBaker
{
	protected $pieCrust;
	
	protected $postInfos;
	protected $postTags;
	protected $postCategories;
	
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
			$this->ensureDirectory($defaultBakeDir);
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
	}
	
	/**
	 * Bakes the website.
	 */
	public function bake()
	{
		echo "====== BAKING: " . $this->pieCrust->getUrlBase() . " ======\n\n";
		echo " Into: " . $this->getBakeDir() . "\n\n";
		
		$this->bakePages();
		$this->bakePosts();
		$this->bakeTags();
		$this->bakeCategories();
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
				$relativePathInfo['filename'] == PIECRUST_TAG_PAGE_NAME)
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
			echo ' > ' . $postInfo['name'];
			
			$page = Page::create(
				$this->pieCrust,
				$uri,
				$postInfo['path'],
				true
			);
			$this->bakePage($page);
			
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
			}
			$postIndex++;
			echo "\n";
		}
		
		echo "\n";
	}
	
	protected function bakeTags()
	{
		$tagPagePath = $this->pieCrust->getPagesDir() . PIECRUST_TAG_PAGE_NAME . '.html';
		if (!is_file($tagPagePath)) return;
		
		echo "Baking tags:\n";
		
		foreach ($this->postTags as $tag => $postIndices)
		{
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
		
		foreach ($this->postCategories as $category => $postIndices)
		{
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
			}
		}
	}
	
	protected function bakeSinglePage(Page $page, PageRenderer $pageRenderer, array $postInfos = null, array $extraData = null)
	{
		// Merge optional extra data into the page.
		if ($postInfos != null or $extraData != null)
		{
			$mergeData = array();
			if ($postInfos != null)
			{
				$paginator = new Paginator($this->pieCrust, $page);
				$paginator->buildPaginationData($postInfos);
				$mergeData['pagination'] = $paginator;
			}
			if ($extraData != null)
			{
				$mergeData = array_merge($mergeData, $extraData);
			}
			$page->setExtraPageData($mergeData);
		}
		
		// Render the page.
		$bakedContents = $pageRenderer->get($page, null, false);
		
		// Bake the page into the correct HTML file, and figure out
		// if there are more pages to bake for this page.
		$useDirectory = $page->getConfigValue('pretty_urls');
		if ($useDirectory == null)
		{
			$useDirectory = ($this->pieCrust->getConfigValue('site', 'pretty_urls') === true);
		}
		$pageData = $page->getPageData();
		$paginator = $pageData['pagination'];
		if ($paginator->wasPaginationDataAccessed())
		{
			// If pagination data was accessed, there may be sub-pages for this page,
			// so we need the 'directory' naming scheme to store them.
			$useDirectory = true;
		}
		
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
		
		$this->ensureDirectory(dirname($bakePath));
		file_put_contents($bakePath, $bakedContents);
	
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
	
	protected function ensureDirectory($dir)
	{
		if (!is_dir($dir))
		{
			mkdir($dir, 0777, true);
		}
	}
	
	protected function deleteDirectory($dir, $level = 0)
	{
		$files = new FilesystemIterator($dir);
		foreach ($files as $file)
		{
			if ($file->getFilename() == '.empty' or $file->getFilename() == 'empty' or $file->getFilename() == 'empty.txt')
			{
				continue;
			}
			
			if($file->isDir())
			{
				$this->deleteDirectory($file->getPathname(), $level + 1);
			}
			else
			{
				unlink($file);
			}
		}
		
		if ($level > 0 and is_dir($dir))
		{
			rmdir($dir);
		}
	}
}
