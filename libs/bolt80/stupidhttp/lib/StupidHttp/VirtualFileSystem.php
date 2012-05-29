<?php


/**
 * A class responsible for handling static files served by the server.
 */
class StupidHttp_VirtualFileSystem
{
    protected $documentRoot;
    protected $mounts;
    protected $mimeTypes;

    /**
     * Gets the root directory for the static files.
     */
    public function getDocumentRoot()
    {
        return $this->documentRoot;
    }

    /**
     * Gets the mime types to be used by the server.
     */
    public function getMimeTypes()
    {
        $this->ensureMimeTypes();
        return $this->mimeTypes;
    }
    
    /**
     * Sets the mime types to be used by the server.
     */
    public function setMimeTypes($mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;
    }
    
    /**
     * Sets a specific mime type for a given file extension.
     */
    public function setMimeType($extension, $mimeType)
    {
        $this->ensureMimeTypes();
        $this->mimeTypes[$extension] = $mimeType;
    }

    /**
     * Builds a new instance of StupidHttp_VirtualFileSystem.
     */
    public function __construct($documentRoot)
    {
        if ($documentRoot != null)
        {
            if (!is_dir($documentRoot))
            {
                throw new StupidHttp_WebException("The given document root is not valid: " . $documentRoot);
            }
            $this->documentRoot = rtrim($documentRoot, '/\\');
        }
        else
        {
            $this->documentRoot = null;
        }
        $this->mounts = array();
    }

    /**
     * Adds a virtual mount point to the file system.
     */
    public function addMountPoint($directory, $alias)
    {
        $this->mounts[$alias] = rtrim($directory, '/\\');
    }

    /**
     * Returns a web response that corresponds to serving a given static file.
     */
    public function serveDocument(StupidHttp_WebRequest $request, $documentPath)
    {
        // First, check for timestamp if possible.
        $serverTimestamp = filemtime($documentPath);
        $ifModifiedSince = $request->getHeader('If-Modified-Since');
        if ($ifModifiedSince != null)
        {
            $clientTimestamp = strtotime($ifModifiedSince);
            if ($clientTimestamp > $serverTimestamp)
            {
                return new StupidHttp_WebResponse(304);
            }
        }
        
        // ...otherwise, check for similar checksum.
        $documentSize = filesize($documentPath);
        if ($documentSize == 0)
        {
            return new StupidHttp_WebResponse(200);
        }
        $documentHandle = fopen($documentPath, "rb");
        $contents = fread($documentHandle, $documentSize);
        fclose($documentHandle);
        if ($contents === false)
        {
            throw new StupidHttp_WebException('Error reading file: ' . $documentPath, 500);
        }
        $contentsHash = md5($contents);
        $ifNoneMatch = $request->getHeader('If-None-Match');
        if ($ifNoneMatch != null)
        {
            if ($ifNoneMatch == $contentsHash)
            {
                return new StupidHttp_WebResponse(304);
            }
        }
        
        // ...ok, let's send the file.
        $this->ensureMimeTypes();
        $extension = pathinfo($documentPath, PATHINFO_EXTENSION);
        $headers = array(
            'Content-Length' => $documentSize,
            'Content-MD5' => base64_encode($contentsHash),
            'Content-Type' => (
                isset($this->mimeTypes[$extension]) ? 
                    $this->mimeTypes[$extension] : 
                    'text/plain'
            ),
            'ETag' => $contentsHash,
            'Last-Modified' => date("D, d M Y H:i:s T", filemtime($documentPath))
        );
        return new StupidHttp_WebResponse(200, $headers, $contents);
    }
    
    /**
     * Returns a web response that corresponds to serving the contents of a
     * given directory.
     */
    public function serveDirectory(StupidHttp_WebRequest $request, $documentPath)
    {
        $headers = array();
        
        $contents = '<ul>' . PHP_EOL;
        foreach (new DirectoryIterator($documentPath) as $entry)
        {
            $contents .= '<li>' . $entry->getFilename() . '</li>' . PHP_EOL;
        }
        $contents .= '</ul>' . PHP_EOL;
        
        $replacements = array(
            '%path%' => $documentPath,
            '%contents%' => $contents
        );
        $body = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'directory-listing.html');
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        return new StupidHttp_WebResponse(200, $headers, $body);
    }
    
    public function getDocumentPath($uri)
    {
        if ($this->documentRoot == null)
            return null;

        $root = $this->documentRoot;
        $uri = rawurldecode($uri);
        $secondSlash = strpos($uri, '/', 1);
        if ($secondSlash !== false)
        {
            $firstDir = substr($uri, 1, $secondSlash - 1);
            if (isset($this->mounts[$firstDir]))
            {
                $root = $this->mounts[$firstDir];
                $uri = substr($uri, $secondSlash);
            }
        }
        if ($root === false)
            return false;
        return $root . str_replace('/', DIRECTORY_SEPARATOR, $uri);
    }
    
    /**
     * Finds the index document for a given directory (e.g. `index.html`).
     */
    public function getIndexDocument($path)
    {
        static $indexDocuments = array(
            'index.htm',
            'index.html'
        );
        $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
        foreach ($indexDocuments as $doc)
        {
            if (is_file($path . $doc))
            {
                return $path . $doc;
            }
        }
        return null;
    }

    protected function ensureMimeTypes()
    {
        if ($this->mimeTypes !== null)
            return;

        // Lazy-load the MIME types.
        $mimeTypesPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mime.types';
        $handle = @fopen($mimeTypesPath, "r");
        if ($handle)
        {
            $hasError = false;
            $this->mimeTypes = array();
            while (($buffer = fgets($handle, 4096)) !== false)
            {
                $tokens = preg_split('/\s+/', $buffer, -1, PREG_SPLIT_NO_EMPTY);
                if (count($tokens) > 1)
                {
                    for ($i = 1; $i < count($tokens); $i++)
                    {
                        $this->mimeTypes[$tokens[$i]] = $tokens[0];
                    }
                }
            }
            if (!feof($handle))
                $hasError = true;
            fclose($handle);
            if ($hasError) 
                throw new StupidHttp_WebException("An error occured while reading the mime.types file: " . $mimeTypesPath);
        }
        else
        {
            throw new StupidHttp_WebException("Can't find the 'mime.types' file: " . $mimeTypesPath);
        }
    }
}

