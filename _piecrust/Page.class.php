<?php

define('PIECRUST_PAGE_REGULAR', 1);
define('PIECRUST_PAGE_POST', 2);
define('PIECRUST_PAGE_TAG', 3);
define('PIECRUST_PAGE_CATEGORY', 4);

require_once 'Linker.class.php';
require_once 'Assetor.class.php';
require_once 'Paginator.class.php';

/**
 * A class that represents a page (article or post) in PieCrust.
 *
 */
class Page
{
    protected $pieCrust;
    protected $cache;
    
    protected $path;
    /**
     * Gets the file-system path to the page's file.
     */
    public function getPath()
    {
        return $this->path;
    }
    
    /**
     * Gets the relative file-system path from the app root.
     */
    public function getRelativePath($stripExtension = false)
    {
        $rootDir = $this->pieCrust->getRootDir();
        $relativePath = substr($this->path, strlen($rootDir));
        if ($stripExtension) $relativePath = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
        return $relativePath;
    }
    
    protected $uri;
    /**
     * Gets the PieCrust URI to the page.
     */
    public function getUri()
    {
        return $this->uri;
    }
    
    protected $pageNumber;
    /**
     * Gets the page number (for pages that display a large number of posts).
     */
    public function getPageNumber()
    {
        return $this->pageNumber;
    }
    
    /**
     * Sets the page number.
     */
    public function setPageNumber($pageNumber)
    {
        $this->pageNumber = $pageNumber;
        $this->config = null;
        $this->contents = null;
        $this->data = null;
        $this->assetor = null;
        $this->paginator = null;
        $this->linker = null;
    }
    
    protected $date;
    /**
     * Gets the date this page was created.
     */
    public function getDate()
    {
        if ($this->date === null)
        {
            $this->date = filemtime($this->path);
        }
        return $this->date;
    }
    
    /**
     * Sets the date this page was created.
     */
    public function setDate($date)
    {
        if (is_int($date))
        {
            $this->date = $date;
        }
        else if (is_array($date))
        {
            $this->date = mktime(0, 0, 0, intval($date['month']), intval($date['day']), intval($date['year']));
        }
        else
        {
            throw new PieCrustException("The date must be an integer or an array.");
        }
    }
    
    protected $type;
    /**
     * Gets the page type.
     */
    public function getPageType()
    {
        return $this->type;
    }
    
    /**
     * Gets whether this page is a regular page.
     */
    public function isRegular()
    {
        return $this->type == PIECRUST_PAGE_REGULAR;
    }
    
    /**
     * Gets whether this page is a blog post.
     */
    public function isPost()
    {
        return $this->type == PIECRUST_PAGE_POST;
    }
    
    /**
     * Gets whether this page is a tag listing.
     */
    public function isTag()
    {
        return $this->type == PIECRUST_PAGE_TAG;
    }
    
    /**
     * Gets whether this page is a category listing.
     */
    public function isCategory()
    {
        return $this->type == PIECRUST_PAGE_CATEGORY;
    }
    
    protected $key;
    /**
     * Gets the page key (e.g. the tag or category)
     */
    public function getPageKey()
    {
        return $this->key;
    }
    
    protected $isCached;
    /**
     * Gets whether this page's contents have been cached.
     */
    public function isCached()
    {
        if ($this->isCached === null)
        {
            $cacheTime = $this->getCacheTime();
            if ($cacheTime === false)
            {
                $this->isCached = false;
            }
            else
            {
                $this->isCached = ($cacheTime > filemtime($this->path));
            }
        }
        return $this->isCached;
    }
    
    protected $cacheTime;
    /**
     * Gets the cache time for this page, or false if it was not cached.
     */
    public function getCacheTime()
    {
        if ($this->cacheTime === null)
        {
            if ($this->cache == null)
            {
                $this->cacheTime = false;
            }
            else
            {
                $this->cacheTime = $this->cache->getCacheTime($this->uri, 'json');
            }
        }
        return $this->cacheTime;
    }
    
