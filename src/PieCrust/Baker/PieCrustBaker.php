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
class PieCrustBaker implements IBaker
{
    /**
     * Default directories and files.
     */
    const DEFAULT_BAKE_DIR = '_counter';

    const BAKE_RECORD_PATH = 'bake_r/';

    protected $bakeRecord;
    protected $logger;
    protected $assistants;
    
    protected $pieCrust;
    /**
     * Get the app hosted in the baker.
     */
    public function getApp()
    {
        return $this->pieCrust;
    }

    protected $pageBaker;
    /**
     * Gets the page baker.
     */
    public function getPageBaker()
    {
        if ($this->pageBaker == null)
        {
            $parameters = array(
                'smart' => $this->parameters['__smart_content'],
                'copy_assets' => $this->parameters['copy_assets']
            );
            $this->pageBaker = new PageBaker(
                $this->pieCrust,
                $this->getBakeDir(),
                $this->bakeRecord->getCurrent(),
                $parameters
            );
        }
        return $this->pageBaker;
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
        try
        {
            PathHelper::ensureDirectory($this->bakeDir, true);
        }
        catch (Exception $e)
        {
            throw new PieCrustException('The bake directory must exist and be writable, and we can\'t create it or change the permissions ourselves: ' . $this->bakeDir, 0, $e);
        }
    }
    
    /**
     * Creates a new instance of the PieCrustBaker.
     */
    public function __construct(IPieCrust $pieCrust, array $bakerParameters = array())
    {
        $this->pieCrust = $pieCrust;
        $this->pieCrust->getConfig()->setValue('baker/is_baking', false);
        
        $bakerParametersFromApp = $this->pieCrust->getConfig()->getValue('baker');
        if ($bakerParametersFromApp == null)
            $bakerParametersFromApp = array();
        $this->parameters = array_merge(array(
                'smart' => true,
                'clean_cache' => false,
                'show_record' => false,
                'config_variant' => null,
                'copy_assets' => true,
                'processors' => '*',
                'mounts' => array(),
                'skip_patterns' => array(),
                'force_patterns' => array()
            ),
            $bakerParametersFromApp,
            $bakerParameters
        );

        $this->pageBaker = null;
        $this->logger = $pieCrust->getEnvironment()->getLog();
        
        // New way: apply the `baker` variant.
        // Old way: apply the specified variant, or the default one. Warn about deprecation.
        $variantName = $this->parameters['config_variant'];
        if ($variantName)
        {
            $this->logger->warning("The `--config` parameter has been moved to a global parameter (specified before the command).");
            $this->pieCrust->getConfig()->applyVariant("baker/config_variants/{$variantName}");
            $this->logger->warning("Variant '{$variantName}' has been applied, but will need to be moved to the new `variants` section of the site configuration.");
        }
        else
        {
            if ($this->pieCrust->getConfig()->hasValue("baker/config_variants/default"))
            {
                $this->pieCrust->getConfig()->applyVariant("baker/config_variants/default");
                $this->logger->warning("The default baker configuration variant has been applied, but will need to be moved into the new `variants/baker` section of the site configuration.");
            }
            else
            {
                $this->pieCrust->getConfig()->applyVariant("variants/baker", false);
            }
        }

        // Load the baking assistants.
        $this->cacheAssistants();
    }
    
    /**
     * Bakes the website.
     */
    public function bake()
    {
        $overallStart = microtime(true);

        // Pre-bake notification.
        $this->callAssistants('onBakeStart', array($this));
        
        // Display debug information.
        $this->logger->debug("  Bake Output: " . $this->getBakeDir());
        $this->logger->debug("  Root URL: " . $this->pieCrust->getConfig()->getValue('site/root'));
        
        // Setup the PieCrust environment.
        if ($this->parameters['copy_assets'])
            $this->pieCrust->getEnvironment()->getPageRepository()->setAssetUrlBaseRemap('%site_root%%uri%');
        $this->pieCrust->getConfig()->setValue('baker/is_baking', true);
        
        // Create the bake record.
        $bakeRecordPath = false;
        $this->bakeRecord = new TransitionalBakeRecord($this->pieCrust);
        if ($this->pieCrust->isCachingEnabled())
        {
            $start = microtime(true);
            $bakeRecordPath = $this->pieCrust->getCacheDir() .
                self::BAKE_RECORD_PATH .
                md5($this->getBakeDir()) . DIRECTORY_SEPARATOR .
                'record.json';
            $this->bakeRecord->loadPrevious($bakeRecordPath);
            $this->logger->debug(self::formatTimed($start, "loaded bake record"));
        }

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
            else
            {
                // If we didn't clean the cache, at least clean the level 0 bake cache,
                // where bake-only plugins can cache things.
                $this->cleanLevel0Cache();
            }
        }
        $this->ensureLevel0Cache();

        // Bake!
        $this->bakePosts();
        $this->bakePages();
        $this->bakeTaxonomies();
    
