<?php

namespace PieCrust\Util;

use PieCrust\PieCrustException;
use PieCrust\Util\PathHelper;


class JsonSerializer
{
    public static function serializeData($obj)
    {
        if (!($obj instanceof JsonSerializable))
            throw new PieCrustException("The given object is not `JsonSerializable`.");
        $data = $obj->jsonSerialize();
        $data['__class_name'] = get_class($obj);
        $data = json_encode($data);
        return $data;
    }

    public static function serializeArray(array $arr)
    {
        $data = array();
        foreach ($arr as $item)
        {
            $data[] = $item->jsonSerialize();
        }
        return $data;
    }

    public static function serialize($obj, $filename)
    {
        PathHelper::ensureDirectory(dirname($filename), true);
        $data = self::serializeData($obj);
        file_put_contents($filename, $data);
    }

    public static function deserializeData($json)
    {
        $data = json_decode($json, true);
        if (!isset($data['__class_name']))
            throw new PieCrustException("The data doesn't contain the `__class_name` special key.");
        $className = $data['__class_name'];
        $obj = new $className();
        if (!($obj instanceof JsonSerializable))
            throw new PieCrustException("Class '{$className}' is not `JsonSerializable`.");
        $obj->jsonDeserialize($data);
        return $obj;
    }

    public static function deserializeArray($data, $className)
    {
        $arr = array();
        foreach ($data as $itemJson)
        {
            $item = new $className();
            $item->jsonDeserialize($itemJson);
            $arr[] = $item;
        }
        return $arr;
    }

    public static function deserialize($filename)
    {
        $json = file_get_contents($filename);
        return deserializeData($json);
    }

    public static function deserializeInto($obj, $filename)
    {
        if (!($obj instanceof JsonSerializable))
            throw new PieCrustException("Class '{$className}' is not `JsonSerializable`.");

        $json = file_get_contents($filename);
        $data = json_decode($json, true);
        if (!isset($data['__class_name']))
            throw new PieCrustException("The data doesn't contain the `__class_name` special key.");
        $className = $data['__class_name'];
        if (get_class($obj) != $className)
            throw new PieCrustException("Serialized data is for class '{$className}' but given object is of class: " . get_class($obj));
        $obj->jsonDeserialize($data);
    }
}

