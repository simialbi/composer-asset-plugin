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
use Composer\DependencyResolver\Pool;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Config\Config as AssetConfig;
use Fxp\Composer\AssetPlugin\Repository\AbstractAssetsRepository;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\AssetVcsRepository;
use Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Abstract class for Tests of assets repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractAssetsRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var RepositoryManager
     */
    protected RepositoryManager $rm;

    /**
     * @var AssetRepositoryManager
     */
    protected AssetRepositoryManager $assetRepositoryManager;

    /**
     * @var AbstractAssetsRepository
     */
    protected AbstractAssetsRepository $registry;

    /**
     * @var HttpDownloader
     */
    protected HttpDownloader $httpDownloader;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Pool
     */
    protected Pool|\PHPUnit\Framework\MockObject\MockObject $pool;

    protected function setUp(): void
    {
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(static::any())
            ->method('isVerbose')
            ->willReturn(true);
        /** @var IOInterface $io */
        $config = new Config();
        $config->merge([
            'config' => [
                'home' => sys_get_temp_dir() . '/composer-test',
                'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-repo-cache',
            ]
        ]);
        $this->httpDownloader = new HttpDownloader($this->io, $config);
        /** @var VcsPackageFilter $filter */
        $filter = $this->getMockBuilder(VcsPackageFilter::class)->disableOriginalConstructor()->getMock();
        $rm = new RepositoryManager($io, $config);
        $rm->setRepositoryClass($this->getType() . '-vcs', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\MockAssetRepository');
        $this->assetRepositoryManager = new AssetRepositoryManager($io, $rm, new AssetConfig([]), $this->httpDownloader, $filter);
        $repoConfig = array_merge([
            'asset-repository-manager' => $this->assetRepositoryManager,
            'asset-options' => [
                'searchable' => true,
            ]
        ], $this->getCustomRepoConfig());

        $this->io = $io;
        $this->config = $config;
        $this->rm = $rm;
        $this->registry = $this->getRegistry($repoConfig, $io, $config, $this->httpDownloader);
        $this->pool = $this->getMockBuilder('Composer\DependencyResolver\Pool')->getMock();
    }

    protected function tearDown(): void
    {
        unset($this->io, $this->config, $this->rm, $this->registry, $this->pool);

        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir() . '/composer-test-repo-cache');
        $fs->remove(sys_get_temp_dir() . '/composer-test');
    }

    public function testFindPackageMustBeAlwaysNull()
    {
        static::assertNull($this->registry->findPackage('foobar', '0'));
    }

    public function testFindPackageMustBeAlwaysEmpty()
    {
        static::assertCount(0, $this->registry->findPackages('foobar', '0'));
    }

    public function testGetPackagesNotBeUsed()
    {
        self::expectException('\LogicException');
        $this->registry->getPackages();
    }
//  TODO: What provides tests (loadPackages)
//    public function testGetProviderNamesMustBeEmpty()
//    {
//        static::assertCount(0, $this->registry->getProviderNames());
//    }

    public function testGetMinimalPackagesMustBeAlwaysEmpty()
    {
        static::assertCount(0, $this->registry->getMinimalPackages());
    }

//    public function testWhatProvidesWithNotAssetName()
//    {
//        static::assertCount(0, $this->registry->whatProvides($this->pool, 'foo/bar'));
//    }
//
//    public function testWhatProvidesWithNonExistentPackage()
//    {
//        $name = $this->getType() . '-asset/non-existent';
//        $rfs = $this->replaceRegistryRfsByMock();
//        $rfs->expects(static::any())
//            ->method('getContents')
//            ->will(static::throwException(new TransportException('Package not found')));
//
//        static::assertCount(0, $this->rm->getRepositories());
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(0, $this->rm->getRepositories());
//    }

//    public function testWhatProvidesWithExistingPackage()
//    {
//        $name = $this->getType() . '-asset/existing';
//        $rfs = $this->replaceRegistryRfsByMock();
//        $rfs->expects(static::any())
//            ->method('getContents')
//            ->willReturn(json_encode($this->getMockPackageForVcsConfig()));
//
//        static::assertCount(0, $this->rm->getRepositories());
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(1, $this->rm->getRepositories());
//    }

//    public function testWhatProvidesWithExistingAliasPackage()
//    {
//        $name = $this->getType() . '-asset/existing-1.0';
//        $rfs = $this->replaceRegistryRfsByMock();
//        $rfs->expects(static::any())
//            ->method('getContents')
//            ->willReturn(json_encode($this->getMockPackageForVcsConfig()));
//
//        static::assertCount(0, $this->rm->getRepositories());
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(1, $this->rm->getRepositories());
//    }

