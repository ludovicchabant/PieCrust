<?php

namespace PieCrust\Util;

use PieCrust\PieCrustException;


/**
 * A helper class for manipulating archive files.
 */
class ArchiveHelper
{
    public static function isSupported($archiveType)
    {
        switch ($archiveType)
        {
        case 'zip':
            return function_exists('zip_open');
        default:
            throw new PieCrustException("Unsupported archive type: {$archiveType}");
        }
    }

    public static function throwIfNotSupported($archiveType)
    {
        if (!self::isSupported($archiveType))
            throw new PieCrustException("'{$archiveType}' archives are not supported by your PHP install. You need the proper extension.");
    }

    public static function getArchiveType($archiveFile)
    {
        $extension = pathinfo($archiveFile, PATHINFO_EXTENSION);
        return $extension;
    }

    public static function extractArchive($archiveFile, $destination, $logger = null)
    {
        switch (self::getArchiveType($archiveFile))
        {
        case 'zip':
            self::unzip($archiveFile, $destination, $logger);
            break;
        default:
            throw new PieCrustException("Unsupported archive type: {$archiveType}");
        }
    }

    public static function unzip($zipfile, $destination, $logger = null)
    {
        $destination = rtrim($destination, '/\\') . '/';

        $zip = zip_open($zipfile);
        if (!is_resource($zip))
            throw new PieCrustException("Error opening ZIP file: {$zipfile}");
        while ($entry = zip_read($zip))
        {
            zip_entry_open($zip, $entry);
            $entryName = zip_entry_name($entry);
            if ($logger)
                $logger->debug("Extracting {$entryName}...");

            $path = $destination . $entryName;
            $contents = zip_entry_read(
                $entry,
                zip_entry_filesize($entry)
            );
            PathHelper::ensureDirectory(dirname($path));
            file_put_contents($path, $contents);
            zip_entry_close($entry);
        }
        zip_close($zip);
    }
}

