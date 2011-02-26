<?php

class FileSystem
{
	protected $pieCrust;
	
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
	}
	
	public function getHierarchicalPostFiles()
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
	
	public function getFlatPostFiles()
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
	
	public static function ensureDirectory($dir)
	{
		if (!is_dir($dir))
		{
			mkdir($dir, 0777, true);
		}
	}
	
	public static function deleteDirectory($dir, $skipPattern = '/^(\.)?empty(\.txt)?/i', $level = 0)
	{
		$skippedFiles = false;
		$files = new FilesystemIterator($dir);
		foreach ($files as $file)
		{
			if ($skipPattern != null and preg_match($skipPattern, $file->getFilename()))
			{
				$skippedFiles = true;
				continue;
			}
			
			if($file->isDir())
			{
				FileSystem::deleteDirectory($file->getPathname(), $skipPattern, $level + 1);
			}
			else
			{
				unlink($file);
			}
		}
		
		if ($level > 0 and !$skippedFiles and is_dir($dir))
		{
			rmdir($dir);
		}
	}
}