//    public function testWhatProvidesWithCamelcasePackageName()
//    {
//        $assetName = 'CamelCasePackage';
//        $name = $this->getType() . '-asset/' . strtolower($assetName);
//        $rfs = $this->replaceRegistryRfsByMock();
//        $rfs->expects(static::at(0))
//            ->method('getContents')
//            ->will(static::throwException(new TransportException('Package not found', 404)));
//        $rfs->expects(static::at(1))
//            ->method('getContents')
//            ->will(static::throwException(new TransportException('Package not found', 404)));
//        $rfs->expects(static::at(2))
//            ->method('getContents')
//            ->will(static::throwException(new TransportException('Package not found', 404)));
//        $rfs->expects(static::at(3))
//            ->method('getContents')
//            ->willReturn(json_encode($this->getMockSearchResult($assetName)));
//        $rfs->expects(static::at(4))
//            ->method('getContents')
//            ->willReturn(json_encode($this->getMockPackageForVcsConfig()));
//
//        static::assertCount(0, $this->rm->getRepositories());
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//        static::assertCount(1, $this->rm->getRepositories());
//    }

    public function testSearch()
    {
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(static::any())
            ->method('getContents')
            ->willReturn(json_encode($this->getMockSearchResult()));

        $result = $this->registry->search('query');
        static::assertCount(\count($this->getMockSearchResult()), $result);
    }

    public function testSearchWithAssetComposerPrefix()
    {
        $rfs = $this->replaceRegistryRfsByMock();
        $rfs->expects(static::any())
            ->method('getContents')
            ->willReturn(json_encode($this->getMockSearchResult()));

        $result = $this->registry->search($this->getType() . '-asset/query');
        static::assertCount(\count($this->getMockSearchResult()), $result);
    }

    public function testSearchWithSearchDisabled()
    {
        $repoConfig = [
            'asset-repository-manager' => $this->assetRepositoryManager,
            'asset-options' => [
                'searchable' => false
            ]
        ];
        $this->registry = $this->getRegistry($repoConfig, $this->io, $this->config, $this->httpDownloader);

        static::assertCount(0, $this->registry->search('query'));
    }

//    public function testOverridingVcsRepositoryConfig()
//    {
//        $name = $this->getType() . '-asset/foobar';
//        $rfs = $this->replaceRegistryRfsByMock();
//        $rfs->expects(static::any())
//            ->method('getContents')
//            ->willReturn(json_encode($this->getMockPackageForVcsConfig()));
//
//        $repo = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Repository\AssetVcsRepository')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $repo->expects(static::any())
//            ->method('getComposerPackageName')
//            ->willReturn($name);
//
//        /* @var AssetVcsRepository $repo */
//        $this->rm->addRepository($repo);
//
//        static::assertCount(0, $this->registry->whatProvides($this->pool, $name));
//    }

    protected function getCustomRepoConfig(): array
    {
        return [];
    }

    /**
     * Gets the asset type.
     *
     * @return string
     */
    abstract protected function getType(): string;

    /**
     * Gets the asset registry.
     *
     * @param array $repoConfig
     * @param IOInterface $io
     * @param Config $config
     * @param HttpDownloader $httpDownloader
     * @param EventDispatcher|null $eventDispatcher
     *
     * @return AbstractAssetsRepository
     */
    abstract protected function getRegistry(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, EventDispatcher $eventDispatcher = null): AbstractAssetsRepository;

    /**
     * Gets the mock package of asset for the config of VCS repository.
     *
     * @return array
     */
    abstract protected function getMockPackageForVcsConfig(): array;

    /**
     * Gets the mock search result.
     *
     * @param string $name
     *
     * @return array
     */
    abstract protected function getMockSearchResult(string $name = 'mock-package'): array;

    /**
     * Replaces the Remote file system of Registry by a mock.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function replaceRegistryRfsByMock(): \PHPUnit\Framework\MockObject\MockObject
    {
        $ref = new \ReflectionClass($this->registry);
        $pRef = $ref->getParentClass()->getParentClass();
        $pRfs = $pRef->getProperty('rfs');
        $pRfs->setAccessible(true);

        $rfs = $this->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->setConstructorArgs([$this->io, $this->config])
            ->getMock();

        $pRfs->setValue($this->registry, $rfs);

        return $rfs;
    }
}