    protected $config;
    /**
     * Gets the page's configuration from its YAML frontmatter.
     */
    public function getConfig()
    {
        if ($this->config === null)
        {
            $this->loadConfigAndContents();
        }
        return $this->config;
    }
    
    /**
     * Convenience method for accessing a configuration value.
     */
    public function getConfigValue($key)
    {
        if ($this->config === null)
        {
            $this->loadConfigAndContents();
        }
        if (!isset($this->config[$key]))
        {
            return null;
        }
        return $this->config[$key];
    }
    
    protected $contents;
    /**
     * Gets the page's formatted content.
     */
    public function getContentSegment($segment = 'content')
    {
        if ($this->contents === null)
        {
            $this->loadConfigAndContents();
        }
        return $this->contents[$segment];
    }
    
    /**
     * Gets all the page's formatted content segments.
     */
    public function getContentSegments()
    {
        if ($this->contents === null)
        {
            $this->loadConfigAndContents();
        }
        return $this->contents;
    }
    
    protected $data;
    /**
     * Gets the page's data for rendering.
     */
    public function getPageData()
    {
        if ($this->data === null)
        {
            $data = array(
                'page' => $this->getConfig(),
                'asset'=> $this->getAssetor(),
                'pagination' => $this->getPaginator(),
                'link' => $this->getLinker()
            );
            
            $data['page']['url'] = $this->pieCrust->formatUri($this->getUri());
            $data['page']['slug'] = $this->getUri();
            
            $timestamp = $this->getDate();
            if ($this->getConfigValue('time')) $timestamp = strtotime($this->getConfigValue('time'), $timestamp);
            $data['page']['timestamp'] = $timestamp;
            $dateFormat = $this->pieCrust->getConfigValueUnchecked('site', 'date_format');
            $data['page']['date'] = date($dateFormat, $this->getDate());
            
            switch ($this->type)
            {
                case PIECRUST_PAGE_TAG:
                    $data['tag'] = $this->key;
                    break;
                case PIECRUST_PAGE_CATEGORY:
                    $data['category'] = $this->key;
                    break;
            }
            
            if ($this->extraData != null)
            {
                $data = array_merge($data, $this->extraData);
            }
            
            $this->data = $data;
        }
        return $this->data;
    }
    
    protected $extraData;
    /**
     * Adds extra data to the page's data for rendering.
     */
    public function setExtraPageData($data)
    {
        if ($this->data != null or $this->config != null or $this->contents != null)
            throw new PieCrustException("Extra data on a page must be set before the page's configuration, contents and data are loaded.");
        $this->extraData = $data;
    }
    
    protected $assetUrlBaseRemap;
    /**
     * Gets the asset URL base remapping pattern.
     */
    public function getAssetUrlBaseRemap()
    {
        return $this->assetUrlBaseRemap;
    }
    
    /**
     * Sets the asset URL base remapping pattern.
     */
    public function setAssetUrlBaseRemap($remap)
    {
        $this->assetUrlBaseRemap = $remap;
    }
    
    protected $paginator;
    /**
     * Gets the paginator.
     */
    public function getPaginator()
    {
        if ($this->paginator === null)
        {
            $this->paginator = new Paginator($this->pieCrust, $this);
        }
        return $this->paginator;
    }
    
    protected $assetor;
    /**
     * Gets the assetor.
     */
    public function getAssetor()
    {
        if ($this->assetor === null)
        {
            $this->assetor = new Assetor($this->pieCrust, $this);
        }
        return $this->assetor;
    }
    
    protected $linker;
    /**
     * Gets the linker.
     */
    public function getLinker()
    {
        if ($this->linker === null)
        {
            $this->linker = new Linker($this->pieCrust, $this);
        }
        return $this->linker;
    }
    
