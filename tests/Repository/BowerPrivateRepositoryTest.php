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
use Composer\Semver\Constraint\Constraint;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Repository\AbstractAssetsRepository;
use Fxp\Composer\AssetPlugin\Repository\BowerPrivateRepository;

/**
 * Tests of Private Bower repository.
 *
 * @author Marcus Stüben <marcus@it-stueben.de>
 *
 * @internal
 */
final class BowerPrivateRepositoryTest extends AbstractAssetsRepositoryTest
{
    public function testWhatProvidesWithInvalidPrivateUrl()
    {
        self::expectException('\Fxp\Composer\AssetPlugin\Exception\InvalidCreateRepositoryException');
        self::expectExceptionMessage('The "repository.url" parameter of "existing" bower asset package must be present for create a VCS Repository');
        $name = $this->getType() . '-asset/existing';
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(self::any())
            ->method('getContents')
            ->willReturn(json_encode([]));

        $this->registry->loadPackages([$name => new Constraint('=', '0.1.0')], [], []);
    }

    protected function getType(): string
    {
        return 'bower';
    }

    protected function getRegistry(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, EventDispatcher $eventDispatcher = null): AbstractAssetsRepository
    {
        return new BowerPrivateRepository($repoConfig, $io, $config, $httpDownloader, $eventDispatcher);
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

    protected function getCustomRepoConfig(): array
    {
        return [
            'private-registry-url' => 'http://foo.tld'
        ];
    }
}
