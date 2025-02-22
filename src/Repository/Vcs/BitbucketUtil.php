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

/**
 * Helper for bitbucket VCS driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class BitbucketUtil
{
    /**
     * Get composer information.
     *
     * @param Cache $cache The cache
     * @param array $infoCache The code cache
     * @param string $scheme The scheme
     * @param array $repoConfig The repository config
     * @param string $identifier The identifier
     * @param string $owner The owner of repository
     * @param string $repository The repository name
     * @param VcsDriverInterface $driver The vcs driver
     * @param string $method The method of vcs driver for get contents
     *
     * @return array|null The composer
     * @throws \Seld\JsonLint\ParsingException
     */
    public static function getComposerInformation(
        Cache              $cache,
        array              &$infoCache,
        string             $scheme,
        array              $repoConfig,
        string             $identifier,
        string             $owner,
        string             $repository,
        VcsDriverInterface $driver,
        string             $method = 'getContents'
    ): ?array
    {
        $infoCache[$identifier] = Util::readCache($infoCache, $cache, $repoConfig['asset-type'], $identifier);

        if (!isset($infoCache[$identifier])) {
            $resource = static::getUrlResource($scheme, $repoConfig, $identifier, $owner, $repository, $driver);
            $composer = static::getComposerContent($resource, $identifier, $scheme, $owner, $repository, $driver, $method);

            Util::writeCache($cache, $repoConfig['asset-type'], $identifier, $composer);
            $infoCache[$identifier] = $composer;
        }

        return $infoCache[$identifier];
    }

    /**
     * Get the url of resource.
     *
     * @param string $scheme The scheme
     * @param array $repoConfig The repository config
     * @param string $identifier The identifier
     * @param string $owner The owner of repository
     * @param string $repository The repository name
     * @param VcsDriverInterface $driver The vcs driver
     *
     * @return string
     */
    protected static function getUrlResource(
        string             $scheme,
        array              $repoConfig,
        string             $identifier,
        string             $owner,
        string             $repository,
        VcsDriverInterface $driver
    ): string
    {
        if (!str_contains(\get_class($driver), 'Git')) {
            return "$scheme://bitbucket.org/$owner/$repository/raw/$identifier/{$repoConfig['filename']}";
        }


        return "$scheme://api.bitbucket.org/1.0/repositories/$owner/$repository/src/$identifier/{$repoConfig['filename']}";
    }

    /**
     * Gets content of composer information.
     *
     * @param string $resource The resource
     * @param string $identifier The identifier
     * @param string $scheme The scheme
     * @param string $owner The owner
     * @param string $repository The repository
     * @param VcsDriverInterface $driver The vcs driver
     * @param string $method The method for get content
     *
     * @return array
     * @throws \Seld\JsonLint\ParsingException
     */
    protected static function getComposerContent(
        string             $resource,
        string             $identifier,
        string             $scheme,
        string             $owner,
        string             $repository,
        VcsDriverInterface $driver,
        string             $method
    ): array
    {
        $composer = static::getComposerContentOfFile($resource, $driver, $method);

        if (false !== $composer) {
            $composer = (array)JsonFile::parseJson((string)$composer, $resource);

            return static::formatComposerContent($composer, $identifier, $scheme, $owner, $repository, $driver, $method);
        }

        return ['_nonexistent_package' => true];
    }

    /**
     * Get the parsed content of composer.
     *
     * @param string $resource The resource
     * @param VcsDriverInterface $driver The vcs driver
     * @param string $method The method for get content
     *
     * @return false|string
     */
    protected static function getComposerContentOfFile(string $resource, VcsDriverInterface $driver, string $method): string|false
    {
        try {
            $ref = new \ReflectionClass($driver);
            $meth = $ref->getMethod($method);
            $meth->setAccessible(true);
            $composer = $meth->invoke($driver, $resource);

            if ('getContents' !== $method) {
                $file = (array)JsonFile::parseJson((string)$composer, $resource);
                $composer = empty($file) || !\array_key_exists('data', $file)
                    ? false : $file['data'];
            }
        } catch (\Exception) {
            $composer = false;
        }

        return $composer;
    }

    /**
     * Format composer content.
     *
     * @param array $composer The composer
     * @param string $identifier The identifier
     * @param string $scheme The scheme
     * @param string $owner The owner
     * @param string $repository The repository
     * @param VcsDriverInterface $driver The vcs driver
     * @param string $method The method for get content
     *
     * @return array
     */
    protected static function formatComposerContent(
        array              $composer,
        string             $identifier,
        string             $scheme,
        string             $owner,
        string             $repository,
        VcsDriverInterface $driver,
        string             $method
    ): array
    {
        $resource = "$scheme://api.bitbucket.org/1.0/repositories/$owner/$repository/changesets/$identifier";

        return Util::addComposerTime($composer, 'timestamp', $resource, $driver, $method);
    }
}