    /**
     * Creates a new Page instance.
     */
    public function __construct(PieCrust $pieCrust, $uri)
    {
        $this->pieCrust = $pieCrust;
        if ($uri != null)
        {
            $uriInfo = Page::parseUri($pieCrust, $uri);
            
            $this->uri = $uriInfo['uri'];
            $this->pageNumber = $uriInfo['page'];
            $this->date = $uriInfo['date'];
            $this->type = $uriInfo['type'];
            $this->key = $uriInfo['key'];
            $this->path = $uriInfo['path'];
            
            if ($this->path == null or (!$uriInfo['was_path_checked'] and !is_file($this->path)))
            {
                if ($this->type == PIECRUST_PAGE_TAG) throw new PieCrustException('The special tag listing page was not found.');
                if ($this->type == PIECRUST_PAGE_CATEGORY) throw new PieCrustException('The special category listing page was not found.');
                throw new PieCrustException('404');
            }
        }
        else
        {
            $this->uri = null;
            $this->pageNumber = 1;
            $this->date = null;
            $this->type = PIECRUST_PAGE_REGULAR;
            $this->key = null;
            $this->path = null;
        }
        
        $this->cache = null;
        if ($pieCrust->isCachingEnabled())
        {
            $this->cache = new Cache($pieCrust->getCacheDir() . 'pages_r');
        }
    }
    
    /**
     * Creates a new Page instance with pre-determined properties.
     */
    public static function create(PieCrust $pieCrust, $uri, $path, $pageType = PIECRUST_PAGE_REGULAR, $pageNumber = 1, $pageKey = null)
    {
        $page = new Page($pieCrust, null);
        $page->uri = trim($uri, '/');
        $page->path = realpath($path);
        $page->type = $pageType;
        $page->pageNumber = $pageNumber;
        $page->key = $pageKey;
        return $page;
    }
    
    /**
     *	Parse a relative URI and returns information about it.
     */
    public static function parseUri(PieCrust $pieCrust, $uri)
    {
        if (strpos($uri, '..') !== false)	// Some bad boy's trying to access files outside of our standard folders...
        {
            throw new PieCrustException('404');
        }
        
        $uri = trim($uri, '/');
		if ($uri == '') $uri = PIECRUST_INDEX_PAGE_NAME;
		
        $pageNumber = 1;
        $matches = array();
        if (preg_match('/\/(\d+)\/?$/', $uri, $matches))
        {
            // Requesting a page other than the first for this article.
            $uri = substr($uri, 0, strlen($uri) - strlen($matches[0]));
            $pageNumber = intval($matches[1]);
        }
		$matches = array();
		$uriWithoutExtension = $uri;
		if (preg_match('/\.[a-zA-Z0-9]+$/', $uri, $matches))
		{
			// There's an extension specified. Strip it
			// (the extension is probably because the page has a `content_type` different than HTML, which means
			//  it would be baked into a static file with that extension).
			$uriWithoutExtension = substr($uri, 0, strlen($uri) - strlen($matches[0]));
		}
        
        // Try first with a regular page path.
        $key = null;
		$date = null;
        $type = PIECRUST_PAGE_REGULAR;
        $path = $pieCrust->getPagesDir() . str_replace('/', DIRECTORY_SEPARATOR, $uriWithoutExtension) . '.html';
        $pathWasChecked = false;
        if (!is_file($path))
        {
            $matches = array();
            
            // Try with a post.
            $postsPattern = Paginator::buildPostUrlPattern($pieCrust->getConfigValueUnchecked('site', 'post_url'));
            if (preg_match($postsPattern, $uri, $matches))
            {
                // Requesting a post.
                $type = PIECRUST_PAGE_POST;
                $baseDir = $pieCrust->getPostsDir();
                $postsFs = $pieCrust->getConfigValueUnchecked('site', 'posts_fs');
                switch ($postsFs)
                {
                case 'hierarchy':
                    $path = $baseDir . $matches['year'] . DIRECTORY_SEPARATOR . $matches['month'] . DIRECTORY_SEPARATOR . $matches['day'] . '_' . $matches['slug'] . '.html';
                    break;
                case 'flat':
                default:
                    $path = $baseDir . $matches['year'] . '-' . $matches['month'] . '-' . $matches['day'] . '_' . $matches['slug'] . '.html';
                    break;
                }
                $date = mktime(0, 0, 0, intval($matches['month']), intval($matches['day']), intval($matches['year']));
            }
            else
            {
                // Try with a tag page.
                $tagsPattern = Paginator::buildTagUrlPattern($pieCrust->getConfigValueUnchecked('site', 'tag_url'));
                if (preg_match($tagsPattern, $uri, $matches))
                {
                    $key = $matches['tag'];
                    $type = PIECRUST_PAGE_TAG;
                    $path = $pieCrust->getPagesDir() . PIECRUST_TAG_PAGE_NAME . '.html';
                }
                else
                {
                    // Try with a category page.
                    $categoryPattern = Paginator::buildCategoryUrlPattern($pieCrust->getConfigValueUnchecked('site', 'category_url'));
                    if (preg_match($categoryPattern, $uri, $matches))
                    {
                        $key = $matches['cat'];
                        $type = PIECRUST_PAGE_CATEGORY;
                        $path = $pieCrust->getPagesDir() . PIECRUST_CATEGORY_PAGE_NAME . '.html';
                    }
					else
					{
						$path = null;
					}
                }
            }
        }
        else
        {
            $pathWasChecked = true;
        }
        
        return array(
            'uri' => $uri,
            'page' => $pageNumber,
            'type' => $type,
            'key' => $key,
            'date' => $date,
            'path' => $path,
            'was_path_checked' => $pathWasChecked
        );
    }
    
