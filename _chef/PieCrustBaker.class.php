<?php

define('PIECRUST_BAKE_INDEX_DOCUMENT', 'index.html');
define('PIECRUST_BAKE_INFO_FILE', 'bakeinfo.json');

// When baking, we copy the assets to the page's output directory so we don't need
// a suffix in the URL.
define ('PIECRUST_ASSET_URL_SUFFIX', '');

require_once 'PieCrust.class.php';
require_once 'Paginator.class.php';
require_once 'FileSystem.class.php';
require_once 'BakeRecord.class.php';
require_once 'PageBaker.class.php';


/**
 * A class that 'bakes' a PieCrust website into a bunch of static HTML files.
 */
class PieCrustBaker
{
	protected $pieCrust;
	
	protected $parameters;
	protected $bakeRecord;
	
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
	
	/**
	 * Creates a new instance of the PieCrustBaker.
	 */
	public function __construct(PieCrust $pieCrust, array $parameters = array())
	{
		$this->pieCrust = $pieCrust;
		$this->parameters = array_merge(array(
			'smart' => true
		), $parameters);
		
		$this->dependencies = array('images', 'pictures', 'js', 'css', 'styles');
	}
	
	/**
	 * Bakes the website.
	 */
	public function bake()
	{
		echo "PieCrust Baker v." . PieCrust::VERSION . "\n\n";
		echo "  Baking:  " . $this->pieCrust->getRootDir() . "\n";
		echo "  Into:    " . $this->getBakeDir() . "\n";
		echo "  For URL: " . $this->pieCrust->getHost() . $this->pieCrust->getUrlBase() . "\n";
		echo "\n\n";
	
		echo "====== CLEANING CACHE ======\n\n";
		FileSystem::deleteDirectory($this->pieCrust->getCacheDir(), true);
		echo "\n\n";
		
		echo "====== BAKING: " . $this->pieCrust->getUrlBase() . " ======\n\n";
		
		$bakeInfoPath = $this->getBakeDir() . PIECRUST_BAKE_INFO_FILE;
		$this->bakeRecord = new BakeRecord($bakeInfoPath);
		
		$this->bakePosts();
		$this->bakePages();
		$this->bakeTags();
		$this->bakeCategories();
		
		$this->bakeRecord->saveBakeInfo($bakeInfoPath);
		unset($this->bakeRecord);
		$this->bakeRecord = null;
	}
	
	/**
	 * Bake one specific page only.
	 */
	public function bakePage($path, $smart = false)
	{
		$path = realpath($path);
		if (!is_file($path))
		{
			throw new PieCrustException("The given page path does not exist.");
		}
		if ($this->bakeRecord == null and $smart)
		{
			throw new PieCrustException("Can't bake a page in 'smart' mode without a bake-record active.");
		}
		
		$pagesDir = $this->pieCrust->getPagesDir();
		$relativePath = str_replace('\\', '/', substr($path, strlen($pagesDir)));
		$relativePathInfo = pathinfo($relativePath);
		if ($relativePathInfo['filename'] == PIECRUST_CATEGORY_PAGE_NAME or
			$relativePathInfo['filename'] == PIECRUST_TAG_PAGE_NAME or
			$relativePathInfo['extension'] != 'html')
		{
			return false;
		}
		if ($smart)
		{
			// Don't bake this file if it is up-to-date and is not using any posts (if any was rebaked).
			if (!$this->shouldRebakeFile($path) and 
					(!$this->bakeRecord->wasAnyPostBaked() or 
					 !$this->bakeRecord->isPageUsingPosts($relativePath))
			   )
			{
				return false;
			}
		}
		
		$uri = (($relativePathInfo['dirname'] == '.') ? '' : ($relativePathInfo['dirname'] . '/')) . $relativePathInfo['filename'];
		$uri = str_replace('_index', '', $uri);
		
		echo ' > ' . $relativePath;
		$page = Page::create(
				$this->pieCrust,
				$uri,
				$path
			);
		$baker = new PageBaker($this->pieCrust, $this->getBakeDir());
		$baker->bake($page);
		
		if ($smart)
		{
			if ($baker->wasPaginationDataAccessed())
			{
				$this->bakeRecord->addPageUsingPosts($relativePath);
			}
		}
		
		echo PHP_EOL;
		
		return true;
	}
	
