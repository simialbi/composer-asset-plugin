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

use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;

/**
 * Utils for semver converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class SemverUtil
{
    /**
     * @var string[]
     */
    private static array $cleanPatterns = [
        '-npm-packages',
        '-bower-packages',
    ];

    /**
     * Replace the alias version (x or *) by integer.
     *
     * @param string $version
     * @param string $type
     *
     * @return string
     */
    public static function replaceAlias(string $version, string $type): string
    {
        $value = '>' === $type ? '0' : '9999999';

        return str_replace(['x', '*'], $value, $version);
    }

    /**
     * Converts the date or datetime version.
     *
     * @param string $version The version
     *
     * @return string
     */
    public static function convertDateVersion(string $version): string
    {
        if (preg_match('/^\d{7,}\./', $version)) {
            $pos = strpos($version, '.');
            $version = substr($version, 0, $pos) . self::convertDateMinorVersion(substr($version, $pos + 1));
        }

        return $version;
    }

    /**
     * Converts the version metadata.
     *
     * @param string $version
     *
     * @return string
     */
    public static function convertVersionMetadata(string $version): string
    {
        $version = str_replace(self::$cleanPatterns, '', $version);

        if (preg_match_all(
            self::createPattern('([a-zA-Z]+|(\-|\+)[a-zA-Z]+|(\-|\+)[0-9]+)'),
            $version,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            [$type, $version, $end] = self::cleanVersion(strtolower($version), $matches);
            [$version, $patchVersion] = self::matchVersion($version, $type);

            $matches = [];
            $hasPatchNumber = preg_match('/[0-9]+\.[0-9]+|[0-9]+|\.[0-9]+$/', $end, $matches);
            $end = $hasPatchNumber ? $matches[0] : '1';

            if ($patchVersion) {
                $version .= $end;
            }
        }

        return static::cleanWildcard($version);
    }

    /**
     * Creates a pattern with the version prefix pattern.
     *
     * @param string $pattern The pattern without '/'
     *
     * @return string The full pattern with '/'
     */
    public static function createPattern(string $pattern): string
    {
        $numVer = '([0-9]+|x|\*)';
        $numVer2 = '(' . $numVer . '\.' . $numVer . ')';
        $numVer3 = '(' . $numVer . '\.' . $numVer . '\.' . $numVer . ')';

        return '/^' . '(' . $numVer . '|' . $numVer2 . '|' . $numVer3 . ')' . $pattern . '/';
    }

    /**
     * Clean the wildcard in version.
     *
     * @param string $version The version
     *
     * @return string The cleaned version
     */
    protected static function cleanWildcard(string $version): string
    {
        while (str_contains($version, '.x.x')) {
            $version = str_replace('.x.x', '.x', $version);
        }

        return $version;
    }

    /**
     * Clean the raw version.
     *
     * @param string $version The version
     * @param array $matches The match of pattern asset version
     *
     * @return array The list of $type, $version and $end
     */
    protected static function cleanVersion(string $version, array $matches): array
    {
        $end = substr($version, \strlen($matches[1][0][0]));
        $version = $matches[1][0][0] . '-';

        $matches = [];
        if (preg_match('/^([-+])/', $end, $matches)) {
            $end = substr($end, 1);
        }

        $matches = [];
        preg_match('/^[a-z]+/', $end, $matches);
        $type = isset($matches[0]) ? VersionParser::normalizeStability($matches[0]) : '';
        $end = substr($end, \strlen($type));

        return [$type, $version, $end];
    }

    /**
     * Match the version.
     *
     * @param string $version
     * @param string $type
     *
     * @return array The list of $version and $patchVersion
     */
    protected static function matchVersion(string $version, string $type): array
    {
        $patchVersion = true;

        if (\in_array($type, ['dev', 'snapshot'], true)) {
            $type = 'dev';
            $patchVersion = false;
        } elseif ('a' === $type) {
            $type = 'alpha';
        } elseif (\in_array($type, ['b', 'pre'], true)) {
            $type = 'beta';
        } elseif (!\in_array($type, ['alpha', 'beta', 'RC'], true)) {
            $type = 'patch';
        }

        $version .= $type;

        return [$version, $patchVersion];
    }

    /**
     * Convert the minor version of date.
     *
     * @param string $minor The minor version
     *
     * @return string
     */
    protected static function convertDateMinorVersion(string $minor): string
    {
        $split = explode('.', $minor);
        $minor = (int)$split[0];
        $revision = isset($split[1]) ? (int)$split[1] : 0;

        return '.' . sprintf('%03d', $minor) . sprintf('%03d', $revision);
    }
}
