<?php

namespace PieCrust\Page;

use \Exception;
use PieCrust\PieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\Cache;
use PieCrust\Util\UriParser;

require_once 'sfYaml/lib/sfYamlParser.php';


/**
U * A class that represents a page (article or post) in PieCrust.
 *
 */
class Page
{
    /**
     * Page types.
     */
    const TYPE_REGULAR = 1;
    const TYPE_POST = 2;
    const TYPE_TAG = 3;
    const TYPE_CATEGORY = 4;
    
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
    
    protected $blogKey;
    /**
     * Gets the blog key for this page.
     */
    public function getBlogKey()
    {
        return $this->blogKey;
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
        if ($pageNumber != $this->pageNumber)
        {
            $this->pageNumber = $pageNumber;
            $this->config = null;
            $this->contents = null;
            $this->data = null;
            $this->assetor = null;
            $this->paginator = null;
            $this->linker = null;
        }
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
        else if (is_string($date))
        {
            $this->date = strtotime($date);
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
        return $this->type == Page::TYPE_REGULAR;
    }
    
    /**
     * Gets whether this page is a blog post.
     */
    public function isPost()
    {
        return $this->type == Page::TYPE_POST;
    }
    
    /**
     * Gets whether this page is a tag listing.
     */
    public function isTag()
    {
        return $this->type == Page::TYPE_TAG;
    }
    
    /**
     * Gets whether this page is a category listing.
     */
    public function isCategory()
    {
        return $this->type == Page::TYPE_CATEGORY;
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
     *
     * If an 'appKey' is provided, and if the configuration key is not
     * found in the page config, then that same configuration key will
     * be looked up in the PieCrust app's own config, in the 'appKey'
     * section. This is useful for getting a configuration value that
     * can be defined at the app level or overridden at the page level.
     */
    public function getConfigValue($key, $appKey = null)
    {
        if ($this->config === null)
        {
            $this->loadConfigAndContents();
        }
        if (isset($this->config[$key]))
        {
            return $this->config[$key];
        }
        if ($appKey != null)
        {
            return $this->pieCrust->getConfigValue($appKey, $key);
        }
        return null;
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
     * Returns whether a given content segment exists.
     */
    public function hasContentSegment($segment)
    {
        if ($this->contents === null)
        {
            $this->loadConfigAndContents();
        }
        return isset($this->contents[$segment]);
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
            
            if ($this->getConfigValue('date'))
                $timestamp = strtotime($this->getConfigValue('date'));
            else
                $timestamp = $this->getDate();
            if ($this->getConfigValue('time'))
                $timestamp = strtotime($this->getConfigValue('time'), $timestamp);
            $data['page']['timestamp'] = $timestamp;
            $dateFormat = $this->getConfigValue('date_format', ($this->blogKey != null ? $this->blogKey : 'site'));
            $data['page']['date'] = date($dateFormat, $timestamp);
            
            switch ($this->type)
            {
                case Page::TYPE_TAG:
                    if (is_array($this->key))
                    {
                        $data['tag'] = implode(' + ', $this->key);
                    }
                    else
                    {
                        $data['tag'] = $this->key;
                    }
                    break;
                case Page::TYPE_CATEGORY:
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
    public function __construct(PieCrust $pieCrust, $uri, $path, $pageType = Page::TYPE_REGULAR, $blogKey = null, $pageKey = null, $pageNumber = 1, $date = null)
    {
        $this->pieCrust = $pieCrust;
        $this->uri = $uri;
        $this->path = $path;
        $this->type = $pageType;
        $this->blogKey = $blogKey;
        $this->key = $pageKey;
        $this->pageNumber = $pageNumber;
        $this->date = $date;
        
        $this->cache = null;
        if ($pieCrust->isCachingEnabled())
        {
            $this->cache = new Cache($pieCrust->getCacheDir() . 'pages_r');
        }
    }
    
    /**
     * Creates a new Page instance given a fully qualified URI.
     */
    public static function createFromUri(PieCrust $pieCrust, $uri)
    {
        if ($uri == null)
            throw new InvalidArgumentException("The given URI is null.");
        
        $uriInfo = UriParser::parseUri($pieCrust, $uri);
        if ($uriInfo == null or (!$uriInfo['was_path_checked'] and !is_file($uriInfo['path'])))
        {
            if ($uriInfo['type'] == Page::TYPE_TAG) throw new PieCrustException('The special tag listing page was not found.');
            if ($uriInfo['type'] == Page::TYPE_CATEGORY) throw new PieCrustException('The special category listing page was not found.');
            throw new PieCrustException('404');
        }
        
        return new Page(
                $pieCrust,
                $uriInfo['uri'],
                $uriInfo['path'],
                $uriInfo['type'],
                $uriInfo['blogKey'],
                $uriInfo['key'],
                $uriInfo['page'],
                $uriInfo['date']
            );
    }
    
    /**
     * Creates a new Page instance given a path.
     */
    public static function createFromPath(PieCrust $pieCrust, $path, $pageType = Page::TYPE_REGULAR, $pageNumber = 1, $blogKey = null, $pageKey = null, $date = null)
    {
        if ($path == null)
            throw new InvalidArgumentException("The given path is null.");
        if (!is_file($path))
            throw new InvalidArgumentException("The given path does not exist: " . $path);
        
        $uri = Page::buildUri($path, $pageType);
        return new Page(
                $pieCrust,
                $uri,
                $path,
                $pageType,
                $blogKey,
                $pageKey,
                $pageNumber,
                $date
            );
    }
    
    /**
     * Gets the URI of a page given a path.
     */
    public static function buildUri($path, $makePathRelativeTo = null, $stripIndex = true)
    {
        if ($makePathRelativeTo != null)
        {
            $basePath = $makePathRelativeTo;
            if (is_int($makePathRelativeTo))
            {
                switch ($makePathRelativeTo)
                {
                    case Page::TYPE_REGULAR:
                    case Page::TYPE_CATEGORY:
                    case Page::TYPE_TAG:
                        $basePath = $this->pieCrust->getPagesDir();
                        break;
                    case Page::TYPE_POST:
                        $basePath = $this->pieCrust->getPostsDir();
                        break;
                    default:
                        throw new InvalidArgumentException("Unknown page type given: " . $makePathRelativeTo);
                }
            }
            $path = str_replace('\\', '/', substr($path, strlen($baseDir)));
        }
        $uri = preg_replace('/\.[a-zA-Z0-9]+$/', '', $path);    // strip the extension
        if ($stripIndex) $uri = str_replace('_index', '', $uri);// strip special name
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
            
            $this->contents = array('content' => null, 'content.abstract' => null);
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
            $this->contents = array('content' => null, 'content.abstract' => null);
            $data = $this->getPageData();
            $data = array_merge($this->pieCrust->getSiteData($this->isCached()), $data);
            $templateEngine = $this->pieCrust->getTemplateEngine($this->config['template_engine']);
            foreach ($segments as $key => $content)
            {
                ob_start();
                try
                {
                    $templateEngine->renderString($content, $data);
                    $renderedContent = ob_get_clean();
                }
                catch (Exception $e)
                {
                    ob_end_clean();
                    throw new PieCrustException("Error in page '".$this->path."': ".$e->getMessage(), 0, $e);
                }
            
                $this->config['segments'][] = $key;
                $renderedAndFormattedContent = $this->pieCrust->formatText($renderedContent, $this->config['format']);
                $this->contents[$key] = $renderedAndFormattedContent;
                if ($key == 'content')
                {
                    $matches = array();
                    if (preg_match('/^<!--\s*(more|(page)?break)\s*-->\s*$/m', $renderedAndFormattedContent, $matches, PREG_OFFSET_CAPTURE))
                    {
                        // Add a special content segment for the "intro/abstract" part of the article.
                        $offset = $matches[0][1];
                        $abstract = substr($renderedAndFormattedContent, 0, $offset);
                        $this->config['segments'][] = 'content.abstract';
                        $this->contents['content.abstract'] = $abstract;
                    }
                }
            }
            
            // Do not cache the page if 'volatile' data was accessed (e.g. the page displays
            // the latest posts).
            if ($enableCache and $this->cache != null and $this->wasVolatileDataAccessed($data) == false)
            {
                $yamlMarkup = json_encode($this->config);
                $this->cache->write($this->uri, 'json', $yamlMarkup);
                
                $keys = $this->config['segments'];
                foreach ($keys as $key)
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
                $yamlParser = new \sfYamlParser();
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
        $blogKeys = $this->pieCrust->getConfigValueUnchecked('site', 'blogs');
        $validatedConfig = array_merge(
            array(
                'layout' => $this->isPost() ? PieCrust::DEFAULT_POST_TEMPLATE_NAME : PieCrust::DEFAULT_PAGE_TEMPLATE_NAME,
                'format' => $this->pieCrust->getConfigValueUnchecked('site', 'default_format'),
                'template_engine' => $this->pieCrust->getConfigValueUnchecked('site', 'default_template_engine'),
                'content_type' => 'html',
                'title' => 'Untitled Page',
                'blog' => ($this->blogKey != null) ? $this->blogKey : $blogKeys[0],
                'segments' => array()
            ),
            $config);
        return $validatedConfig;
    }
}
