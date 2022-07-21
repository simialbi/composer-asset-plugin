<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository\Vcs;

use Composer\Cache;
use Composer\Json\JsonFile;
use Composer\Repository\Vcs\VcsDriverInterface;
use Composer\Util\Http\Response;

/**
 * Helper for VCS driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class Util
{
    /**
     * Check if the identifier is an SHA.
     *
     * @param string $identifier The identifier
     *
     * @return bool
     */
    public static function isSha(string $identifier): bool
    {
        return (bool)preg_match('{[a-f0-9]{40}}i', $identifier);
    }

    /**
     * Read from cache.
     *
     * @param array $cacheCode The cache code
     * @param Cache $cache The cache filesystem
     * @param string $type The asset type
     * @param string $identifier The identifier
     * @param bool $force Force the read
     *
     * @return null|array
     * @throws \Seld\JsonLint\ParsingException
     */
    public static function readCache(array $cacheCode, Cache $cache, string $type, string $identifier, bool $force = false): ?array
    {
        if (\array_key_exists($identifier, $cacheCode)) {
            return $cacheCode[$identifier];
        }

        $data = null;
        if (self::isSha($identifier) || $force) {
            $res = $cache->read($type . '-' . $identifier);

            if ($res) {
                $data = JsonFile::parseJson($res);
            }
        }

        return $data;
    }

    /**
     * Write to cache
     *
     * @param Cache $cache The cache
     * @param string $type The asset type
     * @param string $identifier The identifier
     * @param array $composer The data composer
     * @param bool $force Force the write
     *
     * @return void
     * @throws
     */
    public static function writeCache(Cache $cache, string $type, string $identifier, array $composer, bool $force = false): void
    {
        if (self::isSha($identifier) || $force) {
            $cache->write($type . '-' . $identifier, json_encode($composer));
        }
    }

    /**
     * Add time in composer.
     *
     * @param array $composer The composer
     * @param string $resourceKey The composer key
     * @param string $resource The resource url
     * @param VcsDriverInterface $driver The vcs driver
     * @param string $method The method for get content
     *
     * @return array|null The composer
     * @throws \ReflectionException
     * @throws \Seld\JsonLint\ParsingException
     */
    public static function addComposerTime(
        array $composer,
        string $resourceKey,
        string $resource,
        VcsDriverInterface $driver,
        string $method = 'getContents'
    ): ?array
    {
        if (!isset($composer['time'])) {
            $ref = new \ReflectionClass($driver);
            $meth = $ref->getMethod($method);
            $meth->setAccessible(true);

            /** @var Response $response */
            $response = $meth->invoke($driver, $resource);

            $commit = $response->decodeJson();
            $keys = explode('.', $resourceKey);

            while (!empty($keys)) {
                $commit = $commit[$keys[0]];
                array_shift($keys);
            }

            $composer['time'] = $commit;
        }

        return $composer;
    }
}
