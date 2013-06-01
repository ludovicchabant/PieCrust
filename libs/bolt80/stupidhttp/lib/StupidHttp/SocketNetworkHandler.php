<?php

/**
 * A network handler that uses sockets.
 */
class StupidHttp_SocketNetworkHandler extends StupidHttp_NetworkHandler
{
    protected $sock;
    protected $sockSendBufferSize;
    protected $sockReceiveBufferSize;
    protected $leftOver;

    /**
     * Builds a new instance of StupidHttp_SocketNetworkHandler.
     */
    public function __construct($address, $port)
    {
        parent::__construct($address, $port);
        $this->sock = null;
        $this->leftOver = null;
    }

    /**
     * Initializes the network handler.
     */
    public function register()
    {
        if (($this->sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
        {
            throw new StupidHttp_NetworkException("Can't create socket: " . socket_strerror(socket_last_error()));
        }
        
        if (@socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1) === false)
        {
            throw new StupidHttp_NetworkException("Can't set options on the socket: " . socket_strerror(socket_last_error()));
        }
        
        if (@socket_bind($this->sock, $this->address, $this->port) === false)
        {
            throw new StupidHttp_NetworkException("Can't bind socket to {$this->address}:{$this->port}: " . socket_strerror(socket_last_error($this->sock)));
        }
        
        if (@socket_listen($this->sock) === false)
        {
            throw new StupidHttp_NetworkException("Failed listening to socket on {$this->address}:{$this->port}: " . socket_strerror(socket_last_error($this->sock)));
        }

        $this->sockSendBufferSize = @socket_get_option($this->sock, SOL_SOCKET, SO_SNDBUF);
        $this->sockReceiveBufferSize = @socket_get_option($this->sock, SOL_SOCKET, SO_RCVBUF);
    }

    /**
     * Shuts down the network handler.
     */
    public function unregister()
    {
        if ($this->sock !== null)
        {
            socket_close($this->sock);
            $this->sock = null;
        }
    }

    /**
     * Waits for incoming connections.
     */
    public function connect(array $connections, $options)
    {
        $dummy = array();
        $read = $connections;
        $read[] = $this->sock;
        $ready = @socket_select(
            $read,
            $dummy,
            $dummy,
            $options['poll_interval']
        );
        if ($ready === false)
        {
            throw new StupidHttp_NetworkException("Failed to monitor incoming connections.");
        }
        if ($ready == 0)
        {
            return null;
        }

        // Check for a new connection.
        $i = array_search($this->sock, $read);
        if ($i !== false)
        {
            // Remove our socket from the connections and replace it
            // with the file-descriptor for the new client.
            unset($read[$i]);

            if (($msgsock = @socket_accept($this->sock)) === false)
            {
                throw new StupidHttp_NetworkException(
                    "Failed accepting connection: " .
                    socket_strerror(socket_last_error($this->sock))
                );
            }

            if (@socket_set_option($msgsock, SOL_SOCKET, SO_REUSEADDR, 1) === false)
            {
                throw new StupidHttp_NetworkException(
                    "Failed setting address re-use option: " .
                    socket_strerror(socket_last_error($msgsock))
                );
            }

            $timeout = array('sec' => $options['timeout'], 'usec' => 0);
            if (@socket_set_option($msgsock, SOL_SOCKET, SO_RCVTIMEO, $timeout) === false)
            {
                throw new StupidHttp_NetworkException(
                    "Failed setting timeout value: " .
                    socket_strerror(socket_last_error($msgsock))
                );
            }

            $read[] = $msgsock;
        }

        return $read;
    }

    /**
     * Gets a client's information.
     */
    public function getClientInfo($connection)
    {
        $address = '';
        $port = 0;
        @socket_getpeername($connection, $address, $port);
        return array(
            'address' => $address,
            'port' => $port
        );
    }

    /**
     * Disconnects from an existing client connection.
     */
    public function disconnect($connection)
    {
        @socket_shutdown($connection);
        @socket_close($connection);
    }

    /**
     * Reads from an open client connection until a given delimiter is encountered.
     */
    public function readUntil($connection, $delimiter)
    {
        $data = '';
        while (true)
        {
            // Get the next piece of data from the left-overs from
            // the previous network read (if any), or from the network.
            if ($this->leftOver)
            {
                $buf = $this->leftOver;
                $this->leftOver = null;
            }
            else
            {
                $buf = $this->readFromSocket($connection);
                if (strlen($buf) == 0)
                    break;
            }
            
            // See if the delimiter is in the data we got...
            $i = strpos($buf, $delimiter);
            if ($i !== false)
            {
                // Yep! Return data up to the delimiter, and keep
                // the rest as the left-overs for next read.
                $data .= substr($buf, 0, $i);
                $this->leftOver = substr($buf, $i + strlen($delimiter));
                break;
            }

            $data .= $buf;
        }
        if (strlen($data) == 0)
            return false;
        return $data;
    }

    /**
     * Reads data from a client connection.
     */
    public function read($connection, $length)
    {
        $data = '';
        $lengthLeft = $length;
        while ($lengthLeft > 0)
        {
            if ($this->leftOver)
            {
                if (strlen($this->leftOver) <= $lengthLeft)
                {
                    // We want more than just the left-overs from the previous
                    // network read... consume it all and keep going.
                    $data .= $this->leftOver;
                    $lengthLeft -= strlen($this->leftOver);
                    $this->leftOver = null;
                }
                else
                {
                    // We have enough data in the left-overs to satisfy the
                    // caller... give what we were asked and keep the rest.
                    $data .= substr($this->leftOver, 0, $lengthLeft);
                    $lengthLeft = 0;
                    $this->leftOver = substr($this->leftOver, $lengthLeft);
                }
            }
            else
            {
                // Read from the network.
                $curLength = min($this->sockReceiveBufferSize, $lengthLeft);
                $buf = $this->readFromSocket($connection);
                if (strlen($buf) == 0)
                {
                    throw new StupidHttp_NetworkException("No more data received from the network, but we are still expecting {$lengthLeft} bytes.");
                }

                $data .= $buf;
                $lengthLeft -= strlen($buf);
            }
        }
        return $data;
    }

    /**
     * Writes data to a client connection.
     */
    public function write($connection, $data)
    {
        $transmitted = 0;
        $dataLength = strlen($data);
        while ($transmitted < $dataLength)
        {
            $socketWriteLength = min($dataLength - $transmitted, $this->sockSendBufferSize);
            $transmittedThisTime = @socket_write($connection, $data, $socketWriteLength);
            if (false === $transmittedThisTime)
            {
                throw new StupidHttp_NetworkException("Couldn't write response to socket: " . socket_strerror(socket_last_error($connection)));
            }
            $transmitted += $transmittedThisTime;
            $data = substr($data, $transmittedThisTime);

            $leftToGo = $dataLength - $transmitted;
            if ($leftToGo > 0)
                $this->log->debug('Transmitted ' . $transmittedThisTime . ' bytes, ' . $leftToGo . ' left to go...');
        }
        return $transmitted;
    }

    protected function readFromSocket($connection)
    {
        if (false === ($buf = @socket_read($connection, $this->sockReceiveBufferSize)))
        {
            throw new StupidHttp_NetworkException(socket_strerror(socket_last_error($connection)));
        }
        return $buf;
    }
}

