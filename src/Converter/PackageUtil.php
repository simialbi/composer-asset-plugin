<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Converter;

use Composer\Config;
use Composer\IO\NullIO;
use Composer\Repository\Vcs\VcsDriverInterface;
use Fxp\Composer\AssetPlugin\Assets;
use Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;
use Fxp\Composer\AssetPlugin\Util\Validator;

/**
 * Utils for package converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class PackageUtil
{
    /**
     * @var string[]
     */
    private static array $extensions = [
        '.zip',
        '.tar',
        '.tar.gz',
        '.tar.bz2',
        '.tar.Z',
        '.tar.xz',
        '.bz2',
        '.gz',
    ];

    /**
     * Checks if the version is a URL version.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param string $dependency The dependency
     * @param string $version The version
     * @param array $vcsRepos The list of new vcs configs
     * @param array $composer The partial composer data
     *
     * @return string[] The new dependency and the new version
     */
    public static function checkUrlVersion(AssetTypeInterface $assetType, string $dependency, string $version, array &$vcsRepos, array $composer): array
    {
        if (preg_match('/(:\/\/)|@/', $version)) {
            [$url, $version] = static::splitUrlVersion($version);

            if (!static::isUrlArchive($url) && static::hasUrlDependencySupported($url)) {
                $vcsRepos[] = [
                    'type' => sprintf('%s-vcs', $assetType->getName()),
                    'url' => $url,
                    'name' => $assetType->formatComposerName($dependency)
                ];
            } else {
                $dependency = static::getUrlFileDependencyName($assetType, $composer, $dependency);
                $vcsRepos[] = [
                    'type' => 'package',
                    'package' => [
                        'name' => $assetType->formatComposerName($dependency),
                        'type' => $assetType->getComposerType(),
                        'version' => static::getUrlFileDependencyVersion($assetType, $url, $version),
                        'dist' => [
                            'url' => $url,
                            'type' => 'file',
                        ]
                    ]
                ];
            }
        }

        return [$dependency, $version];
    }

    /**
     * Check if the url is a url of a archive file.
     *
     * @param string $url The url
     *
     * @return bool
     */
    public static function isUrlArchive(string $url): bool
    {
        if (str_starts_with($url, 'http')) {
            foreach (self::$extensions as $extension) {
                if (str_ends_with($url, $extension)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if the version is a alias version.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param string $dependency The dependency
     * @param string $version The version
     *
     * @return string[] The new dependency and the new version
     */
    public static function checkAliasVersion(AssetTypeInterface $assetType, string $dependency, string $version): array
    {
        $pos = strpos($version, '#');

        if ($pos > 0 && !preg_match('{[0-9a-f]{40}$}', $version)) {
            $dependency = substr($version, 0, $pos);
            $version = substr($version, $pos);
            $searchVersion = substr($version, 1);

            if (!str_contains($version, '*') && Validator::validateTag($searchVersion, $assetType)) {
                $dependency .= '-' . str_replace('#', '', $version);
            }
        }

        return [$dependency, $version];
    }

    /**
     * Convert the dependency version.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param string $dependency The dependency
     * @param string $version The version
     *
     * @return string[] The new dependency and the new version
     */
    public static function convertDependencyVersion(AssetTypeInterface $assetType, string $dependency, string $version): array
    {
        $containsHash = false !== strpos($version, '#');
        $version = str_replace('#', '', $version);
        $version = empty($version) ? '*' : trim($version);
        $searchVersion = str_replace([' ', '<', '>', '=', '^', '~'], '', $version);

        // sha version or branch version
        // sha size: 4-40. See https://git-scm.com/book/tr/v2/Git-Tools-Revision-Selection#_short_sha_1
        if ($containsHash && preg_match('{^[0-9a-f]{4,40}$}', $version)) {
            $version = 'dev-default#' . $version;
        } elseif ('*' !== $version && !Validator::validateTag($searchVersion, $assetType) && !static::depIsRange($version)) {
            $version = static::convertBrachVersion($assetType, $version);
        }

        return [$dependency, $version];
    }

    /**
     * Converts the simple key of package.
     *
     * @param array $asset The asset data
     * @param string $assetKey The asset key
     * @param array $composer The composer data
     * @param string $composerKey The composer key
     */
    public static function convertStringKey(array $asset, string $assetKey, array &$composer, string $composerKey): void
    {
        if (isset($asset[$assetKey])) {
            $composer[$composerKey] = $asset[$assetKey];
        }
    }

    /**
     * Converts the simple key of package.
     *
     * @param array $asset The asset data
     * @param string $assetKey The asset key
     * @param array $composer The composer data
     * @param array $composerKey The array with composer key name and closure
     *
     * @throws InvalidArgumentException When the 'composerKey' argument of asset packager converter is not an string or an array with the composer key and closure
     */
    public static function convertArrayKey(array $asset, string $assetKey, array &$composer, array $composerKey): void
    {
        if (2 !== \count($composerKey)
            || (!\is_string($composerKey[0]) && !\is_array($composerKey[0])) || !$composerKey[1] instanceof \Closure) {
            throw new InvalidArgumentException('The "composerKey" argument of asset packager converter must be an string or an array with the composer key and closure');
        }

        $closure = $composerKey[1];
        $composerKey = $composerKey[0];
        if (is_array($composerKey)) {
            $data = $closure($asset[$assetKey] ?? null);
            foreach ($composerKey as $item) {
                $composer[$item] = $data[$item] ?? null;
            }
        } else {
            $data = $asset[$assetKey] ?? null;
            $previousData = $composer[$composerKey] ?? null;
            $data = $closure($data, $previousData);

            if (null !== $data) {
                $composer[$composerKey] = $data;
            }
        }
    }

    /**
     * Split the URL and version.
     *
     * @param string $version The url and version (in the same string)
     *
     * @return string[] The url and version
     */
    protected static function splitUrlVersion(string $version): array
    {
        $pos = strpos($version, '#');

        // number version or empty version
        if (false !== $pos) {
            $url = substr($version, 0, $pos);
            $version = substr($version, $pos);
        } else {
            $url = $version;
            $version = '#';
        }

        return [$url, $version];
    }

    /**
     * Get the name of url file dependency.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param array $composer The partial composer
     * @param string $dependency The dependency name
     *
     * @return string The dependency name
     */
    protected static function getUrlFileDependencyName(AssetTypeInterface $assetType, array $composer, string $dependency): string
    {
        $prefix = isset($composer['name'])
            ? substr($composer['name'], \strlen($assetType->getComposerVendorName()) + 1) . '-'
            : '';

        return $prefix . $dependency . '-file';
    }

    /**
     * Get the version of url file dependency.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param string $url The url
     * @param string $version The version
     *
     * @return string The version
     */
    protected static function getUrlFileDependencyVersion(AssetTypeInterface $assetType, string $url, string $version): string
    {
        if ('#' !== $version) {
            return substr($version, 1);
        }

        if (preg_match('/(\d+)(\.\d+)(\.\d+)?(\.\d+)?/', $url, $match)) {
            return $assetType->getVersionConverter()->convertVersion($match[0]);
        }

        return '0.0.0.0';
    }

    /**
     * Check if url is supported by vcs drivers.
     *
     * @param string $url The url
     *
     * @return bool
     */
    protected static function hasUrlDependencySupported(string $url): bool
    {
        $io = new NullIO();
        $config = new Config();

        /** @var VcsDriverInterface $driver */
        foreach (Assets::getVcsDrivers() as $driver) {
            $supported = $driver::supports($io, $config, $url);

            if ($supported) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the version of dependency is a range version.
     *
     * @param string $version
     *
     * @return bool
     */
    protected static function depIsRange(string $version): bool
    {
        $version = trim($version);

        return (bool)preg_match('/[<>=^~ ]/', $version);
    }

    /**
     * Convert the dependency branch version.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param string $version The version
     *
     * @return string
     */
    protected static function convertBrachVersion(AssetTypeInterface $assetType, string $version): string
    {
        $oldVersion = $version;
        $version = 'dev-' . $assetType->getVersionConverter()->convertVersion($version);

        if (!Validator::validateBranch($oldVersion)) {
            $version .= ' || ' . $oldVersion;
        }

        return $version;
    }
}
