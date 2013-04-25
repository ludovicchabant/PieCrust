<?php

namespace PieCrust\Baker;

use \Exception;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustCacheInfo;
use PieCrust\PieCrustException;
use PieCrust\Util\UriBuilder;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;


/**
 * A class that 'bakes' a PieCrust website into a bunch of static HTML files.
 */
class PieCrustBaker
{
    /**
     * Default directories and files.
     */
    const DEFAULT_BAKE_DIR = '_counter';
    const BAKE_INFO_FILE = 'bakeinfo.json';

    protected $bakeRecord;
    protected $logger;
    
    protected $pieCrust;
    /**
     * Get the app hosted in the baker.
     */
    public function getApp()
    {
        return $this->pieCrust;
    }
    
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
            $defaultBakeDir = $this->pieCrust->getRootDir() . self::DEFAULT_BAKE_DIR;
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
                    if (@mkdir($this->bakeDir, 0777, true) === false)
                        throw new PieCrustException("Can't create bake directory: " . $this->bakeDir);
                }
                else
                {
                    if (@chmod($this->bakeDir, 0777) === false)
                        throw new PieCrustException("Can't make bake directory writeable: " . $this->bakeDir);
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException('The bake directory must exist and be writable, and we can\'t create it or change the permissions ourselves: ' . $this->bakeDir, 0, $e);
            }
        }
    }
    
    /**
     * Creates a new instance of the PieCrustBaker.
     */
    public function __construct(IPieCrust $pieCrust, array $bakerParameters = array(), $logger = null)
    {
        $this->pieCrust = $pieCrust;
        $this->pieCrust->getConfig()->setValue('baker/is_baking', false);
        
        $bakerParametersFromApp = $this->pieCrust->getConfig()->getValue('baker');
        if ($bakerParametersFromApp == null)
            $bakerParametersFromApp = array();
        $this->parameters = array_merge(array(
                'smart' => true,
                'clean_cache' => false,
                'config_variant' => null,
                'copy_assets' => true,
                'processors' => '*',
                'mounts' => array(),
                'skip_patterns' => array(),
                'force_patterns' => array(),
                'tag_combinations' => array()
            ),
            $bakerParametersFromApp,
            $bakerParameters
        );

        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
        
        // Validate and explode the tag combinations.
        $combinations = $this->parameters['tag_combinations'];
        if ($combinations)
        {
            if (!is_array($combinations))
                $combinations = array($combinations);
            $combinationsExploded = array();
            foreach ($combinations as $comb)
            {
                $combExploded = explode('/', $comb);
                if (count($combExploded) > 1)
                    $combinationsExploded[] = $combExploded;
            }
            $this->parameters['tag_combinations'] = $combinationsExploded;
        }
        
        // Apply the specified configuration variant, if any. Otherwise,
        // use the default variant if it exists.
        $isDefault = false;
        $variantName = $this->parameters['config_variant'];
        if (!$variantName)
        {
            $isDefault = true;
            $variantName = 'default';
        }
        $this->pieCrust->getConfig()->applyVariant(
            "baker/config_variants/{$variantName}",
            !$isDefault
        );
    }
    
    /**
     * Bakes the website.
     */
    public function bake()
    {
        $overallStart = microtime(true);
        
        // Display debug information.
        $this->logger->debug("  Bake Output: " . $this->getBakeDir());
        $this->logger->debug("  Root URL: " . $this->pieCrust->getConfig()->getValue('site/root'));
        
        // Setup the PieCrust environment.
        if ($this->parameters['copy_assets'])
            $this->pieCrust->getEnvironment()->getPageRepository()->setAssetUrlBaseRemap('%site_root%%uri%');
        $this->pieCrust->getConfig()->setValue('baker/is_baking', true);
        
        // Create the bake record.
        $blogKeys = $this->pieCrust->getConfig()->getValueUnchecked('site/blogs');
        $bakeInfoPath = false;
        if ($this->pieCrust->isCachingEnabled())
            $bakeInfoPath = $this->pieCrust->getCacheDir() . self::BAKE_INFO_FILE;
        $this->bakeRecord = new BakeRecord($blogKeys, $bakeInfoPath);

        // Create the execution context.
        $executionContext = $this->pieCrust->getEnvironment()->getExecutionContext(true);
        
        // Get the cache validity information.
        $cacheInfo = new PieCrustCacheInfo($this->pieCrust);
        $cacheValidity = $cacheInfo->getValidity(false);
        $executionContext->isCacheValid = $cacheValidity['is_valid'];
        
        // Figure out if we need to clean the cache.
        $this->parameters['__smart_content'] = $this->parameters['smart'];
        if ($this->pieCrust->isCachingEnabled())
        {
            if ($this->cleanCacheIfNeeded($cacheValidity))
            {
                $executionContext->wasCacheCleaned = true;
                $this->parameters['__smart_content'] = false;
            }
        }

        // Bake!
        $this->bakePosts();
        $this->bakePages();
        $this->bakeRecord->collectTagCombinations($this->pieCrust->getEnvironment()->getLinkCollector());
        $this->bakeTags();
        $this->bakeCategories();
    
        $dirBaker = new DirectoryBaker($this->pieCrust,
            $this->getBakeDir(),
            array(
                'smart' => $this->parameters['smart'],
                'mounts' => $this->parameters['mounts'],
                'processors' => $this->parameters['processors'],
                'skip_patterns' => $this->parameters['skip_patterns'],
                'force_patterns' => $this->parameters['force_patterns']
            ),
            $this->logger
        );
        $dirBaker->bake();
        
        // Save the bake record and clean up.
        if ($bakeInfoPath)
            $this->bakeRecord->saveBakeInfo($bakeInfoPath);
        $this->bakeRecord = null;
        
        $this->pieCrust->getConfig()->setValue('baker/is_baking', false);
        
        $this->logger->info('-------------------------');
        $this->logger->notice(self::formatTimed($overallStart, 'done baking'));
    }

    protected function cleanCacheIfNeeded(array $cacheValidity)
    {
        $cleanCache = $this->parameters['clean_cache'];
        $cleanCacheReason = "ordered to";
        if (!$cleanCache)
        {
            if (!$cacheValidity['is_valid'])
            {
                $cleanCache = true;
                $cleanCacheReason = "not valid anymore";
            }
        }
        if (!$cleanCache)
        {
            if ($this->bakeRecord->shouldDoFullBake())
            {
                $cleanCache = true;
                $cleanCacheReason = "need bake info regen";
            }
        }
        // If any template file changed since last time, we also need to re-bake everything
        // (there's no way to know what weird conditional template inheritance/inclusion
        //  could be in use...).
        if (!$cleanCache)
        {
            $maxMTime = 0;
            foreach ($this->pieCrust->getTemplatesDirs() as $dir)
            {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir), 
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $path)
                {
                    if ($path->isFile())
                    {
                        $maxMTime = max($maxMTime, $path->getMTime());
                    }
                }
            }
            if ($maxMTime >= $this->bakeRecord->getLast('time'))
            {
                $cleanCache = true;
                $cleanCacheReason = "templates modified";
            }
        }
        if ($cleanCache)
        {
            $start = microtime(true);
            PathHelper::deleteDirectoryContents($this->pieCrust->getCacheDir());
            file_put_contents($cacheValidity['path'], $cacheValidity['hash']);
            $this->logger->info(self::formatTimed($start, 'cleaned cache (reason: ' . $cleanCacheReason . ')'));
        }
        return $cleanCache;
    }
    
    protected function bakePages()
    {
        if ($this->bakeRecord == null)
            throw new PieCrustException("Can't bake pages without a bake-record active.");

        $pages = PageHelper::getPages($this->pieCrust);
        foreach ($pages as $page)
        {
            $this->bakePage($page);
        }
    }
    
    protected function bakePage(IPage $page)
    {
        $start = microtime(true);
        $baker = new PageBaker(
            $this->getBakeDir(), 
            $this->getPageBakerParameters(), 
            $this->logger
        );
        $didBake = $baker->bake($page);
        if (!$didBake)
            return;

        if ($baker->wasPaginationDataAccessed())
        {
            $relativePath = PageHelper::getRelativePath($page);
            $this->bakeRecord->addPageUsingPosts($relativePath);
        }
        
        $pageCount = $baker->getPageCount();
        $this->logger->info(self::formatTimed($start, ($page->getUri() == '' ? '[main page]' : $page->getUri()) . (($pageCount > 1) ? " [{$pageCount}]" : "")));
        return true;
    }
    
    protected function bakePosts()
    {
        if ($this->bakeRecord == null)
            throw new PieCrustException("Can't bake posts without a bake-record active.");

        $blogKeys = $this->pieCrust->getConfig()->getValue('site/blogs');
        foreach ($blogKeys as $blogKey)
        {
            $posts = PageHelper::getPosts($this->pieCrust, $blogKey);
            foreach ($posts as $post)
            {
                $this->bakePost($post);
            }
        }
    }

    protected function bakePost(IPage $post)
    {
        $start = microtime(true);
        $baker = new PageBaker(
            $this->getBakeDir(), 
            $this->getPageBakerParameters(),
            $this->logger
        );
        $didBake = $baker->bake($post);
        if ($didBake)
            $this->logger->info(self::formatTimed($start, $post->getUri()));

        $postInfo = array();
        $postInfo['blogKey'] = $post->getBlogKey();
        $postInfo['tags'] = $post->getConfig()->getValue('tags');
        $postInfo['category'] = $post->getConfig()->getValue('category');
        $postInfo['wasBaked'] = $didBake;
        $this->bakeRecord->addPostInfo($postInfo);
    }
    
    protected function bakeTags()
    {
        if ($this->bakeRecord == null)
            throw new PieCrustException("Can't bake tags without a bake-record active.");
        
        $blogKeys = $this->pieCrust->getConfig()->getValueUnchecked('site/blogs');
        $slugifyFlags = $this->pieCrust->getConfig()->getValue('site/slugify_flags');
        foreach ($blogKeys as $blogKey)
        {
            // Check that there is a tag listing page to bake.
            $prefix = '';
            if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
                $prefix = $blogKey . DIRECTORY_SEPARATOR;

            $tagPageName = $prefix . PieCrustDefaults::TAG_PAGE_NAME . '.html';
            $themeOrResTagPageName = PieCrustDefaults::TAG_PAGE_NAME . '.html';
            $tagPagePath = PathHelper::getUserOrThemeOrResPath($this->pieCrust, $tagPageName, $themeOrResTagPageName);
            if ($tagPagePath === false)
                continue;
            
            // Get single and multi tags to bake.
            $tagsToBake = $this->bakeRecord->getTagsToBake($blogKey);
            // Figure out tag combinations to bake. Start with any specified
            // in the config.
            $combinations = $this->parameters['tag_combinations'];
            if (array_key_exists($blogKey, $combinations))
            {
                $combinations = $combinations[$blogKey];
            }
            elseif (count($blogKeys > 1))
            {
                $combinations = array();
            }
            
            // Look at the known combinations from what was collected in the
            // site's pages.
            $lastKnownCombinations = $this->bakeRecord->getLast('knownTagCombinations');
            if (array_key_exists($blogKey, $lastKnownCombinations))
            {
                $combinations = array_unique(
                    array_merge($combinations, $lastKnownCombinations[$blogKey])
                );
            }
            if (count($combinations) > 0)
            {
                // Filter combinations that contain tags that got invalidated.
                $combinationsToBake = array();
                foreach ($combinations as $comb)
                {
                    if (count(array_intersect($comb, $tagsToBake)) > 0)
                        $combinationsToBake[] = $comb;
                }
                $tagsToBake = array_merge($combinationsToBake, $tagsToBake);
            }

            // Order tags so it looks nice when we bake.
            usort($tagsToBake, function ($t1, $t2) {
                if (is_array($t1))
                    $t1 = implode('+', $t1);
                if (is_array($t2))
                    $t2 = implode('+', $t2);
                return strcmp($t1, $t2);
            });
            
            // Bake!
            $pageRepository = $this->pieCrust->getEnvironment()->getPageRepository();
            foreach ($tagsToBake as $tag)
            {
                $start = microtime(true);
                $postInfos = $this->bakeRecord->getPostsTagged($blogKey, $tag);
                if (count($postInfos) > 0)
                {
                    if (is_array($tag))
                    {
                        $slugifiedTag = array_map(
                            function($t) use ($slugifyFlags) {
                                return UriBuilder::slugify($t, $slugifyFlags);
                            },
                            $tag
                        );
                        $formattedTag = implode('+', array_map('rawurldecode', $tag));
                    }
                    else
                    {
                        $slugifiedTag = UriBuilder::slugify($tag, $slugifyFlags);
                        $formattedTag = rawurldecode($tag);
                    }

                    $uri = UriBuilder::buildTagUri($this->pieCrust, $blogKey, $slugifiedTag, false);
                    $page = $pageRepository->getOrCreatePage(
                        $uri,
                        $tagPagePath,
                        IPage::TYPE_TAG,
                        $blogKey
                    );
                    $page->setPageKey($slugifiedTag);
                    $baker = new PageBaker(
                        $this->getBakeDir(), 
                        $this->getPageBakerParameters(),
                        $this->logger
                    );
                    $baker->bake($page);

                    $pageCount = $baker->getPageCount();
                    $this->logger->info(self::formatTimed($start, 'tag:' . $formattedTag . (($pageCount > 1) ? " [{$pageCount}]" : "")));
                }
            }
        }
    }
    
    protected function bakeCategories()
    {
        if ($this->bakeRecord == null)
            throw new PieCrustException("Can't bake categories without a bake-record active.");
        
        $blogKeys = $this->pieCrust->getConfig()->getValueUnchecked('site/blogs');
        $slugifyFlags = $this->pieCrust->getConfig()->getValue('site/slugify_flags');
        foreach ($blogKeys as $blogKey)
        {
            // Check that there is a category listing page to bake.
            $prefix = '';
            if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
                $prefix = $blogKey . DIRECTORY_SEPARATOR;

            $categoryPageName = $prefix . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html';
            $themeOrResCategoryPageName = PieCrustDefaults::CATEGORY_PAGE_NAME . '.html';
            $categoryPagePath = PathHelper::getUserOrThemeOrResPath($this->pieCrust, $categoryPageName, $themeOrResCategoryPageName);
            if ($categoryPagePath === false)
                continue;

            // Order categories so it looks nicer when we bake.
            $categoriesToBake = $this->bakeRecord->getCategoriesToBake($blogKey);
            sort($categoriesToBake);

            // Bake!
            $pageRepository = $this->pieCrust->getEnvironment()->getPageRepository();
            foreach ($categoriesToBake as $category)
            {
                $start = microtime(true);
                $postInfos = $this->bakeRecord->getPostsInCategory($blogKey, $category);
                if (count($postInfos) > 0)
                {
                    $slugifiedCategory = UriBuilder::slugify($category, $slugifyFlags);
                    $formattedCategory = rawurldecode($slugifiedCategory);
                    
                    $uri = UriBuilder::buildCategoryUri($this->pieCrust, $blogKey, $slugifiedCategory, false);
                    $page = $pageRepository->getOrCreatePage(
                        $uri, 
                        $categoryPagePath,
                        IPage::TYPE_CATEGORY,
                        $blogKey
                    );
                    $page->setPageKey($slugifiedCategory);
                    $baker = new PageBaker(
                        $this->getBakeDir(),
                        $this->getPageBakerParameters(),
                        $this->logger
                    );
                    $baker->bake($page);

                    $pageCount = $baker->getPageCount();
                    $this->logger->info(self::formatTimed($start, 'category:' . $formattedCategory . (($pageCount > 1) ? " [{$pageCount}]" : "")));
                }
            }
        }
    }

    protected function hasPages()
    { 
        return ($this->pieCrust->getPagesDir() !== false);
    }

    protected function hasPosts()
    {
        return ($this->pieCrust->getPostsDir() !== false);
    }
    
    protected function getPageBakerParameters()
    {
        return array(
            'smart' => $this->parameters['__smart_content'],
            'copy_assets' => $this->parameters['copy_assets'],
            'bake_record' => $this->bakeRecord
        );
    }
    
    public static function formatTimed($startTime, $message)
    {
        static $color = null;
        if ($color === null)
        {
            if (PieCrustDefaults::IS_WINDOWS())
                $color = false;
            else
                $color = new \Console_Color2();
        }

        $endTime = microtime(true);
        $endTimeStr = sprintf('%8.1f ms', ($endTime - $startTime)*1000.0);
        if ($color)
        {
            $endTimeStr = $color->escape($endTimeStr);
            $message = $color->escape($message);
            return $color->convert("[%g{$endTimeStr}%n] {$message}");
        }
        else
        {
            return "[{$endTimeStr}] {$message}";
        }
    }
}
