<?php

namespace PieCrust\Interop\Importers;

use PieCrust\PieCrustException;


/**
 * A class that imports content from a Wordpress blog. Supports both
 * importing from an XML file (exported from Wordpress' dashboard) or
 * directly from the MySQL database.
 */
class WordpressImporter extends ImporterBase
{
    protected static $wordpress_helpTopic = <<<EOD
The source must be a path to an XML file exported from the Wordpress dashboard, or a connection string to the MySQL database the blog is running on. That connection string must be of the form: 

    username:password@server/database_name

If the tables in the database don't have the default `wp_` prefix, you can specify the prefix to use with the `--wptableprefix` option to the `import` command.
EOD;

    protected $type;
    protected $db;
    protected $authors;

    protected $tablePrefix;

    public function __construct()
    {
        parent::__construct('wordpress', 
            "Imports pages and posts contents from a Wordpress blog.",
            self::$wordpress_helpTopic);
    }

    public function setupParser(\Console_CommandLine $parser)
    {
        parent::setupParser($parser);

        $parser->addOption('wp_table_prefix', array(
            'long_name'   => '--wptableprefix',
            'description' => "For the WordPress importer: specify the SQL table prefix (default: wp_).",
            'help_name'   => 'PREFIX'
        ));
    }
    
    protected function open($connection)
    {
        $matches = array();
        if (preg_match('/^([\w\d\-\._]+)\:([^@]+)@([\w\d\-\._]+)\/([\w\d\-\._]+)(\/[\w\d_]+)?$/', $connection, $matches))
        {
            $username = $matches[1];
            $password = $matches[2];
            $server = $matches[3];
            $dbName = $matches[4];

            $tablePrefix = 'wp_';
            if (isset($this->options['wp_table_prefix']))
                $tablePrefix = $this->options['wp_table_prefix'];

            if (isset($matches[5]))
            {
                if (isset($this->options['wp_table_prefix']))
                    throw new PieCrustException("You can't specify both a table prefix in the connection string and with the command line option.");
                $tablePrefix = ltrim($matches[5], '/');
            }

            $this->openMySql($username, $password, $server, $dbName, $tablePrefix);
         }
        else
        {
            $this->openXml($connection);
        }
    }

    protected function close()
    {
        if ($this->type == 'mysql')
        {
            mysql_close($this->db);
            $this->db = null;
        }
    }
    
    protected function importPages($pagesDir)
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

    protected function importTemplates($templatesDirs)
    {
    }
    
    protected function importPosts($postsDir, $mode)
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

    protected function importStatic($rootDir)
    {
    }

    // SQL Import {{{
    
    protected function openMySql($username, $password, $server, $dbName, $tablePrefix)
    {
        // Connect to the server and database.
        $this->db = mysql_connect($server, $username, $password);
        if (!$this->db)
            throw new PieCrustException("Can't connect to '$server'.");
        $this->logger->info("Connected to server '$server' as '$username'. Will use table prefix '$tablePrefix'.");
        if (!mysql_select_db($dbName))
            throw new PieCrustException("Can't select database '$dbName' on '$server'.");
        $this->type = 'mysql';
        $this->tablePrefix = $tablePrefix;

        // Use UTF8 encoding.
        mysql_set_charset('utf8');

        // Gather the authors' names.
        $this->authors = array();
        $query = mysql_query("SELECT display_name FROM {$this->tablePrefix}users");
        if (!$query)
            throw new PieCrustException("Error querying authors from the database: " . mysql_error());
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
        if (!$query)
            throw new PieCrustException("Error querying posts from the database: " . mysql_error());

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
        $this->logger->info("Loading: " . $connection);

        // Load the XML file.
        $this->db = simplexml_load_file($connection);
        $this->type = 'xml';

        // Gather the authors' names.
        // (note that some versions of Wordpress' XML export don't
        //  declare authors like this, and instead just specify the
        //  full name each time in the posts)
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
            $authorKey = strval($dcChildren->creator);
            if (isset($this->authors[$authorKey]))
                $metadata['author'] = $this->authors[$authorKey];
            else
                $metadata['author'] = $authorKey;
            
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

