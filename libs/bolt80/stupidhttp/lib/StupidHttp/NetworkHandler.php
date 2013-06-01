<?php

/**
 * The base class for classes responsible with network communication.
 */
class StupidHttp_NetworkHandler
{
    protected $address;
    /**
     * Gets the IP address (or host name) of the server.
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    protected $port;
    /**
     * Gets the port of the server.
     */
    public function getPort()
    {
        return $this->port;
    }

    protected $log;
    /**
     * Gets the logger.
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Sets the logger.
     */
    public function setLog($log)
    {
        $this->log = $log;
    }

    /**
     * Builds a new instance of StupidHttp_NetworkHandler.
     */
    public function __construct($address, $port)
    {
        $this->address = $address;
        $this->port = $port;
        $this->log = new StupidHttp_Log();
    }

    /**
     * Initializes the network handler.
     */
    public function register()
    {
    }

    /**
     * Shuts down the network handler.
     */
    public function unregister()
    {
    }

    /**
     * Waits for a new client connection, and returns the connection resource.
     *
     * The returned resource should be unique to the client connection.
     */
    public function connect(array $connections, $options)
    {
        return null;
    }

    /**
     * Gets a client's information.
     *
     * Returns an array of the form:
     * { 'address' => '123.456.7.8', 'port' => '9' }
     */
    public function getClientInfo($connection)
    {
    }

    /**
     * Disconnects from an existing client connection.
     */
    public function disconnect($connection)
    {
    }

    /**
     * Reads from an open client connection until a given delimiter is 
     * encountered.
     *
     * The returned data should not include the delimiter, nor should the
     * delimiter be included in the next call to `read` or `readUntil`.
     */
    public function readUntil($connection, $delimiter)
    {
        return '';
    }

    /**
     * Reads data from a client connection.
     *
     * The returned data could be shorter than the specified length if
     * there is no more data to be fetched from the network.
     */
    public function read($connection, $length)
    {
        return '';
    }

    /**
     * Writes data to a client connection.
     *
     * This should return the number of bytes transmitted.
     */
    public function write($connection, $data)
    {
        return 0;
    }
}

