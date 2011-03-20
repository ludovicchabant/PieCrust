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
    protected $bakeRecord;
    
    protected $parameters;
    /**
     * Gets the baking parameters.
     */
    public function getParameters()
    {
        return $this->parameters;
    }
    
    /**
     * Get a baking parameter's value.
     */
    public function getParameterValue($key)
    {
        return $this->parameters[$key];
    }
    
    /**
     * Sets a baking parameter's value.
     */
    public function setParameterValue($key, $value)
    {
        $this->parameters[$key] = $value;
    }
    
    protected $bakeDir;
    /**
     * Gets the bake (output) directory.
     */
    public function getBakeDir()
    {
        if ($this->bakeDir === null)
        {
            $defaultBakeDir = $this->pieCrust->getRootDir();
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
			try
            {
                if (!is_dir($this->bakeDir))
                {
                    mkdir($dir, 0777, true);
                }
                else
                {
                    chmod($this->bakeDir, 0777);
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException('The bake directory must exist and be writable, and we can\'t create it or change the permissions ourselves: ' . $this->bakeDir);
            }
        }
    }
    
    /**
     * Creates a new instance of the PieCrustBaker.
     */
    public function __construct(PieCrust $pieCrust, array $parameters = array())
    {
        $this->pieCrust = $pieCrust;
        $this->pieCrust->setConfigValue('baker', 'is_baking', true);
        
        $this->parameters = array_merge(array(
            'smart' => true,
            'copy_assets' => false,
			'copy_misc' => false
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
        echo "  For URL: " . $this->pieCrust->getUrlBase() . "\n";
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
    
    protected function bakePages()
    {
		if ($this->bakeRecord == null) throw new PieCrustException("Can't bake pages without a bake-record active.");
		
        echo "Baking pages:\n";
        
        $hasBaked = false;
        $pagesDir = $this->pieCrust->getPagesDir();
        $directory = new RecursiveDirectoryIterator($pagesDir);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $path)
        {
			if ($iterator->isDot()) continue;
            $hasBaked |= $this->bakePage($path->getPathname());
        }
        if (!$hasBaked)
        {
            echo "   (nothing to bake)\n";
        }
        
        echo PHP_EOL;
    }
	
	protected function bakePage($path)
    {
        $path = realpath($path);
        $pagesDir = $this->pieCrust->getPagesDir();
        $relativePath = str_replace('\\', '/', substr($path, strlen($pagesDir)));
        $relativePathInfo = pathinfo($relativePath);
        if ($relativePathInfo['filename'] == PIECRUST_CATEGORY_PAGE_NAME or
            $relativePathInfo['filename'] == PIECRUST_TAG_PAGE_NAME or
            $relativePathInfo['extension'] != 'html')
        {
            return false;
        }

		// Don't bake this file if it is up-to-date and is not using any posts (if any was rebaked).
		if (!$this->shouldRebakeFile($path) and 
				(!$this->bakeRecord->wasAnyPostBaked() or 
				 !$this->bakeRecord->isPageUsingPosts($relativePath))
		   )
		{
			return false;
		}
        
		echo ' > ' . $relativePath;
        $uri = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
        $uri = str_replace('_index', '', $uri);
        $page = Page::create(
                $this->pieCrust,
                $uri,
                $path
            );
        $baker = new PageBaker($this->pieCrust, $this->getBakeDir(), $this->getPageBakerParameters());
        $baker->bake($page);
		if ($baker->wasPaginationDataAccessed())
		{
			$this->bakeRecord->addPageUsingPosts($relativePath);
		}
        echo PHP_EOL;
        
        return true;
    }
    
    protected function bakePosts()
    {
		if (!$this->hasPosts()) return;
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
                $baker = new PageBaker($this->pieCrust, $this->getBakeDir(), $this->getPageBakerParameters());
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
		if (!$this->hasPosts()) return;
		
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
            $baker = new PageBaker($this->pieCrust, $this->getBakeDir(), $this->getPageBakerParameters());
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
		if (!$this->hasPosts()) return;
		
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
            $baker = new PageBaker($this->pieCrust, $this->getBakeDir(), $this->getPageBakerParameters());
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
	
	protected function hasPosts()
	{
		try
		{
			$dir = $this->pieCrust->getPostsDir();
			return true;
		}
		catch (Exception $e)
		{
			return false;
		}
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
    
    protected function getPageBakerParameters()
    {
        return array('copy_assets' => $this->parameters['copy_assets']);
    }
}