    /**
     * Gets the URI of a page given a path.
     */
    public static function buildPageUri(PieCrust $pieCrust, $path, $stripIndex = true)
    {
        $pagesDir = $pieCrust->getPagesDir();
        return Page::buildUri($pagesDir, $path, $stripIndex);
    }
    
    /**
     * Gets the URI of a post given a path.
     */
    public static function buildPostUri(PieCrust $pieCrust, $path, $stripIndex = true)
    {
        $postsDir = $pieCrust->getPostsDir();
        return Page::buildUri($postsDir, $path, $stripIndex);
    }
    
    /**
     * Gets the URI of a page given a path and a base directory (probably the pages
     * or posts directory).
     */
    public static function buildUri($baseDir, $path, $stripIndex = true)
    {
        $relativePath = str_replace('\\', '/', substr(realpath($path), strlen($baseDir)));
        $uri = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
        if ($stripIndex) $uri = str_replace('_index', '', $uri);
        return $uri;
    }
    
    protected function loadConfigAndContents()
    {
        // Caching must be disabled if we get some extra page data because then this page
        // is not self-contained anymore.
        $enableCache = ($this->extraData == null);
        
        if ($enableCache and $this->isCached())
        {
            // Get the page from the cache.
            $configText = $this->cache->read($this->uri, 'json');
            $config = json_decode($configText, true);
            $this->config = $this->buildValidatedConfig($config);
            
            $this->contents = array();
            foreach ($this->config['segments'] as $key)
            {
                $this->contents[$key] = $this->cache->read($this->uri, $key . '.html');
            }
        }
        else
        {
            // Re-format/process the page.
            $rawContents = file_get_contents($this->path);
            $headerOffset = 0;
            $this->config = $this->parseConfig($rawContents, $headerOffset);
            
            $segments = $this->parseContentSegments($rawContents, $headerOffset);
            $this->contents = array();
            $data = $this->getPageData();
            $data = array_merge($this->pieCrust->getSiteData(), $data);
            $templateEngine = $this->pieCrust->getTemplateEngine($this->config['template_engine']);
            foreach ($segments as $key => $content)
            {
                ob_start();
                $templateEngine->renderString($content, $data);
                $renderedContent = ob_get_clean();
            
                $this->config['segments'][] = $key;
                $this->contents[$key] = $this->pieCrust->formatText($renderedContent, $this->config['format']);
            }
            
            // Do not cache the page if 'volatile' data was accessed (e.g. the page displays
            // the latest posts).
            if ($enableCache and $this->cache != null and $this->wasVolatileDataAccessed($data) == false)
            {
                $yamlMarkup = json_encode($this->config);
                $this->cache->write($this->uri, 'json', $yamlMarkup);
                
                foreach (array_keys($segments) as $key)
                {
                    $this->cache->write($this->uri . '.' . $key, 'html', $this->contents[$key]);
                }
            }
        }
        if (!isset($this->config) or $this->config == null or 
            !isset($this->contents) or $this->contents == null)
        {
            throw new PieCrustException('An unknown error occured while loading the contents and configuration for page: ' . $this->uri);
        }
    }
    
