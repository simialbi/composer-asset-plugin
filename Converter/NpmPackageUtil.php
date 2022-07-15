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

/**
 * Utils for NPM package converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class NpmPackageUtil
{
    /**
     * Convert the npm package name.
     *
     * @param string $name The npm package name
     *
     * @return string
     */
    public static function convertName(string $name): string
    {
        if (str_starts_with($name, '@') && str_contains($name, '/')) {
            $name = ltrim(str_replace('/', '--', $name), '@');
        }

        return $name;
    }

    /**
     * Revert the npm package name from composer package name.
     *
     * @param string $name The npm package name
     *
     * @return string
     */
    public static function revertName(string $name): string
    {
        if (str_contains($name, '--')) {
            $name = '@' . str_replace('--', '/', $name);
        }

        return $name;
    }

    /**
     * Convert the npm licenses list.
     *
     * @param array|string $licenses The npm package licenses list
     *
     * @return array|string
     */
    public static function convertLicenses(array|string $licenses): array|string
    {
        if (!\is_array($licenses)) {
            return $licenses;
        }

        $result = [];
        foreach ($licenses as $license) {
            if (\is_array($license)) {
                if (!empty($license['type'])) {
                    $result[] = $license['type'];
                } elseif (!empty($license['name'])) {
                    $result[] = $license['name'];
                }
            } else {
                $result[] = $license;
            }
        }

        return $result;
    }

    /**
     * Convert the author section.
     *
     * @param string|null $value The current value
     *
     * @return array|null
     */
    public static function convertAuthor(?string $value): array|null
    {
        if (null !== $value) {
            $value = [$value];
        }

        return $value;
    }

    /**
     * Convert the contributors section.
     *
     * @param array|string|null $value The current value
     * @param array|string|null $prevValue The previous value
     *
     * @return array
     */
    public static function convertContributors(array|string|null $value, array|string|null $prevValue): array|string|null
    {
        $mergeValue = \is_array($prevValue) ? $prevValue : [];
        $mergeValue = array_merge($mergeValue, \is_array($value) ? $value : []);

        if (\count($mergeValue) > 0) {
            $value = $mergeValue;
        }

        return $value;
    }

    /**
     * Convert the dist section.
     *
     * @param array|string|null $value The current value
     *
     * @return array|string|null
     */
    public static function convertDist(array|string|null $value): array|string|null
    {
        if (\is_array($value)) {
            $data = $value;
            $value = [];

            foreach ($data as $type => $url) {
                if (\is_string($url)) {
                    self::convertDistEntry($value, $type, $url);
                }
            }
        }

        return $value;
    }

    /**
     * Convert the each entry of dist section.
     *
     * @param array $value The result
     * @param string $type The dist type
     * @param string $url The dist url
     */
    private static function convertDistEntry(array &$value, string $type, string $url): void
    {
        $httpPrefix = 'http://';

        if (str_starts_with($url, $httpPrefix)) {
            $url = 'https://' . substr($url, \strlen($httpPrefix));
        }

        if ('shasum' === $type) {
            $value[$type] = $url;
        } elseif ('tarball' === $type) {
            $value['type'] = 'tar';
            $value['url'] = $url;
        } elseif (\in_array($type, self::getDownloaderTypes(), true)) {
            $value['type'] = $type;
            $value['url'] = $url;
        }
    }

    /**
     * Get downloader types in Composer.
     *
     * @return string[]
     */
    private static function getDownloaderTypes(): array
    {
        return ['git', 'svn', 'fossil', 'hg', 'perforce', 'zip', 'rar', 'tar', 'gzip', 'xz', 'phar', 'file', 'path'];
    }
}
