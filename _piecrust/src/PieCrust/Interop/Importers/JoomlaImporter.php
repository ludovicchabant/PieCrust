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
    protected $sectionID; //Joomla articles' section ID
    protected static $joomla_helpTopic = "Imports articles from Joomla 1.5 (MySQL)"; //TODO: complete joomla desc

    public function __construct()
    {
        parent::__construct('joomla', 
            'Imports articles from Joomla 1.5',
            self::$joomla_helpTopic);
    }

    public function setupParser(\Console_CommandLine $parser){
        parent::setupParser($parser);
        $parser->addOption('j_table_prefix', array(
            'long_name'     => '--jtblprefix',
            'description'   => 'For Joomla importer: specify SQL table prefix (default: jos_)',
            'help_name'     => 'PREFIX'
        ));
        $parser->addOption('j_section_id', array(
            'long_name'     => '--jsecid',
            'description'   => 'For Joomla importer: sepecify articles\' section ID (default: 1)',
            'help_name'     => 'SECTION'
        ));
    }

    protected function open($connection){
        $matches=array();
        if(preg_match('/^([\w\d\-\._]+)\:([^@]+)@([\w\d\-\._]+)\/([\w\d\-\._]+)(\/[\w\d_]+)?$/', $connection, $matches)){
            var_dump($connection);
            //get the matches from the MySQL connection string
            $username=$matches[1];
            $password=$matches[2];
            $server=$matches[3];
            $dbName=$matches[4];
            $tablePrefix='jos_';
            $sectionID=1;

            //check table prefix
            //TODO: input validation
            if(isset($this->options['j_table_prefix']))
                $tablePrefix=$this->options['j_table_prefix'];

            //backward compatibility
            if(isset($matches[5])){
                if(isset($this->options['j_table_prefix']))
                    throw new PieCrustException('You cannot specify  both a table prefix in the connection string and with the option');
                $tablePrefix=ltrim($matches[5], '/');
            }

            //check section id
            //TODO: input validation
            if(isset($this->options['j_section_id']))
                $sectionID=intval($this->options['j_section_id']);

            $this->openMysql($username,$password,$server,$dbName,$tablePrefix,$sectionID);
        } else {
            throw new PieCrustException( "MySQL connection string is not in the valid format.");
        }
       
    }
    
    protected function importPages($pagesDir){
    }

    protected function importTemplates($templatesDirs){
    }

    protected function importPosts($postsDir,$mode){
        $posts = $this->getPostsFromMysql();
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
        mysql_close($this->db);
        unset($this->db);
    }

    protected function openMysql($username,$password,$server,$dbName,$tablePrefix,$sectionID){
        $this->db=mysql_connect($server,$username,$password);
        if(!$this->db) throw new PieCrustException("Cannot connection to '$server'.");
        echo "Connected to server '$server' as '$username'. Will use table prefix '$tablePrefix'".PHP_EOL;
        if(!mysql_select_db($dbName,$this->db)) throw new PieCrustException("Cannot select database '$dbName' on '$server'");
        $this->tablePrefix=$tablePrefix;
        $this->sectionID=$sectionID;
        //Use UTF-8
        mysql_set_charset('utf8', $this->db);
    }

    protected function getPostsFromMysql(){
        $result = array();

        $query=mysql_query("SELECT `title`, `alias`, CONCAT(`introtext`,`fulltext`) as content, `created`, `id` FROM {$this->tablePrefix}content WHERE state = '1' AND sectionid = '{$this->sectionID}' ORDER BY `created` ASC", $this->db);
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

    protected function getCleanSlug($name){
        //Replace '%xx' with '-'
        $name = preg_replace("/%[0-9a-f]{2}/", "-", $name);
        //Replace more than 2 '-' with one
        $name = preg_replace("/\-{2,}/", "-", $name);
        return $name;
    }


}
