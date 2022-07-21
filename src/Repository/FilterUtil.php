<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Semver\Constraint\ConstraintInterface;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;

/**
 * Helper for Filter Package of Repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class FilterUtil
{
    /**
     * Get the link constraint of normalized version.
     *
     * @param string $normalizedVersion The normalized version
     * @param VersionParser $versionParser The version parser
     *
     * @return ConstraintInterface The constraint
     */
    public static function getVersionConstraint(string $normalizedVersion, VersionParser $versionParser): ConstraintInterface
    {
        if (preg_match('/^\d+(\.\d+)(\.\d+)(\.\d+)-[A-Za-z0-9]+$/', $normalizedVersion)) {
            $normalizedVersion = substr($normalizedVersion, 0, strpos($normalizedVersion, '-'));
        }

        return $versionParser->parseConstraints($normalizedVersion);
    }

    /**
     * Find the stability name with the stability value.
     *
     * @param int $level The stability level
     *
     * @return string The stability name
     */
    public static function findFlagStabilityName(int $level): string
    {
        $stability = 'dev';

        /** @var string $stabilityName */
        foreach (Package::$stabilities as $stabilityName => $stabilityLevel) {
            if ($stabilityLevel === $level) {
                $stability = $stabilityName;

                break;
            }
        }

        return $stability;
    }

    /**
     * Find the lowest stability.
     *
     * @param string[] $stability The list of stability
     * @param VersionParser $versionParser The version parser
     *
     * @return string The lowest stability
     */
    public static function findInlineStability(array $stability, VersionParser $versionParser): string
    {
        $lowestStability = 'stable';

        foreach ($stability as $s) {
            $s = $versionParser->normalizeStability($s);
            $s = $versionParser->parseStability($s);

            if (Package::$stabilities[$s] > Package::$stabilities[$lowestStability]) {
                $lowestStability = $s;
            }
        }

        return $lowestStability;
    }

    /**
     * Get the minimum stability for the require dependency defined in root package.
     *
     * @param RootPackageInterface $package The root package
     * @param Link $require The require link defined in root package
     *
     * @return string The minimum stability defined in root package (in links or global project)
     */
    public static function getMinimumStabilityFlag(RootPackageInterface $package, Link $require): string
    {
        $flags = $package->getStabilityFlags();

        if (isset($flags[$require->getTarget()])) {
            return static::findFlagStabilityName($flags[$require->getTarget()]);
        }

        return $package->getPreferStable() ? 'stable' : $package->getMinimumStability();
    }

    /**
     * Check the config option.
     *
     * @param Config $config The plugin config
     * @param string $name The extra option name
     *
     * @return bool
     */
    public static function checkConfigOption(Config $config, string $name): bool
    {
        return true === $config->get($name, true);
    }
}