	protected function bakePages()
	{
		echo "Baking pages:\n";
		
		$hasBaked = false;
		$pagesDir = $this->pieCrust->getPagesDir();
		$directory = new RecursiveDirectoryIterator($pagesDir);
		$iterator = new RecursiveIteratorIterator($directory);
		foreach ($iterator as $path)
		{
			$hasBaked |= $this->bakePage($path->getPathname(), true);
		}
		if (!$hasBaked)
		{
			echo "   (nothing to bake)\n";
		}
		
		echo PHP_EOL;
	}
	
	protected function bakePosts()
	{
		if ($this->bakeRecord == null) throw new PieCrustException("Can't bake posts without a bake-record active.");
		
		echo "Baking posts:\n";
		
		$fs = new FileSystem($this->pieCrust);
		$postInfos = $fs->getPostFiles();
		
		$hasBaked = false;
		$postUrlFormat = $this->pieCrust->getConfigValue('site', 'post_url');
		foreach ($postInfos as $postInfo)
		{
			$uri = Paginator::buildPostUrl($postUrlFormat, $postInfo);
			$page = Page::create(
				$this->pieCrust,
				$uri,
				$postInfo['path'],
				PIECRUST_PAGE_POST
			);
			$page->setDate($postInfo);
			
			$pageWasBaked = false;
			if ($this->shouldRebakeFile($postInfo['path']))
			{
				echo ' > ' . $postInfo['name'];
				$baker = new PageBaker($this->pieCrust, $this->getBakeDir());
				$baker->bake($page);
				$pageWasBaked = true;
				$hasBaked = true;
				echo PHP_EOL;
			}
			
			$postInfo['tags'] = $page->getConfigValue('tags');
			$postInfo['category'] = $page->getConfigValue('category');
			$this->bakeRecord->addPostInfo($postInfo, $pageWasBaked);
		}
		if (!$hasBaked)
		{
			echo "   (nothing to bake)\n";
		}
		
		echo PHP_EOL;
	}
	
	protected function bakeTags()
	{
		$tagPagePath = $this->pieCrust->getPagesDir() . PIECRUST_TAG_PAGE_NAME . '.html';
		if (!is_file($tagPagePath)) return;
		if ($this->bakeRecord == null) throw new PieCrustException("Can't bake tags without a bake-record active.");
		
		echo "Baking tags:\n";
		
		$hasBaked = false;
		foreach ($this->bakeRecord->getTagsToBake() as $tag)
		{
			$postInfos = $this->bakeRecord->getPostsTagged($tag);
			echo ' > ' . $tag . ' (' . count($postInfos) . ' posts)';
			
			$uri = Paginator::buildTagUrl($this->pieCrust->getConfigValue('site', 'tag_url'), $tag);
			$page = Page::create(
				$this->pieCrust,
				$uri,
				$tagPagePath,
				PIECRUST_PAGE_TAG,
				1,
				$tag
			);
			$baker = new PageBaker($this->pieCrust, $this->getBakeDir());
			$baker->bake($page, $postInfos);
			$hasBaked = true;
			
			echo PHP_EOL;
		}
		if (!$hasBaked)
		{
			echo "   (nothing to bake)\n";
		}
		
		echo PHP_EOL;
	}
	
	protected function bakeCategories()
	{
		$categoryPagePath = $this->pieCrust->getPagesDir() . PIECRUST_CATEGORY_PAGE_NAME . '.html';
		if (!is_file($categoryPagePath)) return;
		if ($this->bakeRecord == null) throw new PieCrustException("Can't bake categories without a bake-record active.");
		
		echo "Baking categories:\n";
		
		$hasBaked = false;
		foreach ($this->bakeRecord->getCategoriesToBake() as $category)
		{
			$postInfos = $this->getPostsInCategory($category);
			echo ' > ' . $category . ' (' . count($postInfos) . ' posts)';
			
			$uri = Paginator::buildCategoryUrl($this->pieCrust->getConfigValue('site', 'category_url'), $category);
			$page = Page::create(
				$this->pieCrust, 
				$uri, 
				$categoryPagePath,
				PIECRUST_PAGE_CATEGORY,
				1,
				$category
			);
			$baker = new PageBaker($this->pieCrust, $this->getBakeDir());
			$baker->bake($page, $postInfos);
			$hasBaked = true;
			
			echo PHP_EOL;
		}
		if (!$hasBaked)
		{
			echo "   (nothing to bake)\n";
		}
		
		echo PHP_EOL;
	}
	
	protected function shouldRebakeFile($path)
	{
		if ($this->parameters['smart'] and $this->bakeRecord->getLastBakeTime() !== false)
		{
			if (filemtime($path) < $this->bakeRecord->getLastBakeTime())
			{
				return false;
			}
		}
		return true;
	}
}
