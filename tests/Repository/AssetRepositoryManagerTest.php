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

use Composer\DependencyResolver\Pool;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\ResolutionManager;
use Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter;

/**
 * Tests of Asset Repository Manager.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class AssetRepositoryManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RepositoryManager
     */
    protected RepositoryManager|\PHPUnit\Framework\MockObject\MockObject $rm;

    /**
     * @var IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|VcsPackageFilter
     */
    protected \PHPUnit\Framework\MockObject\MockObject|VcsPackageFilter $filter;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ResolutionManager
     */
    protected ResolutionManager|\PHPUnit\Framework\MockObject\MockObject $resolutionManager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|HttpDownloader
     */
    protected HttpDownloader|\PHPUnit\Framework\MockObject\MockObject $httpDownloader;

    /**
     * @var AssetRepositoryManager
     */
    protected AssetRepositoryManager $assetRepositoryManager;

    protected function setUp(): void
    {
        $this->io = $this->getMockBuilder(IOInterface::class)->getMock();
        $this->rm = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $this->config = new Config([]);
        $this->httpDownloader = $this->getMockBuilder(HttpDownloader::class)->disableOriginalConstructor()->getMock();
        $this->filter = $this->getMockBuilder(VcsPackageFilter::class)->disableOriginalConstructor()->getMock();

        $this->resolutionManager = $this->getMockBuilder(ResolutionManager::class)->getMock();
        $this->assetRepositoryManager = new AssetRepositoryManager($this->io, $this->rm, $this->config, $this->httpDownloader, $this->filter);
    }

    public function getDataForSolveResolutions(): array
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @dataProvider getDataForSolveResolutions
     *
     * @param bool $withResolutionManager
     */
    public function testSolveResolutions(bool $withResolutionManager)
    {
        $expected = [
            'name' => 'foo/bar'
        ];

        if ($withResolutionManager) {
            $this->assetRepositoryManager->setResolutionManager($this->resolutionManager);
            $this->resolutionManager->expects(self::once())
                ->method('solveResolutions')
                ->with($expected)
                ->willReturn($expected);
        } else {
            $this->resolutionManager->expects(self::never())
                ->method('solveResolutions');
        }

        $data = $this->assetRepositoryManager->solveResolutions($expected);

        self::assertSame($expected, $data);
    }

    public function testAddRepositoryInPool()
    {
        $repos = [
            [
                'name' => 'foo/bar',
                'type' => 'asset-vcs',
                'url' => 'https://github.com/helloguest/helloguest-ui-app.git'
            ]
        ];

        $repoConfigExpected = array_merge($repos[0], [
            'asset-repository-manager' => $this->assetRepositoryManager,
            'vcs-package-filter' => $this->filter
        ]);

        $repo = $this->getMockBuilder(RepositoryInterface::class)->getMock();

        $this->rm->expects(self::once())
            ->method('createRepository')
            ->with('asset-vcs', $repoConfigExpected)
            ->willReturn($repo);

        $this->assetRepositoryManager->addRepositories($repos);

        $this->assetRepositoryManager->setPool($pool);
    }

    public function testGetConfig()
    {
        self::assertSame($this->config, $this->assetRepositoryManager->getConfig());
    }
}
