<?php

namespace PieCrust\Interop\Importers;

require_once 'sfYaml/lib/sfYamlDumper.php';

use PieCrust\PieCrustException;


/**
 * A class that imports content from a Wordpress blog. Supports both
 * importing from an XML file (exported from Wordpress' dashboard) or
 * directly from the MySQL database.
 */
class WordpressImporter implements IImporter
{
    protected $type;
    protected $db;
    protected $authors;

    protected $tablePrefix;

    public function __construct()
    {
    }
    
    public function getName()
    {
        return "wordpress";
    }

    public function getDescription()
    {
        return "Imports pages and posts from a Wordpress blog. " . 
            "The source must be a path to an XML file exported from the Wordpress dashboard, " .
            "or a connection string to the MySQL database the blog is running on. " .
            "That connection string must be of the form: " . PHP_EOL .
            "username:password@server/database_name" . PHP_EOL .
            "A suffix of the form `/prefix` can also be specified if the tables " . PHP_EOL .
            "in the database don't have the default `wp_` prefix.";
    }
    
    public function open($connection)
    {
        $matches = array();
        if (preg_match('/^([\w\d\-\._]+)\:([^@]+)@([\w\d\-\._]+)\/([\w\d\-\._]+)(\/[\w\d_]+)?$/', $connection, $matches))
        {
            $username = $matches[1];
            $password = $matches[2];
            $server = $matches[3];
            $dbName = $matches[4];
            $tablePrefix = 'wp_';
            if ($matches[5])
                $tablePrefix = ltrim($matches[5], '/');

            $this->openMySql($username, $password, $server, $dbName, $tablePrefix);
         }
        else
        {
            $this->openXml($connection);
        }
    }

    public function close()
    {
        if ($this->type == 'mysql')
        {
            mysql_close($this->db);
            $this->db = null;
        }
    }
    
    public function importPages($pagesDir)
    {
        switch ($this->type)
        {
        case 'xml':
            $this->importPagesFromXml($pagesDir);
            break;
        case 'mysql':
            $this->importPagesFromMySql($pagesDir);
            break;
        }
    }
    
    public function importPosts($postsDir, $mode)
    {
        switch ($this->type)
        {
        case 'xml':
            $this->importPostsFromXml($postsDir, $mode);
            break;
        case 'mysql':
            $this->importPostsFromMySql($postsDir, $mode);
            break;
        }
    }

    // SQL Import {{{
    
    protected function openMySql($username, $password, $server, $dbName, $tablePrefix)
    {
        echo "Connected to server '$server' as '$username'. Will use table prefix '$tablePrefix'." . PHP_EOL;

        // Connect to the server and database.
        $this->db = mysql_connect($server, $username, $password);
        if (!$this->db) throw new PieCrustException("Can't connect to '$server'.");
        if (!mysql_select_db($dbName)) throw new PieCrustException("Can't select database '$dbName' on '$server'.");
        $this->type = 'mysql';
        $this->tablePrefix = $tablePrefix;

        // Use UTF8 encoding.
        mysql_set_charset('utf8');

        // Gather the authors' names.
        $this->authors = array();
        $query = mysql_query("SELECT display_name FROM {$this->tablePrefix}users");
        if (!$query) throw new PieCrustException("Error querying authors from the database: " . mysql_error());
        while ($row = mysql_fetch_assoc($query))
        {
            $this->authors[] = $row['display_name'];
        }
    }

    protected function importPagesFromMySql($pagesDir)
    {
        $posts = $this->getPostsFromMySql('page');
        foreach ($posts as $post)
        {
            $this->createPage(
                $pagesDir, 
                $post['name'], 
                $post['timestamp'], 
                $post['metadata'], 
                $post['content']
            );       
        }
    }

