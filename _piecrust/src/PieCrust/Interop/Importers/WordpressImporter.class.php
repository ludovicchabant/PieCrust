<?php

namespace PieCrust\Interop\Importers;

require_once 'sfYaml/lib/sfYamlDumper.php';


class WordpressImporter implements IImporter
{
    protected $xml;

    public function __construct()
    {
    }
    
    public function getName()
    {
        return "wordpress";
    }
    
    public function open($connection)
    {
        $this->xml = simplexml_load_file($connection);
    }
    
    public function importPages($pagesDir)
    {
    }
    
    public function importPosts($postsDir, $mode)
    {
        foreach ($this->xml->channel->item as $item)
        {
            $wpChildren = $item->children('wp', TRUE);
            if ($wpChildren->status != 'publish')
                continue;
            if ($wpChildren->post_type != 'post')
                continue;
            
            $title = strval($item->title);
            $date = strval($wpChildren->post_date);
            $filename = strval($wpChildren->post_name);
            echo " > " . $filename . "\n";
            
            $dcChildren = $item->children('dc', TRUE);
            $author = strval($dcChildren->creator);
            
            $tags = $item->xpath('category[@domain=\'tag\'][@nicename]');
            
            $contentChildren = $item->children('content', TRUE);
            $content = strval($contentChildren->encoded);       
            
            $timestamp = strtotime($date);
            if ($mode == 'hierarchy')
            {
                $filename = $postsDir . date('Y', $timestamp) . DIRECTORY_SEPARATOR 
                                      . date('m', $timestamp) . DIRECTORY_SEPARATOR
                                      . date('d', $timestamp) . '_' . $filename . '.html';
            }
            else
            {
                $filename = $postsDir . date('Y-m-d', $timestamp) . '_' . $filename . '.html';
            }
            
            $data = array(
                'title' => $title,
                'id' => intval($wpChildren->post_id),
                'time' => date('H:i:s', $timestamp),
                'author' => $author,
                'tags' => array_map(function($n) { return strval($n['nicename']); }, $tags)
            );
            if ($excerpt != null && $excerpt != '')
            {
                $data['excerpt'] = $excerpt;
            }
            
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
