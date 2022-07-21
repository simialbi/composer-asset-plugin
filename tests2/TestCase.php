<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests;

use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Composer\Util\Filesystem;
use Composer\Util\Silencer;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Base of tests.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private static VersionParser $parser;
    private static array $executableCache = [];

    public static function getUniqueTmpDirectory(): bool|string
    {
        $attempts = 5;
        $root = sys_get_temp_dir();

        do {
            $unique = $root . \DIRECTORY_SEPARATOR . uniqid('composer-test-' . rand(1000, 9000));

            if (!file_exists($unique) && Silencer::call('mkdir', $unique, 0777)) {
                return realpath($unique);
            }
        } while (--$attempts);

        throw new \RuntimeException('Failed to create a unique temporary directory.');
    }

    protected static function getVersionParser(): VersionParser
    {
        if (!self::$parser) {
            self::$parser = new VersionParser();
        }

        return self::$parser;
    }

    protected function getVersionConstraint(string $operator, string $version): Constraint
    {
        $constraint = new Constraint(
            $operator,
            self::getVersionParser()->normalize($version)
        );

        $constraint->setPrettyString($operator . ' ' . $version);

        return $constraint;
    }

    protected function getPackage(string $name, string $version, string $class = 'Composer\Package\Package')
    {
        $normVersion = self::getVersionParser()->normalize($version);

        return new $class($name, $normVersion, $version);
    }

    protected function getAliasPackage(BasePackage $package, string $version): AliasPackage
    {
        $normVersion = self::getVersionParser()->normalize($version);

        return new AliasPackage($package, $normVersion, $version);
    }

    protected static function ensureDirectoryExistsAndClear(string $directory)
    {
        $fs = new Filesystem();

        if (is_dir($directory)) {
            $fs->removeDirectory($directory);
        }

        mkdir($directory, 0777, true);
    }

    /**
     * Check whether or not the given name is an available executable.
     *
     * @param string $executableName the name of the binary to test
     */
    protected function skipIfNotExecutable(string $executableName)
    {
        if (!isset(self::$executableCache[$executableName])) {
            $finder = new ExecutableFinder();
            self::$executableCache[$executableName] = (bool)$finder->find($executableName);
        }

        if (false === self::$executableCache[$executableName]) {
            static::markTestSkipped($executableName . ' is not found or not executable.');
        }
    }
}
