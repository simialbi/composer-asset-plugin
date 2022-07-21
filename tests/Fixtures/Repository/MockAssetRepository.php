<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;

/**
 * Fixture for assets repository tests.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockAssetRepository implements RepositoryInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        array           $repoConfig,
        IOInterface     $io,
        Config          $config,
        EventDispatcher $eventDispatcher = null
    )
    {
    }

    public function hasPackage(PackageInterface $package): bool
    {
        return false;
    }

    public function findPackage($name, $version)
    {
    }

    public function findPackages($name, $version = null): array
    {
        return [];
    }

    public function getPackages(): array
    {
        return [];
    }

    public function search($query, $mode = 0, $type = null): array
    {
        return [];
    }

    public function count(): int
    {
        return 0;
    }

    public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []): array
    {
        return [
            'namesFound' => [],
            'packages' => []
        ];
    }

    public function getProviders(string $packageName): array
    {
        return [];
    }

    public function getRepoName(): string
    {
        return 'MockAssetRepository';
    }
}