        $dirBaker = new DirectoryBaker(
            $this->pieCrust,
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
        $this->bakeRecord->getCurrent()->addAssetEntries($dirBaker->getBakedFiles());

        $this->handleDeletions();

        // Post-bake notification.
        $this->callAssistants('onBakeEnd', array($this));
        
        // Save the bake record and clean up.
        if ($bakeRecordPath)
        {
            $start = microtime(true);
            $this->bakeRecord->collapse();
            $this->bakeRecord->saveCurrent($bakeRecordPath);
            $this->logger->debug(self::formatTimed($start, "saved bake record"));
        }
        $this->bakeRecord = null;
        
        $this->pieCrust->getConfig()->setValue('baker/is_baking', false);
        
        $this->logger->info('-------------------------');
        $this->logger->notice(self::formatTimed($overallStart, 'done baking'));
    }

    protected function cleanLevel0Cache()
    {
        $start = microtime(true);
        PathHelper::deleteDirectoryContents($this->pieCrust->getCacheDir() . 'bake_t/0');
        $this->logger->debug(self::formatTimed($start, 'cleaned level 0 cache'));
    }

    protected function ensureLevel0Cache()
    {
        PathHelper::ensureDirectory($this->pieCrust->getCacheDir() . 'bake_t/0', true);
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
            if (
                !$this->bakeRecord->getPrevious()->isVersionMatch() ||
                !$this->bakeRecord->getPrevious()->getBakeTime()
            )
            {
                $cleanCache = true;
                $cleanCacheReason = "need bake record regen";
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
            if ($maxMTime >= $this->bakeRecord->getPrevious()->getBakeTime())
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
        $pages = PageHelper::getPages($this->pieCrust);
        foreach ($pages as $page)
        {
            $this->bakePage($page);
        }
    }
    
    protected function bakePage(IPage $page)
    {
        $start = microtime(true);
        $this->callAssistants('onPageBakeStart', array($page));
        $baker = $this->getPageBaker();
        $didBake = $baker->bake($page);
        $this->callAssistants('onPageBakeEnd', array($page, new BakeResult($didBake)));
        if (!$didBake)
            return false;
        
        $pageCount = $baker->getPageCount();
        $this->logger->info(self::formatTimed($start, ($page->getUri() == '' ? '[main page]' : $page->getUri()) . (($pageCount > 1) ? " [{$pageCount}]" : "")));
        return true;
    }
    
    protected function bakePosts()
    {
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
        $this->callAssistants('onPageBakeStart', array($post));
        $baker = $this->getPageBaker();
        $didBake = $baker->bake($post);
        $this->callAssistants('onPageBakeEnd', array($post, new BakeResult($didBake)));
        if (!$didBake)
            return false;

        $this->logger->info(self::formatTimed($start, $post->getUri()));
        return true;
    }

    protected function bakeTaxonomies()
    {
        // Get some global stuff we'll need.
        $slugifyFlags = $this->pieCrust->getConfig()->getValue('site/slugify_flags');
        $pageRepository = $this->pieCrust->getEnvironment()->getPageRepository();

        // Get the taxonomies.
        $taxonomies = array(
            'tags' => array(
                'multiple' => true,
                'singular' => 'tag',
                'page' => PieCrustDefaults::TAG_PAGE_NAME . '.html'
            ),
            'category' => array(
                'multiple' => false,
                'page' => PieCrustDefaults::CATEGORY_PAGE_NAME . '.html'
            )
        );

        // Get which terms we need to bake.
        $allDirtyTaxonomies = $this->bakeRecord->getDirtyTaxonomies($taxonomies);
        $allUsedCombinations = $this->bakeRecord->getUsedTaxonomyCombinations($taxonomies);

        // Get the taxonomy listing pages, if they exist.
        $taxonomyPages = array();
        $blogKeys = $this->pieCrust->getConfig()->getValue('site/blogs');
        foreach ($taxonomies as $name => $taxonomyMetadata)
        {
            $taxonomyPages[$name] = array();

            foreach ($blogKeys as $blogKey)
            {
                $prefix = '';
                if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
                    $prefix = $blogKey . DIRECTORY_SEPARATOR;

                $termPageName = $prefix . $taxonomyMetadata['page'];
                $themeTermPageName = $taxonomyMetadata['page'];
                $termPagePath = PathHelper::getUserOrThemePath($this->pieCrust, $termPageName, $themeTermPageName);
                $taxonomyPages[$name][$blogKey] = $termPagePath;
            }
        }

        foreach ($allDirtyTaxonomies as $name => $dirtyTerms)
        {
            $taxonomyMetadata = $taxonomies[$name];

            foreach ($dirtyTerms as $blogKey => $dirtyTermsForBlog)
            {
                // Check that we have a term listing page to bake.
                $termPagePath = $taxonomyPages[$name][$blogKey];
                if (!$termPagePath)
                    continue;

                // We have the terms that need to be rebaked.
                $termsToBake = $dirtyTermsForBlog;

                // Look at the combinations of terms we need to consider.
                if ($taxonomyMetadata['multiple'])
                {
                    // User-specified combinations.
                    $forcedCombinations = array();
                    $forcedCombinationParameters = array($name . '_combinations');
                    if (isset($taxonomyMetadata['singular']))
                        $forcedCombinationParameters[] = $taxonomyMetadata['singular'] . '_combinations';
                    foreach ($forcedCombinationParameters as $param)
                    {
                        if (isset($this->parameters[$param]))
                        {
                            $forcedCombinations = $this->parameters[$param];
                            if (array_key_exists($blogKey, $forcedCombinations))
                            {
                                $forcedCombinations = $forcedCombinations[$blogKey];
                            }
                            elseif (count($blogKeys > 1))
                            {
                                $forcedCombinations = array();
                            }
                            break;
                        }
                    }

                    // Collected combinations in use.
                    $usedCombinations = array();
                    if (isset($allUsedCombinations[$name]) &&
                        isset($allUsedCombinations[$name][$blogKey]))
                    {
                        $usedCombinations = $allUsedCombinations[$name][$blogKey];
                    }

                    // Get all the combinations together (forced and used) and keep
                    // those that include a term that we have to rebake.
                    $combinations = array_merge($forcedCombinations, $usedCombinations);
                    if ($combinations)
                    {
                        $combinationsToBake = array();
                        foreach ($combinations as $comb)
                        {
                            if (count(array_intersect($comb, $termsToBake)) > 0)
                                $combinationsToBake[] = $comb;
                        }
                        $termsToBake = array_merge($termsToBake, $combinationsToBake);
                    }
                }

                // Order terms so it looks nice when we bake.
                usort($termsToBake, function ($t1, $t2) {
                    if (is_array($t1))
                        $t1 = implode('+', $t1);
                    if (is_array($t2))
                        $t2 = implode('+', $t2);
                    return strcmp($t1, $t2);
                });

                // Bake!
                foreach ($termsToBake as $term)
                {
                    $start = microtime(true);
                    if ($taxonomyMetadata['multiple'] && is_array($term))
                    {
                        $slugifiedTerm = array_map(
                            function($t) use ($slugifyFlags) {
                                return UriBuilder::slugify($t, $slugifyFlags);
                            },
                                $term
                            );
                        $formattedTerm = implode('+', array_map('rawurldecode', $term));
                    }
                    else
                    {
                        $slugifiedTerm = UriBuilder::slugify($term, $slugifyFlags);
                        $formattedTerm = rawurldecode($term);
                    }

                    if ($name == 'tags')
                    {
                        $uri = UriBuilder::buildTagUri($this->pieCrust, $blogKey, $slugifiedTerm, false);
                        $pageType = IPage::TYPE_TAG;
                    }
                    else if ($name == 'category')
                    {
                        $uri = UriBuilder::buildCategoryUri($this->pieCrust, $blogKey, $slugifiedTerm, false);
                        $pageType = IPage::TYPE_CATEGORY;
                    }
                    $page = $pageRepository->getOrCreatePage(
                        $uri,
                        $termPagePath,
                        $pageType,
                        $blogKey
                    );
                    $page->setPageKey($slugifiedTerm);
                    $this->callAssistants('onPageBakeStart', array($page));
                    $baker = $this->getPageBaker();
                    $baker->bake($page);
                    $this->callAssistants('onPageBakeEnd', array($page, new BakeResult(true)));

                    $pageCount = $baker->getPageCount();
                    $this->logger->info(self::formatTimed($start, "{$name}:{$formattedTerm}" . (($pageCount > 1) ? " [{$pageCount}]" : "")));
                }
            }
        }
    }
    
    protected function handleDeletions()
    {
        $count = 0;
        $start = microtime(true);
        foreach ($this->bakeRecord->getPagesToDelete() as $path => $deleteInfo)
        {
            $reason = $deleteInfo['type'];
            $outputs = $deleteInfo['files'];

            if ($reason == TransitionalBakeRecord::DELETION_MISSING)
                $this->logger->debug("'{$path}' is missing from the bake record:");
            elseif ($reason == TransitionalBakeRecord::DELETION_CHANGED)
                $this->logger->debug("'{$path}' has different outputs than last time:");

            foreach ($outputs as $output)
            {
                if (is_file($output))
                {
                    $this->logger->debug("  Deleting {$output}");
                    unlink($output);
                    ++$count;
                }
            }
        }
        foreach ($this->bakeRecord->getAssetsToDelete() as $path => $outputs)
        {
            $this->logger->debug("'{$path}' is missing from the bake record:");
            foreach ($outputs as $output)
            {
                if (is_file($output))
                {
                    $this->logger->debug("  Deleting {$output}");
                    unlink($output);
                    ++$count;
                }
            }
        }
        if ($count > 0)
        {
            $this->logger->info(self::formatTimed($start, "Deleted {$count} files from the output"));
        }
        else
        {
            $this->logger->debug("No deletions detected.");
        }
    }

    protected function cacheAssistants()
    {
        $this->assistants = array();
        foreach ($this->pieCrust->getPluginLoader()->getBakerAssistants() as $ass)
        {
            $ass->initialize($this->pieCrust, $this->logger);
            $this->assistants[] = $ass;
        }
    }

    protected function callAssistants($method, array $args = array())
    {
        foreach ($this->assistants as $ass)
        {
            call_user_func_array(array(&$ass, $method), $args);
        }
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
