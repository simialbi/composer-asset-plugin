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
 * Utils for semver range converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class SemverRangeUtil
{
    /**
     * Replaces the special range "^".
     *
     * @param SemverConverter $converter The semver converter
     * @param string $match The match version
     *
     * @return string the new match version
     */
    public static function replaceSpecialRange(SemverConverter $converter, string $match): string
    {
        $newMatch = $converter->convertVersion($match);
        $newMatch = '>=' . static::standardizeVersion(SemverUtil::replaceAlias($newMatch, '>')) . ',<';
        $exp = static::getSplitVersion($match);
        $increase = false;

        foreach ($exp as $i => $sub) {
            if (static::analyzeSubVersion($i, $exp, $increase)) {
                continue;
            }

            static::increaseSubVersion($i, $exp, $increase);
        }

        $newMatch .= $converter->convertVersion(static::standardizeVersion($exp));

        return $newMatch;
    }

    /**
     * Analyze the sub version of split version.
     *
     * @param int $i The position in split version
     * @param array $exp The split version
     * @param bool $increase Check if the next sub version must be increased
     *
     * @return bool
     */
    protected static function analyzeSubVersion(int $i, array &$exp, bool &$increase): bool
    {
        $analyzed = false;

        if ($increase) {
            $exp[$i] = 0;
            $analyzed = true;
        }

        if (0 === $i && (int)$exp[$i] > 0) {
            $increase = true;
            $exp[$i] = (int)$exp[$i] + 1;
            $analyzed = true;
        }

        return $analyzed;
    }

    /**
     * Increase the sub version of split version.
     *
     * @param int $i The position in split version
     * @param array $exp The split version
     * @param bool $increase Check if the next sub version must be increased
     */
    protected static function increaseSubVersion($i, array &$exp, bool &$increase): void
    {
        $iNext = min($i + 1, 3, \count($exp) - 1);

        if (($iNext !== $i && ($exp[$i] > 0 || (int)$exp[$iNext] > 9999998)) || $iNext === $i) {
            $exp[$i] = (int)$exp[$i] + 1;
            $increase = true;
        }
    }

    /**
     * Standardize the version.
     *
     * @param array|string $version The version or the split version
     *
     * @return string
     */
    protected static function standardizeVersion(array|string $version): string
    {
        if (\is_string($version)) {
            $version = explode('.', $version);
        }

        while (\count($version) < 3) {
            $version[] = '0';
        }

        return implode('.', $version);
    }

    /**
     * Split the version.
     *
     * @param string $version
     *
     * @return array
     */
    protected static function getSplitVersion(string $version): array
    {
        $version = static::cleanExtraVersion($version);
        $version = str_replace(['*', 'x', 'X'], '9999999', $version);

        return explode('.', $version);
    }

    /**
     * Remove the extra information of the version (info after "-").
     *
     * @param string $version
     *
     * @return string
     */
    protected static function cleanExtraVersion(string $version): string
    {
        $pos = strpos($version, '-');

        if (false !== $pos) {
            $version = substr($version, 0, $pos);
        }

        return $version;
    }
}
