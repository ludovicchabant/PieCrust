<?php

class Paginator
{
    protected $pieCrust;
    
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
    
    public function getPaginationData($pageUri, $pageNumber)
	{
        $postsData = array();
        $nextPageIndex = null;
        $previousPageIndex = ($pageNumber > 2) ? $pageNumber - 1 : '';
        
        // Find all HTML posts in the posts directory.
        $pathPattern = $this->pieCrust->getPostsDir() . '*.html';
        $paths = glob($pathPattern, GLOB_ERR);
        if ($paths === false)
            throw new PieCrustException('An error occured while reading the posts directory.');
        if (count($paths) > 0)
        {
            // Posts will be named year-month-day_title.html so reverse-sorting the files by name
            // should arrange them in a nice counter-chronological order.
            rsort($paths);
            
            // Load all the posts for the requested page number (page numbers start at '1').
            $postsPerPage = $this->pieCrust->getConfigValue('site', 'posts_per_page');
            $postsDateFormat = $this->pieCrust->getConfigValue('site', 'posts_date_format');
            $offset = ($pageNumber - 1) * $postsPerPage;
            $upperLimit = min($offset + $postsPerPage, count($paths));
            for ($i = $offset; $i < $upperLimit; ++$i)
            {
                $matches = array();
                $filename = pathinfo($paths[$i], PATHINFO_FILENAME);
                if (preg_match('/^((\d+)-(\d+)-(\d+))_(.*)$/', $filename, $matches) == false)
                    continue;
                    
                $post = new Page($this->pieCrust, '/' . $matches[2] . '/' . $matches[3] . '/' . $matches[4] . '/' . $matches[5]);
                $postConfig = $post->getConfig();
                $postDateTime = strtotime($matches[1]);
                $postContents = $post->getContents();
                $postContentsSplit = preg_split('/^<!--\s*(more|(page)?break)\s*-->\s*$/m', $postContents, 2);
                $postUri = $post->getUri();
                
                array_push($postsData, array(
                    'title' => $postConfig['title'],
                    'url' => $postUri,
                    'date' => date($postsDateFormat, $postDateTime),
                    'content' => $postContentsSplit[0]
                ));
            }
            
            if ($offset + $postsPerPage < count($paths))
            {
                // There's another page following this one.
                $nextPageIndex = $pageNumber + 1;
            }
        }
        
        $paginationData = array(
                                'posts' => $postsData,
                                'prev_page' => ($pageUri == '_index' && $previousPageIndex == null) ? '' : $pageUri . '/' . $previousPageIndex,
                                'this_page' => $pageUri . '/' . $pageNumber,
                                'next_page' => $pageUri . '/' . $nextPageIndex
                                );
        return $paginationData;
    }
}
