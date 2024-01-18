<?php

namespace Mikroatlas\Models;

class CacheManager
{
    private const CACHE_DIR = 'cache';
    private const CACHE_FILE_EXTENSION = ''; //Using .html would work, but not for API responses using JSON

    public function generateCacheItemId(string $url, string $prefix = ''): string
    {
        $hash = crc32(trim($url, '/'));
        return $prefix.'-'.$hash;
    }

    public function checkCacheExists(string $cacheItemId)
    {
        return file_exists(self::CACHE_DIR.DIRECTORY_SEPARATOR.$cacheItemId.self::CACHE_FILE_EXTENSION);
    }

    public function getCacheFile(string $cacheItemId)
    {
        return self::CACHE_DIR.DIRECTORY_SEPARATOR.$cacheItemId.self::CACHE_FILE_EXTENSION;
    }

    public function saveCache(string $cacheItemID, string $response, bool $overwrite = true): bool
    {
        if ($this->checkCacheExists($cacheItemID) && !$overwrite) {
            return false;
        }

        $result = file_put_contents(self::CACHE_DIR.DIRECTORY_SEPARATOR.$cacheItemID.self::CACHE_FILE_EXTENSION, $response, LOCK_EX);
        return $result !== false;
    }

    public function clearCache(string $cacheItemId): bool
    {
        return unlink(self::CACHE_DIR.DIRECTORY_SEPARATOR.$cacheItemId.self::CACHE_FILE_EXTENSION);
    }

    public function purgeAllCache(): bool
    {
        $result = true;
        foreach (array_diff(scandir(self::CACHE_DIR), ['.', '..']) as $cacheFile) {
            $result = $result && unlink(self::CACHE_DIR.DIRECTORY_SEPARATOR.$cacheFile);
        }
        return $result;
    }
}
