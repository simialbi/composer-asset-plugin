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
use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Repository\InvalidRepositoryException;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\AssetVcsRepository;
use Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter;
use Fxp\Composer\AssetPlugin\Tests\Fixtures\IO\MockIO;
use Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriver;

/**
 * Tests of asset vcs repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class AssetVcsRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var EventDispatcher
     */
    protected EventDispatcher $dispatcher;

    /**
     * @var MockIO
     */
    protected MockIO $io;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|HttpDownloader
     */
    protected \PHPUnit\Framework\MockObject\MockObject|HttpDownloader $httpDownloader;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AssetRepositoryManager
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetRepositoryManager $assetRepositoryManager;

    /**
     * @var AssetVcsRepository
     */
    protected AssetVcsRepository $repository;

    protected function setUp(): void
    {
        $this->config = new Config();
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getMockBuilder('Composer\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();
        $this->dispatcher = $dispatcher;
        $this->assetRepositoryManager = $this->getMockBuilder(AssetRepositoryManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->httpDownloader = $this->getMockBuilder(HttpDownloader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->assetRepositoryManager->expects(self::any())
            ->method('solveResolutions')
            ->willReturnCallback(function ($value) {
                return $value;
            });
    }

    protected function tearDown(): void
    {
        unset($this->config, $this->dispatcher, $this->io, $this->repository);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function getDefaultDrivers(): array
    {
        return [
            ['npm-github', 'http://example.org/foo.git', 'Fxp\Composer\AssetPlugin\Repository\Vcs\GitHubDriver'],
            ['npm-git', 'http://example.org/foo.git', 'Fxp\Composer\AssetPlugin\Repository\Vcs\GitDriver'],
            ['bower-github', 'http://example.org/foo.git', 'Fxp\Composer\AssetPlugin\Repository\Vcs\GitHubDriver'],
            ['bower-git', 'http://example.org/foo.git', 'Fxp\Composer\AssetPlugin\Repository\Vcs\GitDriver'],
        ];
    }

    /**
     * @dataProvider getDefaultDrivers
     *
     * @param string $type
     * @param string $url
     */
    public function testDefaultConstructor(string $type, string $url)
    {
        $this->init(false, $type, $url, '', false, []);
        self::assertEquals(0, $this->repository->count());
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function getMockDrivers(): array
    {
        return [
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriver'],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriver'],
        ];
    }

    /**
     * @dataProvider getMockDrivers
     *
     * @param string $type
     * @param string $url
     * @param string $class
     */
    public function testNotDriverFound(string $type, string $url, string $class)
    {
        self::expectException('\Composer\Repository\InvalidRepositoryException');
        self::expectExceptionMessageMatches('/No valid (bower|package).json was found in any branch or tag of http:\/\/example.org\/foo, could not load a package from it./');
        $this->init(false, $type, $url, $class);
        $this->repository->getPackages();
    }

    /**
     * @dataProvider getMockDrivers
     *
     * @param string $type
     * @param string $url
     * @param string $class
     */
    public function testWithoutValidPackage(string $type, string $url, string $class)
    {
        self::expectException('\Composer\Repository\InvalidRepositoryException');
        $this->init(true, $type, $url, $class);
        $this->repository->getPackages();
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function getMockDriversSkipParsing(): array
    {
        return [
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverSkipParsing', false],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverSkipParsing', false],
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverSkipParsing', true],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverSkipParsing', true],
        ];
    }

    /**
     * @dataProvider getMockDriversSkipParsing
     *
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     */
    public function testSkipParsingFile(string $type, string $url, string $class, bool $verbose)
    {
        $validTraces = [''];
        if ($verbose) {
            $validTraces = [
                '<error>Skipped parsing ROOT, MESSAGE with ROOT</error>',
                '',
            ];
        }

        $this->init(true, $type, $url, $class, $verbose);

        try {
            $this->repository->getPackages();
        } catch (InvalidRepositoryException $e) {
            // for analysis the IO traces
        }
        self::assertSame($validTraces, $this->io->getTraces());
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function getMockDriversWithExceptions(): array
    {
        return [
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithException'],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithException'],
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithException'],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithException'],
        ];
    }

    /**
     * @dataProvider getMockDriversWithExceptions
     *
     * @param string $type
     * @param string $url
     * @param string $class
     */
    public function testInitFullDriverWithUncachedException(string $type, string $url, string $class)
    {
        self::expectException('\ErrorException');
        self::expectExceptionMessage('Error to retrieve the tags');
        $this->init(true, $type, $url, $class);

        $this->repository->getComposerPackageName();
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function getMockDriversWithVersions(): array
    {
        return [
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithPackages', false],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithPackages', false],
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithPackages', true],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithPackages', true],
        ];
    }

    /**
     * @dataProvider getMockDriversWithVersions
     *
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     */
    public function testRepositoryPackageName(string $type, string $url, string $class, bool $verbose)
    {
        $packageName = 'asset-package-name';
        $valid = str_replace('-mock', '-asset', $type) . '/' . $packageName;

        $this->init(true, $type, $url, $class, $verbose, null, $packageName);

        self::assertEquals($valid, $this->repository->getComposerPackageName());
    }

    /**
     * @dataProvider getMockDriversWithVersions
     *
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     */
    public function testWithTagsAndBranchs(string $type, string $url, string $class, bool $verbose)
    {
        $validPackageName = substr($type, 0, strpos($type, '-')) . '-asset/foobar';
        $validTraces = [''];
        if ($verbose) {
            $validTraces = [
                '<warning>Skipped tag invalid, invalid tag name</warning>',
                '',
            ];
        }

        $this->init(true, $type, $url, $class, $verbose);

        /** @var PackageInterface[] $packages */
        $packages = $this->repository->getPackages();
        self::assertCount(7, $packages);

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            self::assertInstanceOf('Composer\Package\CompletePackage', $package);
            self::assertSame($validPackageName, $package->getName());
        }

        self::assertSame($validTraces, $this->io->getTraces());
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function getMockDriversWithVersionsAndWithoutName(): array
    {
        return [
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithUrlPackages', false],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithUrlPackages', false],
            ['npm-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithUrlPackages', true],
            ['bower-mock', 'http://example.org/foo', 'Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriverWithUrlPackages', true],
        ];
    }

    /**
     * @dataProvider getMockDriversWithVersionsAndWithoutName
     *
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     */
    public function testWithTagsAndBranchsWithoutPackageName(string $type, string $url, string $class, bool $verbose)
    {
        $validPackageName = $url;
        $validTraces = [''];
        if ($verbose) {
            $validTraces = [
                '<warning>Skipped tag invalid, invalid tag name</warning>',
                '',
            ];
        }

        $this->init(true, $type, $url, $class, $verbose);

        /** @var PackageInterface[] $packages */
        $packages = $this->repository->getPackages();
        self::assertCount(7, $packages);

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            self::assertInstanceOf('Composer\Package\CompletePackage', $package);
            self::assertSame($validPackageName, $package->getName());
        }

        self::assertSame($validTraces, $this->io->getTraces());
    }

    /**
     * @dataProvider getMockDriversWithVersions
     *
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     */
    public function testWithTagsAndBranchesWithRegistryPackageName(string $type, string $url, string $class, bool $verbose)
    {
        $validPackageName = substr($type, 0, strpos($type, '-')) . '-asset/registry-foobar';
        $validTraces = [''];
        if ($verbose) {
            $validTraces = [
                '<warning>Skipped tag invalid, invalid tag name</warning>',
                '',
            ];
        }

        $this->init(true, $type, $url, $class, $verbose, null, 'registry-foobar');

        /** @var PackageInterface[] $packages */
        $packages = $this->repository->getPackages();
        self::assertCount(7, $packages);

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            self::assertInstanceOf('Composer\Package\CompletePackage', $package);
            self::assertSame($validPackageName, $package->getName());
        }

        self::assertSame($validTraces, $this->io->getTraces());
    }

    /**
     * @dataProvider getMockDriversWithVersions
     *
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     */
    public function testWithFilterTags(string $type, string $url, string $class, bool $verbose)
    {
        $validPackageName = substr($type, 0, strpos($type, '-')) . '-asset/registry-foobar';
        $validTraces = [''];
        if ($verbose) {
            $validTraces = [];
        }

        $filter = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter')
            ->disableOriginalConstructor()
            ->getMock();

        $filter->expects(self::any())
            ->method('skip')
            ->willReturn(true);

        /* @var VcsPackageFilter $filter */
        $this->init(true, $type, $url, $class, $verbose, null, 'registry-foobar', $filter);

        /** @var PackageInterface[] $packages */
        $packages = $this->repository->getPackages();
        self::assertCount(5, $packages);

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            self::assertInstanceOf('Composer\Package\CompletePackage', $package);
            self::assertSame($validPackageName, $package->getName());
        }

        self::assertSame($validTraces, $this->io->getTraces());
    }

    /**
     * @dataProvider getMockDrivers
     *
     * @param string $type
     * @param string $url
     * @param string $class
     */
    public function testPackageWithRegistryVersions(string $type, string $url, string $class)
    {
        $registryPackages = [
            new CompletePackage('package1', '0.1.0.0', '0.1'),
            new CompletePackage('package1', '0.2.0.0', '0.2'),
            new CompletePackage('package1', '0.3.0.0', '0.3'),
            new CompletePackage('package1', '0.4.0.0', '0.4'),
            new CompletePackage('package1', '0.5.0.0', '0.5'),
            new CompletePackage('package1', '0.6.0.0', '0.6'),
            new CompletePackage('package1', '0.7.0.0', '0.7'),
            new CompletePackage('package1', '0.8.0.0', '0.8'),
            new CompletePackage('package1', '0.9.0.0', '0.9'),
            new CompletePackage('package1', '1.0.0.0', '1.0'),
        ];

        $this->init(true, $type, $url, $class, false, null, 'registry-foobar', null, $registryPackages);

        /** @var PackageInterface[] $packages */
        $packages = $this->repository->getPackages();
        self::assertCount(10, $packages);
        self::assertSame($registryPackages, $packages);
    }

    /**
     * Init the test.
     *
     * @param bool $supported
     * @param string $type
     * @param string $url
     * @param string $class
     * @param bool $verbose
     * @param array|null $drivers
     * @param string|null $registryName
     */
    protected function init(
        bool             $supported,
        string           $type,
        string           $url,
        string           $class,
        bool             $verbose = false,
        array            $drivers = null,
        string           $registryName = null,
        VcsPackageFilter $vcsPackageFilter = null,
        array            $registryPackages = []
    )
    {
        MockVcsDriver::$supported = $supported;
        $driverType = substr($type, strpos($type, '-') + 1);
        $repoConfig = [
            'type' => $type,
            'url' => $url,
            'name' => $registryName,
            'vcs-package-filter' => $vcsPackageFilter,
            'asset-repository-manager' => $this->assetRepositoryManager
        ];

        if (null === $drivers) {
            $drivers = [
                $driverType => $class,
            ];
        }

        if (\count($registryPackages) > 0) {
            $repoConfig['registry-versions'] = $registryPackages;
        }

        $this->io = $this->createIO($verbose);
        $this->repository = new AssetVcsRepository($repoConfig, $this->io, $this->config, $this->httpDownloader, $this->dispatcher, null, $drivers);
    }

    /**
     * @param bool $verbose
     *
     * @return MockIO
     */
    protected function createIO(bool $verbose = false): MockIO
    {
        return new MockIO($verbose);
    }
}
