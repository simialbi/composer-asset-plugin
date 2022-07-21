<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Repository;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Repository\AbstractAssetsRepository;
use Fxp\Composer\AssetPlugin\Repository\BowerRepository;

/**
 * Tests of Bower repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class BowerRepositoryTest extends AbstractAssetsRepositoryTest
{
    protected function getType(): string
    {
        return 'bower';
    }

    protected function getRegistry(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, EventDispatcher $eventDispatcher = null): AbstractAssetsRepository
    {
        return new BowerRepository($repoConfig, $io, $config, $httpDownloader, $eventDispatcher);
    }

    protected function getMockPackageForVcsConfig(): array
    {
        return [
            'url' => 'http://foo.tld'
        ];
    }

    protected function getMockSearchResult(string $name = 'mock-package'): array
    {
        return [
            [
                'name' => $name
            ]
        ];
    }
}
