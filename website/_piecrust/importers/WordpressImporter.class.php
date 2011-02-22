<?php

require_once '../libs/sfyaml/lib/sfYamlDumper.php';

class WordpressImporter implements IImporter
{
	protected $xml;

	public function __construct()
	{
	}
	
	public function open($connection)
	{
		$this->xml = simplexml_load_file($connection);
	}
	
	public function importPages($pagesDir)
	{
	}
	
	public function importPosts($postsDir)
	{
		foreach ($this->xml->channel->item as $item)
		{
			$wpChildren = $item->children('wp', TRUE);
			if ($wpChildren->status != 'publish')
				continue;
			
			$title = strval($item->title);
			$filename = strval($wpChildren->post_name);
			$date = strval($wpChildren->post_date);
			
			$contentChildren = $item->children('content', TRUE);
			$content = strval($contentChildren->encoded);		
			
			$timestamp = strtotime($date);
			/*$filename = $postsDir . date('Y', $timestamp) . DIRECTORY_SEPARATOR 
								  . date('m', $timestamp) . DIRECTORY_SEPARATOR
								  . date('d', $timestamp) . '_' . $filename . '.html';*/
			$filename = $postsDir . date('Y-m-d', $timestamp) . '_' . $filename . '.html';
			$data = array(
			   'title' => $title,
			   'excerpt' => $excerpt
			 );
			$yaml = new sfYamlDumper();
			$header = $yaml->dump($data, 1);

			if (!is_dir(dirname($filename)))
				mkdir(dirname($filename), 0777, true);
			$f = fopen($filename, 'w');
			fwrite($f, "---\n");
			fwrite($f, $header);
			fwrite($f, "---\n");
			fwrite($f, $content);
			fclose($f);
		}
	}
	
	public function close()
	{
	}
}

