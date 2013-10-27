<?php

namespace PieCrust\Interop\Importers;

use PieCrust\PieCrustException;


/**
 * A class that imports content from a Joomla 1.5.x
 * Support importing from MySQL database.
 * Inspired from Wordpress Importer
 * Author: Pedram (pi3ch) Hayati
 * v0.1
 * Usage: chef import joomla USER:PASS@HOST/DATABASE/jos_
 * Note: use Pandoc to convert HTML to markdown if needed.
 */
class JoomlaImporter extends ImporterBase
{
    protected $db;
    protected $authors;
    protected $tablePrefix;
    protected $sectionID; //Joomla articles' section ID
    protected static $joomla_helpTopic = <<<EOD
The source must be a connection string to the MySQL database the Joomla website is running on. It should be of the form:

    username:password@server/database_name

If the tables in the database don't have the default `jos_` prefix, you can specify the prefix to use with the `--jtableprefix` option.

If the posts you want to import are not the in the default section `0`, you can specify the section ID with the `--jsectionid` option.
EOD;

    public function __construct()
    {
        parent::__construct('joomla', 
            'Imports articles from a Joomla 1.5 website.',
            self::$joomla_helpTopic);
    }

    public function setupParser(\Console_CommandLine $parser)
    {
        parent::setupParser($parser);

        $parser->addOption('j_table_prefix', array(
            'long_name'     => '--jtableprefix',
            'description'   => 'For the Joomla importer: specify the SQL table prefix (default: jos_)',
            'help_name'     => 'PREFIX'
        ));
        $parser->addOption('j_section_id', array(
            'long_name'     => '--jsectionid',
            'description'   => 'For the Joomla importer: sepcify the articles\' section ID (default: 0)',
            'help_name'     => 'SECTION'
        ));
    }

    protected function open($connection)
    {
        $matches=array();
        if (preg_match('/^([\w\d\-\._]+)\:([^@]+)@([\w\d\-\._]+)\/([\w\d\-\._]+)(\/[\w\d_]+)?$/', $connection, $matches))
        {
            // Get the matches from the MySQL connection string.
            $username = $matches[1];
            $password = $matches[2];
            $server = $matches[3];
            $dbName = $matches[4];

            $tablePrefix = 'jos_';
            $sectionID = 0;

            // Check table prefix.
            //TODO: input validation
            if (isset($this->options['j_table_prefix']))
                $tablePrefix = $this->options['j_table_prefix'];

            // Backward compatibility.
            if (isset($matches[5]))
            {
                if (isset($this->options['j_table_prefix']))
                    throw new PieCrustException('You cannot specify both a table prefix in the connection string and with the command line option.');
                $tablePrefix = ltrim($matches[5], '/');
            }

            // Check section id.
            //TODO: input validation
            if (isset($this->options['j_section_id']))
                $sectionID = intval($this->options['j_section_id']);

            $this->openMysql($username, $password, $server, $dbName, $tablePrefix, $sectionID);
        }
        else
        {
            throw new PieCrustException( "MySQL connection string is not in the valid format.");
        }
    }
    
    protected function importPages($pagesDir)
    {
    }

    protected function importTemplates($templatesDirs)
    {
    }

    protected function importPosts($postsDir)
    {
        $posts = $this->getPostsFromMySql();
        foreach ($posts as $post){
            $this->createPost(
                $postsDir,
                $post['name'],
                $post['timestamp'],
                $post['metadata'],
                $post['content']
            );
        }
    }

    protected function importStatic($rootDir)
    {
    }

    protected function close()
    {
        mysql_close($this->db);
        unset($this->db);
    }

    protected function openMysql($username, $password, $server, $dbName, $tablePrefix, $sectionID)
    {
        // Connect to the server and database.
        $this->db = mysql_connect($server, $username, $password);
        if(!$this->db)
            throw new PieCrustException("Cannot connection to '$server'.");
        echo "Connected to server '$server' as '$username'. Will use table prefix '$tablePrefix'".PHP_EOL;
        if(!mysql_select_db($dbName, $this->db))
            throw new PieCrustException("Cannot select database '$dbName' on '$server'");
        $this->tablePrefix = $tablePrefix;
        $this->sectionID = $sectionID;

        // Use UTF-8 encoding.
        mysql_set_charset('utf8', $this->db);
    }

    protected function getPostsFromMySql()
    {
        $result = array();

        $query = mysql_query("SELECT `title`, `alias`, `introtext`, `fulltext`, `created`, `id` FROM {$this->tablePrefix}content WHERE state = '1' AND sectionid = '{$this->sectionID}' ORDER BY `created` ASC", $this->db);
        if(!$query)
            throw PieCrustException("Error querying posts from database: ". mysql_error());

        while($row = mysql_fetch_assoc($query))
        {
            $name = trim($row['alias']);
            $timestamp = strtotime($row['created']);
            $metadata = array();
            $metadata['title'] = $row['title'];
            $metadata['id'] = $row['id'];
            $content = $row['introtext'];
            if ($row['fulltext'])
                $content .= "\n<!--break-->\n\n".$row['fulltext'];

            $result[] = array(
                'name' => $name,
                'timestamp' => $timestamp,
                'metadata' => $metadata,
                'content' => $content
            );
        }

        return $result;
    }

    protected function getCleanSlug($name)
    {
        // Replace '%xx' with '-'
        $name = preg_replace("/%[0-9a-f]{2}/", "-", $name);
        // Replace more than 2 '-' with one
        $name = preg_replace("/\-{2,}/", "-", $name);
        return $name;
    }
}
