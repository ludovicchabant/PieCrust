<?php

namespace PieCrust\Interop\Importers;

require_once 'sfYaml/lib/sfYamlDumper.php';

use PieCrust\PieCrustException;

/**
* A class that imports content from a Joomla 1.5.x
* Support importing from MySQL database.
* Inspierd from Wordpress Importer
* Author: Pedram (pi3ch) Hayati
* v0.1
* Usage: chef import joomla USER:PASS@HOST/DATABASE/jos_
* Note: use Pandoc to convert HTML to markdown if needed.
* Note: update the Joomla Section ID in the importPosts function. will fix this in the new version.
*/

class JoomlaImporter extends ImporterBase{
    protected $type; //For future usage not being used in this version
    protected $db;
    protected $authors;
    protected $tablePrefix;
    protected static $joomla_description = "Imports articles from Joomla 1.5 (MySQL)"; //TODO: complete joomla desc

    public function __construct()
    {
        parent::__construct('joomla', self::$joomla_description);
    }

    protected function open($connection)
    {
        $matches=array();
        if(preg_match('/^([\w\d\-\._]+)\:([^@]+)@([\w\d\-\._]+)\/([\w\d\-\._]+)(\/[\w\d_]+)?$/', $connection, $matches)){
            var_dump($connection);
            //get the matches from the MySQL connection string
            $username=$matches[1];
            $password=$matches[2];
            $server=$matches[3];
            $dbName=$matches[4];
            $tablePrefix='jos_';
            if($matches[5])
                $tablePrefix=ltrim($matches[5],'/');
            $this->openMysql($username,$password,$server,$dbName,$tablePrefix);
        } else {
            throw new PieCrustException( "MySQL connection string is not in the valid format.");
        }
       
    }
    
    protected function importPages($pagesDir)
    {
       
    }

    protected function importTemplates($templatesDirs){
    }

    protected function importPosts($postsDir,$mode){
        //TODO passing the Joomla session ID later need to get from command line
        $posts = $this->getPostsFromMysql(2); //UPDATE Joomla Section ID here *******
        foreach ($posts as $post){
            $this->createPost(
                $postsDir,
                $mode,
                $post['name'],
                $post['timestamp'],
                $post['metadata'],
                $post['content']
            );
        }
    }

    protected function importStatic($rootDir){
    }

    protected function close()
    {
        mysql_close($this-db);
        unset($this->db);
    }

    protected function openMysql($username,$password,$server,$dbName,$tablePrefix){
        $this->db=mysql_connect($server,$username,$password);
        if(!$this->db) throw new PieCrustException("Cannot connection to '$server'.");
        echo "Connected to server '$server' as '$username'. Will use table prefix '$tablePrefix'".PHP_EOL;
        if(!mysql_select_db($dbName,$this->db)) throw new PieCrustException("Cannot select database '$dbName' on '$server'");
        $this->tablePrefix=$tablePrefix;
        //Use UTF-8
        mysql_set_charset('utf8', $this->db);
    }

    //param: section Joomla section ID
    protected function getPostsFromMysql($section){
        $result = array();

        $query=mysql_query("SELECT `title`, `alias`, CONCAT(`introtext`,`fulltext`) as content, `created`, `id` FROM {$this->tablePrefix}content WHERE state = '1' AND sectionid = '{$section}' ORDER BY `created` ASC", $this->db);
        if(!$query) throw PieCrustException("Error querying posts from database: ". mysql_error());

        while($row = mysql_fetch_assoc($query)){
            $name=trim($row['alias']);
            $timestamp=strtotime($row['created']);
            $metadata=array();
            $metadata['title']=$row['title'];
            $metadata['id']=$row['id'];
            $content=$row['content'];

            $result[]=array(
                'name'=>$name,
                'timestamp'=>$timestamp,
                'metadata'=>$metadata,
                'content'=>$content
            );
        }
        return $result;
    }

    //Not needed for joomla
    protected function getCleanSlug($name){
        return $name;
    }


}
