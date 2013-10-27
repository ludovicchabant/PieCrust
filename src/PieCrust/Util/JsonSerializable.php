<?php

namespace PieCrust\Util;


interface JsonSerializable
{
    /**
     * Should return an object (most of the time an array) to
     * serialize to JSON.
     */
    public function jsonSerialize();

    /**
     * Should initialize the instance with the deserialized data.
     */
    public function jsonDeserialize($data);
}

