<?php

require_once 'FileSystem.class.php';


/**
 * The pagination manager for a page split into sub-pages.
 *
 * Pages that display a large number of posts may be split into
 * several sub-pages. The Paginator class handles figuring out what
 * posts to include for the current page number.
 *
 */
class Paginator
{
    protected $pieCrust;
	protected $page;
    
	/**
	 * Creates a new Paginator instance.
	 */
    public function __construct(PieCrust $pieCrust, Page $page)
    {
        $this->pieCrust = $pieCrust;
		$this->page = $page;
    }
	
	/**
	 * Gets the posts for this page.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function posts()
	{
		$pagination = $this->getPaginationData();
		return $pagination['posts'];
	}
	
	/**
	 * Gets the previous page's URI.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function prev_page()
	{
		$pagination = $this->getPaginationData();
		return $pagination['prev_page'];
	}
	
	/**
	 * Gets this page's URI.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function this_page()
	{
		$pagination = $this->getPaginationData();
		return $pagination['this_page'];
	}
	
	/**
	 * Gets thenext page's URI.
	 *
	 * This method is meant to be called from the layouts via the template engine.
	 */
	public function next_page()
	{
		$pagination = $this->getPaginationData();
		return $pagination['next_page'];
	}
	
	/**
	 * Gets whether the pagination data was requested by the page.
	 */
	public function wasPaginationDataAccessed()
	{
		return ($this->paginationData != null);
	}
	
	/**
	 * Gets whether the current page has more pages to show.
	 */
	public function hasMorePages()
	{
		return ($this->next_page() != null);
	}
	
	protected $paginationData;
    /**
	 * Gets the pagination data for rendering.
	 */
    public function getPaginationData()
	{
		if ($this->paginationData === null)
		{
			$fs = new FileSystem($this->pieCrust);
			$this->buildPaginationData($fs->getPostFiles());
		}
        return $this->paginationData;
    }
	
	/**
	 * Rebuilds the pagination data with the given posts.
	 */
	public function buildPaginationData(array $postInfos)
	{
		$postsData = array();
		$nextPageIndex = null;
		$previousPageIndex = ($this->page->getPageNumber() > 2) ? $this->page->getPageNumber() - 1 : null;
		
		if (count($postInfos) > 0)
		{
			// Load all the posts for the requested page number (page numbers start at '1').
			$postsUrlFormat = $this->pieCrust->getConfigValueUnchecked('site', 'posts_urls');
			$postsPerPage = $this->page->getConfigValue('posts_per_page');
			if (!$postsPerPage) $postsPerPage = $this->pieCrust->getConfigValueUnchecked('site', 'posts_per_page');
			$postsDateFormat = $this->page->getConfigValue('posts_date_format');
			if (!$postsDateFormat) $postsDateFormat = $this->pieCrust->getConfigValueUnchecked('site', 'posts_date_format');
			
			$offset = ($this->page->getPageNumber() - 1) * $postsPerPage;
			$upperLimit = min($offset + $postsPerPage, count($postInfos));
			for ($i = $offset; $i < $upperLimit; ++$i)
			{
				$postInfo = $postInfos[$i];
				// Create the post with all the stuff we already know.
				$post = Page::create(
					$this->pieCrust,
					Paginator::buildPostUrl($postsUrlFormat, $postInfo), 
					$postInfo['path'],
					true);
				$post->setAssetUrlBaseRemap($this->page->getAssetUrlBaseRemap());

				// Build the pagination data entry for this post.
				$postData = $post->getConfig();
				$postData['url'] = $post->getUri();
				
				$postDateTimeStr = $postInfo['year'] . '-' . $postInfo['month'] . '-' . $postInfo['day'];
				if ($post->getConfigValue('time')) $postDateTimeStr .= ' ' . $postDateTimeStr;
				$postDateTime = strtotime($postDateTimeStr);
				$postData['timestamp'] = $postDateTime;
				$postData['date'] = date($postsDateFormat, $postDateTime);
				
				$postContents = $post->getContentSegment();
				$postContentsSplit = preg_split('/^<!--\s*(more|(page)?break)\s*-->\s*$/m', $postContents, 2);
				$postData['content'] = $postContentsSplit[0];
				if (count($postContentsSplit) > 1) $postData['has_more'] = true;
				
				$postsData[] = $postData;
			}
			
			if ($offset + $postsPerPage < count($postInfos) and
				!($this->page->getConfigValue('single_page')))
			{
				// There's another page following this one.
				$nextPageIndex = $this->page->getPageNumber() + 1;
			}
		}
		
		$this->paginationData = array(
								'posts' => $postsData,
								'prev_page' => ($previousPageIndex == null) ? null : $this->page->getUri() . '/' . $previousPageIndex,
								'this_page' => $this->page->getUri() . '/' . $this->page->getPageNumber(),
								'next_page' => ($nextPageIndex == null) ? null : ($this->page->getUri() . '/' . $nextPageIndex)
								);
	}
	
	/**
	 * Builds the URL of a post given a URL format.
	 */
	public static function buildPostUrl($postUrlFormat, $postInfo)
	{
		$replacements = array(
			'%year%' => $postInfo['year'],
			'%month%' => $postInfo['month'],
			'%day%' => $postInfo['day'],
			'%slug%' => $postInfo['name']
		);
		return str_replace(array_keys($replacements), array_values($replacements), $postUrlFormat);
	}
    
    /**
     * Builds the regex pattern to match the given URL format.
     */
    public static function buildPostUrlPattern($postUrlFormat)
    {
        static $replacements = array(
			'%year%' => '(?P<year>\d{4})',
			'%month%' => '(?P<month>\d{2})',
			'%day%' => '(?P<day>\d{2})',
			'%slug%' => '(?P<slug>.*?)'
		);
        return '/^' . str_replace(array_keys($replacements), array_values($replacements), preg_quote($postUrlFormat, '/')) . '$/';
    }
    
    /**
     * Builds the URL of a tag listing.
     */
    public static function buildTagUrl($tagUrlFormat, $tag)
    {
        return str_replace('%tag%', $tag, $tagUrlFormat);
    }
    
    /**
     * Builds the URL of a category listing.
     */
    public static function buildCategoryUrl($categoryUrlFormat, $category)
    {
        return str_replace('%category%', $category, $categoryUrlFormat);
    }
}
