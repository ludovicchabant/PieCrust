<?php

/**
 *
 */
class BakeRecord
{
	protected $lastBakeInfo;
	
	protected $postInfos;
	protected $postTags;
	protected $postCategories;
	protected $tagsToBake;
	protected $categoriesToBake;
	protected $wasAnyPostBaked;
	
	/**
	 *
	 */
	public function __construct($lastBakeInfoPath)
	{
		$this->postInfos = array();
		$this->postTags = array();
		$this->postCategories = array();
		$this->tagsToBake = array();
		$this->categoriesToBake = array();
		$this->wasAnyPostBaked = false;
		
		$this->loadLastBakeInfo($lastBakeInfoPath);
	}
	
	/**
	 *
	 */
	public function addPostInfo(array $postInfo, $wasBaked)
	{
		$postIndex = count($this->postInfos);
		$this->postInfos[] = $postInfo;

		$tags = $postInfo['tags'];
		if ($tags != null)
		{
			foreach ($tags as $tag)
			{
				if (!isset($this->postTags[$tag])) $this->postTags[$tag] = array();
				$this->postTags[$tag][] = $postIndex;
				
				if ($wasBaked) $this->tagsToBake[$tag] = true;
			}
		}
		
		$category = $postInfo['category'];
		if ($category != null)
		{
			if (!isset($this->postCategories[$category])) $this->postCategories[$category] = array();
			$this->postCategories[$category][] = $postIndex;
			
			if ($wasBaked) $this->categoriesToBake[$category] = true;
		}
		
		$this->wasAnyPostBaked = ($this->wasAnyPostBaked or $wasBaked);
	}

	/**
	 *
	 */
	public function addPageUsingPosts($relativePath)
	{
		$this->lastBakeInfo['pagesUsingPosts'][$relativePath] = true;
	}
	
	/**
	 *
	 */
	public function wasAnyPostBaked()
	{
		return $this->wasAnyPostBaked;
	}
	
	/**
	 *
	 */
	public function isPageUsingPosts($relativePath)
	{
		return ($this->lastBakeInfo['pagesUsingPosts'][$relativePath] === true);
	}
	
	/**
	 *
	 */
	public function getTagsToBake()
	{
		return array_keys($this->tagsToBake);
	}
	
	/**
	 *
	 */
	public function getPostsTagged($tag)
	{
		$postInfos = array();
		$postIndices = $this->postTags[$tag];
		foreach ($postIndices as $i)
		{
			$postInfos[] = $this->postInfos[$i];
		}
		return $postInfos;
	}
	
	/**
	 *
	 */
	public function getCategoriesToBake()
	{
		return array_keys($this->categoriesToBake);
	}
	
	/**
	 *
	 */
	public function getPostsInCategory($category)
	{
		$postInfos = array();
		$postIndices = $this->postCategories[$category];
		foreach ($postIndices as $i)
		{
			$postInfos[] = $this->postInfos[$i];
		}
		return $postInfos;
	}
	
	/**
	 *
	 */
	public function getLastBakeTime()
	{
		return $this->lastBakeInfo['time'];
	}
	
	/**
	 *
	 */
	public function getLast($what)
	{
		return $this->lastBakeInfo[$what];
	}

	/**
	 *
	 */
	public function saveBakeInfo($bakeInfoPath, array $infos = array())
	{
		$infos = array_merge(
			array('time' => time(), 'url_base' => '/'),
			$infos
		);
		
		$this->lastBakeInfo['time'] = $infos['time'];
		$this->lastBakeInfo['url_base'] = $infos['url_base'];
		
		$jsonMarkup = json_encode($this->lastBakeInfo);
		file_put_contents($bakeInfoPath, $jsonMarkup);
	}
	
	protected function loadLastBakeInfo($bakeInfoPath)
	{
		$bakeInfo = array(
			'time' => false,
			'url_base' => '/',
			'pagesUsingPosts' => array()
		);
	
		if (is_file($bakeInfoPath))
		{
			$loadedBakeInfo = json_decode(file_get_contents($bakeInfoPath), true);
			$bakeInfo = array_merge($bakeInfo, $loadedBakeInfo);
		}
		$this->lastBakeInfo = $bakeInfo;
	}
}