    protected function wasVolatileDataAccessed($data)
    {
        return $data['pagination']->wasPaginationDataAccessed();
    }
    
    protected function parseConfig($rawContents, &$offset)
    {
        $yamlHeaderMatches = array();
        $hasYamlHeader = preg_match('/\A(---\s*\n)((.*\n)*?)^(---\s*\n)/m', $rawContents, $yamlHeaderMatches);
        if ($hasYamlHeader == true)
        {
            $yamlHeader = substr($rawContents, strlen($yamlHeaderMatches[1]), strlen($yamlHeaderMatches[2]));
            try
            {
                $yamlParser = new sfYamlParser();
                $config = $yamlParser->parse($yamlHeader);
            }
            catch (Exception $e)
            {
                throw new PieCrustException('An error occured while reading the YAML header for the requested article: ' . $e->getMessage());
            }
            $offset = strlen($yamlHeaderMatches[0]);
        }
        else
        {
            $config = array();
            $offset = 0;
        }
        
        return $this->buildValidatedConfig($config);
    }
    
    protected function parseContentSegments($rawContents, $offset)
    {
        $end = strlen($rawContents);
        $matches = array();
        $matchCount = preg_match_all('/^---(\w+)---\s*\n/m', $rawContents, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, $offset);
        if ($matchCount > 0)
        {
            $contents = array();
            
            if ($matches[0][0][1] > $offset)
            {
                // There's some default content at the beginning.
                $contents['content'] = substr($rawContents, $offset, $matches[0][0][1] - $offset);
            }
            
            for ($i = 0; $i < $matchCount; ++$i)
            {
                // Get each segment as the text that's between the end of the current captured string
                // and the beginning of the next captured string (or the end of the input text if
                // the current is the last capture).
                $matchStart = $matches[0][$i][1] + strlen($matches[0][$i][0]);
                $matchEnd = ($i < $matchCount - 1) ? $matches[0][$i+1][1] : $end;
                $segmentName = $matches[1][$i][0];
                $segmentContent = substr($rawContents, $matchStart, $matchEnd - $matchStart);
                $contents[$segmentName] = $segmentContent;
            }
            
            return $contents;
        }
        else
        {
            // No segments, just the content.
            return array('content' => substr($rawContents, $offset));
        }
    }
    
    protected function buildValidatedConfig($config)
    {
        // Add the default page config values.
        $validatedConfig = array_merge(
            array(
                'layout' => $this->isPost() ? PIECRUST_DEFAULT_POST_TEMPLATE_NAME : PIECRUST_DEFAULT_PAGE_TEMPLATE_NAME,
                'format' => $this->pieCrust->getConfigValueUnchecked('site', 'default_format'),
				'template_engine' => $this->pieCrust->getConfigValueUnchecked('site', 'default_template_engine'),
                'content_type' => 'html',
                'title' => 'Untitled Page',
                'segments' => array()
            ),
            $config);
        return $validatedConfig;
    }
}
