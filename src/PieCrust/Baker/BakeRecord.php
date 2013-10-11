<?php

namespace PieCrust\Baker;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\Environment\LinkCollector;
use PieCrust\Util\JsonSerializable;
use PieCrust\Util\JsonSerializer;


/**
 * A class that keeps track of what's been baked
 * by the PieCrustBaker.
 */
class BakeRecord implements JsonSerializable
{
    const VERSION = 1;

    protected $pieCrust;

    protected $appVersion;
    protected $recordVersion;
    protected $rootPath;
    protected $bakeTime;

    protected $pageEntries;
    protected $assetEntries;

    protected $observers;

    /**
     * Gets the time at which the bake ended.
     */
    public function getBakeTime()
    {
        return $this->bakeTime;
    }

    /**
     * Creates a new instance of the BakeRecord.
     */
    public function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->pageEntries = array();
        $this->assetEntries = array();
        $this->observers = array();

        $this->jsonDeserialize(array());
    }

    /**
     * Loads the bake record from the given path.
     */
    public function load($pathOrData)
    {
        if (is_array($pathOrData))
        {
            // Loading from formatted data (mostly for unit tests).
            $this->jsonDeserialize($pathOrData);

            return true;
        }
        elseif (is_file($pathOrData))
        {
            // Loading from a file.
            $log = $this->pieCrust->getEnvironment()->getLog();
            $log->debug("Loading bake record from: {$pathOrData}");

            JsonSerializer::deserializeInto($this, $pathOrData);

            $entryCount = count($this->pageEntries) + count($this->assetEntries);
            $log->debug("Got {$entryCount} entries.");

            return true;
        }
        return false;
    }

    /**
     * Saves the current bake record to disk.
     */
    public function save($path)
    {
        $entryCount = count($this->pageEntries) + count($this->assetEntries);
        $log = $this->pieCrust->getEnvironment()->getLog();
        $log->debug("Saving bake record with {$entryCount} entries to: {$path}");

        JsonSerializer::serialize($this, $path);
    }

    /**
     * Adds an observer, to be notified when entries are added.
     */
    public function addObserver($observer)
    {
        $this->observers[] = $observer;
    }
    
    /**
     * Gets whether the version of the bake record matches the
     * current application.
     */
    public function isVersionMatch()
    {
        return (
            $this->appVersion == PieCrustDefaults::VERSION &&
            $this->recordVersion == self::VERSION &&
            $this->rootPath == $this->pieCrust->getRootDir()
        );
    }

    /**
     * Adds a page entry to the record.
     */
    public function addPageEntry(IPage $page, $pageBaker = null)
    {
        $entry = new BakeRecordPageEntry();
        $entry->initialize($page, $pageBaker);
        $this->pageEntries[] = $entry;
        foreach ($this->observers as $obs)
        {
            $obs->onBakeRecordPageEntryAdded($entry);
        }
    }

    /**
     * Adds asset entries to the record.
     */
    public function addAssetEntries($bakeInfos)
    {
        foreach ($bakeInfos as $path => $bakeInfo)
        {
            $entry = new BakeRecordAssetEntry();
            $entry->initialize($path, $bakeInfo);
            $this->assetEntries[] = $entry;
            foreach ($this->observers as $obs)
            {
                $obs->onBakeRecordAssetEntryAdded($entry);
            }
        }
    }

    /**
     * Gets all the page entries in this bake record.
     */
    public function getPageEntries()
    {
        return $this->pageEntries;
    }

    /**
     * Gets all the asset entries in this bake record.
     */
    public function getAssetEntries()
    {
        return $this->assetEntries;
    }

    public function jsonSerialize()
    {
        return array(
            'appVersion' => PieCrustDefaults::VERSION,
            'recordVersion' => self::VERSION,
            'rootPath' => $this->pieCrust->getRootDir(),
            'bakeTime' => time(),
            'pageEntries' => JsonSerializer::serializeArray($this->pageEntries),
            'assetEntries' => JsonSerializer::serializeArray($this->assetEntries)
        );
    }
    
    public function jsonDeserialize($data)
    {
        $bakeInfo = array(
            'appVersion' => false,
            'recordVersion' => false,
            'rootPath' => false,
            'bakeTime' => false,
            'pageEntries' => array(),
            'assetEntries' => array()
        );
        if ($data)
        {
            $bakeInfo = array_merge($bakeInfo, $data);
        }

        $this->appVersion = $bakeInfo['appVersion'];
        $this->recordVersion = $bakeInfo['recordVersion'];
        $this->rootPath = $bakeInfo['rootPath'];
        $this->bakeTime = $bakeInfo['bakeTime'];
        $this->pageEntries = JsonSerializer::deserializeArray(
            $bakeInfo['pageEntries'],
            '\PieCrust\Baker\BakeRecordPageEntry'
        );
        $this->assetEntries = JsonSerializer::deserializeArray(
            $bakeInfo['assetEntries'],
            '\PieCrust\Baker\BakeRecordAssetEntry'
        );
    }
}

