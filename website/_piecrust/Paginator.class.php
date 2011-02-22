<?php

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
	protected $pageUri;
	protected $pageNumber;
    
	/**
	 * Creates a new Paginator instance.
	 */
    public function __construct(PieCrust $pieCrust, Page $page)
    {
        $this->pieCrust = $pieCrust;
		$this->pageUri = $page->getUri();
		$this->pageNumber = $page->getPageNumber();
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
	
	protected $paginationData;
    /**
	 * Gets the pagination data for rendering.
	 */
    public function getPaginationData()
	{
		if ($this->paginationData === null)
		{
			$postsData = array();
			$nextPageIndex = null;
			$previousPageIndex = ($this->pageNumber > 2) ? $this->pageNumber - 1 : '';
			
			// Find all HTML posts in the posts directory.
			$postsFs = $this->pieCrust->getConfigValue('site', 'posts_fs');
			switch ($postsFs)
			{
			case 'hierarchy':
				$postInfos = $this->getHierarchicalPostFiles();
				break;
			case 'flat':
			default:
				$postInfos = $this->getFlatPostFiles();
				break;
			}
			if (count($postInfos) > 0)
			{
				// Load all the posts for the requested page number (page numbers start at '1').
				$postsPrefix = $this->pieCrust->getConfigValue('site', 'posts_prefix');
				$postsPerPage = $this->pieCrust->getConfigValue('site', 'posts_per_page');
				$postsDateFormat = $this->pieCrust->getConfigValue('site', 'posts_date_format');
				$offset = ($this->pageNumber - 1) * $postsPerPage;
				$upperLimit = min($offset + $postsPerPage, count($postInfos));
				for ($i = $offset; $i < $upperLimit; ++$i)
				{
					$postInfo = $postInfos[$i];
					$post = Page::createPost(
						$this->pieCrust,
						$postsPrefix . '/' . $postInfo['year'] . '/' . $postInfo['month'] . '/' . $postInfo['day'] . '/' . $postInfo['name'], 
						$postInfo['path']);

					$postConfig = $post->getConfig();
					$postDateTime = strtotime($postInfo['year'] . '-' . $postInfo['month'] . '-' . $postInfo['day']);
					$postContents = $post->getContents();
					$postContentsSplit = preg_split('/^<!--\s*(more|(page)?break)\s*-->\s*$/m', $postContents, 2);
					$postUri = $post->getUri();
					
					$postsData[] = array(
						'title' => $postConfig['title'],
						'url' => $postUri,
						'date' => date($postsDateFormat, $postDateTime),
						'content' => $postContentsSplit[0]
					);
				}
				
				if ($offset + $postsPerPage < count($postInfos))
				{
					// There's another page following this one.
					$nextPageIndex = $this->pageNumber + 1;
				}
			}
			
			$this->paginationData = array(
									'posts' => $postsData,
									'prev_page' => ($this->pageUri == '_index' && $previousPageIndex == null) ? '' : $this->pageUri . '/' . $previousPageIndex,
									'this_page' => $this->pageUri . '/' . $this->pageNumber,
									'next_page' => $this->pageUri . '/' . $nextPageIndex
									);
		}
        return $this->paginationData;
    }
	
	protected function getHierarchicalPostFiles()
	{
		$result = array();
		
		$years = array();
		$yearsIterator = new DirectoryIterator($this->pieCrust->getPostsDir());
		foreach ($yearsIterator as $year)
		{
			if (preg_match('/^\d{4}$/', $year->getFilename()) == false)
				continue;
			
			$thisYear = $year->getFilename();
			$years[] = $thisYear;
		}
		rsort($years);
		
		foreach ($years as $year)
		{
			$months = array();
			$monthsIterator = new DirectoryIterator($this->pieCrust->getPostsDir() . $year);
			foreach ($monthsIterator as $month)
			{
				if (preg_match('/^\d{2}$/', $month->getFilename()) == false)
					continue;
				
				$thisMonth = $month->getFilename();
				$months[] = $thisMonth;
			}
			rsort($months);
				
			foreach ($months as $month)
			{
				$days = array();
				$postsIterator = new DirectoryIterator($this->pieCrust->getPostsDir() . $year . DIRECTORY_SEPARATOR . $month);
				foreach ($postsIterator as $post)
				{
					$matches = array();
					if (preg_match('/^(\d{2})_(.*)\.html$/', $post->getFilename(), $matches) == false)
						continue;
					
					$thisDay = $matches[1];
					$days[$thisDay] = array('name' => $matches[2], 'path' => $post->getPathname());
				}
				krsort($days);
				
				foreach ($days as $day => $info)
				{
					$result[] = array(
						'year' => $year,
						'month' => $month,
						'day' => $day,
						'name' => $info['name'],
						'path' => $info['path']
					);
				}
			}
		}
		
		return $result;
	}
	
	protected function getFlatPostFiles()
	{
		$pathPattern = $this->pieCrust->getPostsDir() . '*.html';
		$paths = glob($pathPattern, GLOB_ERR);
		if ($paths === false)
		{
			throw new PieCrustException('An error occured while reading the posts directory.');
		}
		rsort($paths);
		
		$result = array();
		foreach ($paths as $path)
		{
			$matches = array();
			
			$filename = pathinfo($path, PATHINFO_BASENAME);
			if (preg_match('/^(\d{4})-(\d{2})-(\d{2})_(.*)\.html$/', $filename, $matches) == false)
				continue;
			
			$result[] = array(
				'year' => intval($matches[1]),
				'month' => intval($matches[2]),
				'day' => intval($matches[3]),
				'name' => $matches[4],
				'path' => $path
			);
		}
		return $result;
	}
}
