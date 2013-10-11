<?php

namespace PieCrust\Baker;

use PieCrust\Util\JsonSerializable;
use PieCrust\Util\JsonSerializer;


class BakeRecordAssetEntry implements JsonSerializable
{
    public $path;
    public $outputs;

    public function initialize($path, $bakeInfo)
    {
        $this->path = $path;
        $this->outputs = false;
        if ($bakeInfo['was_baked'] && isset($bakeInfo['outputs']))
            $this->outputs = $bakeInfo['outputs'];
    }

    public function wasBaked()
    {
        return (bool)$this->outputs;
    }

    public function jsonSerialize()
    {
        return array(
            'path' => $this->path,
            'outputs' => $this->outputs
        );
    }

    public function jsonDeserialize($data)
    {
        $this->path = $data['path'];
        $this->outputs = $data['outputs'];
    }
}