    protected function importPostsFromMySql($postsDir, $mode)
    {
        $posts = $this->getPostsFromMySql('post');
        foreach ($posts as $post)
        {
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

    protected function getPostsFromMySql($postType)
    {
        $result = array();

        $query = mysql_query("SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_name, guid FROM {$this->tablePrefix}posts WHERE post_status = 'publish' AND post_type = '{$postType}'");
        if (!$query) throw new PieCrustException("Error querying posts from the database: " . mysql_error());

        while ($row = mysql_fetch_assoc($query))
        {
            $name = trim($row['post_name']);
            if ($name == '')
                $name = preg_replace('/\s+/', '-', strtolower($row['post_title']));

            $timestamp = strtotime($row['post_date']);

            $metadata = array();
            $metadata['title'] = $row['post_title'];
            $metadata['id'] = $row['ID'];
            $metadata['author'] = $this->authors[intval($row['post_author']) - 1];
            //TODO: tags

            $content = $row['post_content'];

            $result[] = array(
                'name' => $name,
                'timestamp' => $timestamp,
                'metadata' => $metadata,
                'content' => $content
            );
        }

        return $result;
    }

    // }}}

    // XML Import {{{

    protected function openXml($connection)
    {
        echo "Loading: " . $connection . PHP_EOL;

        // Load the XML file.
        $this->db = simplexml_load_file($connection);
        $this->type = 'xml';

        // Gather the authors' names.
        $this->authors = array();
        foreach ($this->db->xpath('/rss/channel/wp:author') as $author)
        {
            $login = (string)current($author->xpath('wp:author_login'));
            $displayName = (string)current($author->xpath('wp:author_display_name'));
            $this->authors[$login] = $displayName;
        }
    }

    protected function importPagesFromXml($pagesDir)
    {
        $posts = $this->getPostsFromXml('page');
        foreach ($posts as $post)
        {
            $this->createPage(
                $pagesDir, 
                $post['name'], 
                $post['timestamp'], 
                $post['metadata'], 
                $post['content']
            );
        }
    }

    protected function importPostsFromXml($postsDir, $mode)
    {
        $posts = $this->getPostsFromXml('post');
        foreach ($posts as $post)
        {
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

    protected function getPostsFromXml($postType)
    {
        $result = array();
        foreach ($this->db->channel->item as $item)
        {
            $wpChildren = $item->children('wp', true);
            if ($wpChildren->status != 'publish')
                continue;
            if ($wpChildren->post_type != $postType)
                continue;

            $name = trim(strval($wpChildren->post_name));
            if ($name == '')
                $name = preg_replace('/\s+/', '-', strtolower(strval($item->title)));

            $timestamp = strtotime(strval($wpChildren->post_date));
            
            $metadata = array();
            $metadata['title'] = strval($item->title);
            $metadata['id'] = intval($wpChildren->post_id);

            $dcChildren = $item->children('dc', true);
            $metadata['author'] = $this->authors[strval($dcChildren->creator)];
            
            $tags = $item->xpath('category[@domain=\'post_tag\'][@nicename]');
            $metadata['tags'] = array_map(function($n) { return strval($n['nicename']); }, $tags);
            
            if (isset($item->excerpt) && $item->excerpt)
                $metadata['excerpt'] = $item->excerpt;
            
            $contentChildren = $item->children('content', true);
            $content = strval($contentChildren->encoded);       
            
            $result[] = array(
                'name' => $name,
                'timestamp' => $timestamp,
                'metadata' => $metadata,
                'content' => $content
            );
        }
        return $result;
    }

    // }}}
    
    protected function createPage($pagesDir, $name, $timestamp, $metadata, $content)
    {
        // Get a clean name.
        $name = $this->getCleanSlug($name);

        // Come up with the filename.
        $filename = $pagesDir . $name . '.html';

        // Build the config data that goes in the header.
        $configData = $metadata;
        $configData['date'] = date('Y-m-d', $timestamp);
        $configData['time'] = date('H:i:s', $timestamp);

        // Write it!
        $this->writePageFile($configData, $content, $filename);
    }

    protected function createPost($postsDir, $mode, $name, $timestamp, $metadata, $content)
    {
        // Get a clean name.
        $name = $this->getCleanSlug($name);

        // Come up with the filename.
        if ($mode == 'hierarchy')
        {
            $filename = $postsDir . date('Y', $timestamp) . DIRECTORY_SEPARATOR 
                . date('m', $timestamp) . DIRECTORY_SEPARATOR
                . date('d', $timestamp) . '_' . $name . '.html';
        }
        else if ($mode == 'shallow')
        {
            $filename = $postsDir . date('Y', $timestamp) . DIRECTORY_SEPARATOR
                . date('m-d', $timestamp) . '_' . $name . '.html';
        }
        else
        {
            $filename = $postsDir . date('Y-m-d', $timestamp) . '_' . $name . '.html';
        }

        // Build the config data that goes in the header.
        $configData = $metadata;
        if (!isset($configData['time']))
            $configData['time'] = date('H:i:s', $timestamp);

        // Write it!
        $this->writePageFile($configData, $content, $filename);
    }

    protected function writePageFile($configData, $content, $filename)
    {
        // Get the YAML string for the config data.
        $yaml = new \sfYamlDumper();
        $header = $yaml->dump($configData, 3);

        // Write the post's contents.
        echo " > " . pathinfo($filename, PATHINFO_FILENAME) . "\n";
        if (!is_dir(dirname($filename)))
            mkdir(dirname($filename), 0777, true);
        $f = fopen($filename, 'w');
        fwrite($f, "---\n");
        fwrite($f, $header);
        fwrite($f, "---\n");
        fwrite($f, $content);
        fclose($f);

    }

    protected function getCleanSlug($name)
    {
        // Clean up the name for URL and file-system use:
        // - Replace all funky '%xx' characters by '-'.
        // - Replace 2 or more consecutive '-'s by a single one.
        $name = preg_replace("/%[0-9a-f]{2}/", "-", $name);
        $name = preg_replace("/\-{2,}/", "-", $name);
        return $name;
    }
}

