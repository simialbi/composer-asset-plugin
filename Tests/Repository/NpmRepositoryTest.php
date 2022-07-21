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
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Repository\AbstractAssetsRepository;
use Fxp\Composer\AssetPlugin\Repository\NpmRepository;

/**
 * Tests of NPM repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class NpmRepositoryTest extends AbstractAssetsRepositoryTest
{
    public function testWhatProvidesWithCamelcasePackageName()
    {
        $name = $this->getType() . '-asset/CamelCasePackage';
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(self::any())
            ->method('getContents')
            ->will(self::throwException(new TransportException('Package not found', 404)));

        self::assertCount(0, $this->rm->getRepositories());
        self::assertCount(0, $this->registry->loadPackages([$name => new Constraint('=', '0.1.0')], [], [])['namesFound']);
        self::assertCount(0, $this->rm->getRepositories());
    }

    public function testWatProvidesWithoutRepositoryUrl()
    {
        $name = $this->getType() . '-asset/foobar';
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(self::any())
            ->method('getContents')
            ->willReturn(json_encode([
                'repository' => [
                    'type' => 'vcs'
                ],
                'versions' => [
                    '1.0.0' => [
                        'name' => 'foobar',
                        'version' => '0.0.1',
                        'dependencies' => [],
                        'dist' => [
                            'shasum' => '1d408b3fdb76923b9543d96fb4c9dfd535d9cb5d',
                            'tarball' => 'http://registry.tld/foobar/-/foobar-1.0.0.tgz'
                        ]
                    ]
                ],
                'time' => [
                    '1.0.0' => '2016-09-20T13:48:47.730Z'
                ]
            ]));

        self::assertCount(0, $this->rm->getRepositories());
        self::assertCount(0, $this->registry->loadPackages([$name => new Constraint('=', '0.0.1')], [], [])['namesFound']);
        self::assertCount(1, $this->rm->getRepositories());
    }

    public function testWhatProvidesWithBrokenVersionConstraint()
    {
        $name = $this->getType() . '-asset/foobar';
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(self::any())
            ->method('getContents')
            ->willReturn(json_encode([
                'repository' => [
                    'type' => 'vcs',
                ],
                'versions' => [
                    '1.0.0' => [
                        'name' => 'foobar',
                        'version' => '0.0.1',
                        'dependencies' => [],
                        'dist' => [
                            'shasum' => '1d408b3fdb76923b9543d96fb4c9dfd535d9cb5d',
                            'tarball' => 'http://registry.tld/foobar/-/foobar-1.0.0.tgz',
                        ],
                    ],
                    '1.0.1' => [
                        'name' => 'foobar',
                        'version' => '0.0.1',
                        'dependencies' => [
                            // This constraint is invalid. Whole version package version should be skipped.
                            'library1' => '^1.2,,<2.0',
                        ],
                        'dist' => [
                            'shasum' => '1d408b3fdb76923b9543d96fb4c9acd535d9cb7a',
                            'tarball' => 'http://registry.tld/foobar/-/foobar-1.0.1.tgz',
                        ],
                    ],
                    '1.0.2' => [
                        'name' => 'foobar',
                        'version' => '0.0.1',
                        'dependencies' => [
                            'library1' => '^1.2,<2.0',
                        ],
                        'dist' => [
                            'shasum' => '1d408b3fdb76923b9543d96fb4c9acd535d9cb7a',
                            'tarball' => 'http://registry.tld/foobar/-/foobar-1.0.1.tgz',
                        ],
                    ],
                ],
                'time' => [
                    '1.0.0' => '2016-09-20T13:48:47.730Z',
                ],
            ]));

        self::assertCount(0, $this->rm->getRepositories());
        self::assertCount(0, $this->registry->loadPackages([$name => new Constraint('=', '0.0.1')], [], [])['namesFound']);
        self::assertCount(1, $this->rm->getRepositories());
        self::assertCount(2, $this->rm->getRepositories()[0]->getPackages());
    }

    public function testWatProvidesWithoutRepositoryUrlAndWithoutVersions()
    {
        self::expectException('\Fxp\Composer\AssetPlugin\Exception\InvalidCreateRepositoryException');
        self::expectExceptionMessage('"repository.url" parameter of "foobar"');
        $name = $this->getType() . '-asset/foobar';
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(self::any())
            ->method('getContents')
            ->willReturn(json_encode([]));

        self::assertCount(0, $this->rm->getRepositories());

        $this->registry->loadPackages([$name => new Constraint('=', '0.0.1')], [], []);
    }

    public function testWhatProvidesWithGitPlusHttpsUrl()
    {
        $name = $this->getType() . '-asset/existing';
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(self::any())
            ->method('getContents')
            ->willReturn(json_encode([
                'repository' => [
                    'type' => 'vcs',
                    'url' => 'git+https://foo.tld',
                ],
            ]));

        self::assertCount(0, $this->rm->getRepositories());
        self::assertCount(0, $this->registry->loadPackages([$name => new Constraint('=', '0.0.1')], [], [])['namesFound']);
        self::assertCount(1, $this->rm->getRepositories());
    }

    protected function getType(): string
    {
        return 'npm';
    }

    protected function getRegistry(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, EventDispatcher $eventDispatcher = null): AbstractAssetsRepository
    {
        return new NpmRepository($repoConfig, $io, $config, $httpDownloader, $eventDispatcher);
    }

    protected function getMockPackageForVcsConfig(): array
    {
        return [
            'repository' => [
                'type' => 'vcs',
                'url' => 'http://foo.tld'
            ]
        ];
    }

    protected function getMockSearchResult(string $name = 'mock-package'): array
    {
        return [];
    }
}
