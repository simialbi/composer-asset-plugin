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

use Fxp\Composer\AssetPlugin\Assets;

/**
 * Tests of assets factory.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class AssetsTest extends \PHPUnit\Framework\TestCase
{
    public function testGetTypes()
    {
        self::assertEquals([
            'npm',
            'bower',
            'bower-artifactory'
        ], Assets::getTypes());
    }

    public function testDefaultGetRegistries()
    {
        self::assertEquals([
            'npm',
            'bower'
        ], array_keys(Assets::getDefaultRegistries()));
    }

    public function testGetVcsRepositoryDrivers()
    {
        self::assertEquals([
            'vcs',
            'github',
            'git-bitbucket',
            'git',
            'hg-bitbucket',
            'hg',
            'perforce',
            'svn',
            'url'
        ], array_keys(Assets::getVcsRepositoryDrivers()));
    }

    public function testGetVcsDrivers()
    {
        self::assertEquals([
            'github',
            'git-bitbucket',
            'git',
            'hg',
            'perforce',
            'url',
            'svn'
        ], array_keys(Assets::getVcsDrivers()));
    }

    public function testCreationOfInvalidType()
    {
        self::expectError();
        Assets::createType(null);
    }

    public function testCreationOfNpmAsset()
    {
        $type = Assets::createType('npm');

        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Type\AssetTypeInterface', $type);
    }

    public function testCreationOfBowerAsset()
    {
        $type = Assets::createType('bower');

        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Type\AssetTypeInterface', $type);
    }

    public function testCreationOfPrivateBowerAsset()
    {
        $type = Assets::createType('bower');

        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Type\AssetTypeInterface', $type);
    }
}
