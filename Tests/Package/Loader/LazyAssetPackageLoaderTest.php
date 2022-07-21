<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Package\Loader;

use Composer\Downloader\TransportException;
use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Loader\LoaderInterface;
use Composer\Repository\Vcs\VcsDriverInterface;
use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;
use Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface;
use Fxp\Composer\AssetPlugin\Package\LazyPackageInterface;
use Fxp\Composer\AssetPlugin\Package\Loader\LazyAssetPackageLoader;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Tests\Fixtures\IO\MockIO;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Tests of lazy asset package loader.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class LazyAssetPackageLoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var LazyAssetPackageLoader
     */
    protected LazyAssetPackageLoader $lazyLoader;

    /**
     * @var LazyPackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected LazyPackageInterface|\PHPUnit\Framework\MockObject\MockObject $lazyPackage;

    /**
     * @var AssetTypeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetTypeInterface $assetType;

    /**
     * @var LoaderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|LoaderInterface $loader;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|VcsDriverInterface
     */
    protected VcsDriverInterface|\PHPUnit\Framework\MockObject\MockObject $driver;

    /**
     * @var MockIO
     */
    protected MockIO $io;

    /**
     * @var AssetRepositoryManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetRepositoryManager $assetRepositoryManager;

    protected function setUp(): void
    {
        $this->lazyPackage = $this->getMockBuilder(LazyPackageInterface::class)->getMock();
        $this->assetType = $this->getMockBuilder(AssetTypeInterface::class)->getMock();
        $this->loader = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $this->driver = $this->getMockBuilder(VcsDriverInterface::class)->getMock();
        $this->assetRepositoryManager = $this->getMockBuilder(AssetRepositoryManager::class)
            ->disableOriginalConstructor()->getMock();

        $this->assetRepositoryManager->expects(self::any())
            ->method('solveResolutions')
            ->willReturnCallback(function ($value) {
                return $value;
            });

        $this->lazyPackage
            ->expects(self::any())
            ->method('getName')
            ->willReturn('PACKAGE_NAME');
        $this->lazyPackage
            ->expects(self::any())
            ->method('getUniqueName')
            ->willReturn('PACKAGE_NAME-1.0.0.0');
        $this->lazyPackage
            ->expects(self::any())
            ->method('getPrettyVersion')
            ->willReturn('1.0');
        $this->lazyPackage
            ->expects(self::any())
            ->method('getVersion')
            ->willReturn('1.0.0.0');

        $versionConverter = $this->getMockBuilder(VersionConverterInterface::class)->getMock();
        $versionConverter->expects(self::any())
            ->method('convertVersion')
            ->willReturn('VERSION_CONVERTED');
        $versionConverter->expects(self::any())
            ->method('convertRange')
            ->willReturnCallback(function ($value) {
                return $value;
            });
        $packageConverter = $this->getMockBuilder(PackageConverterInterface::class)->getMock();
        /** @var LazyPackageInterface $lasyPackage */
        $lasyPackage = $this->lazyPackage;
        $packageConverter->expects(self::any())
            ->method('convert')
            ->willReturnCallback(function ($value) use ($lasyPackage) {
                $value['version'] = $lasyPackage->getPrettyVersion();
                $value['version_normalized'] = $lasyPackage->getVersion();

                return $value;
            });
        $this->assetType->expects(self::any())
            ->method('getComposerVendorName')
            ->willReturn('ASSET');
        $this->assetType->expects(self::any())
            ->method('getComposerType')
            ->willReturn('ASSET_TYPE');
        $this->assetType->expects(self::any())
            ->method('getFilename')
            ->willReturn('ASSET.json');
        $this->assetType->expects(self::any())
            ->method('getVersionConverter')
            ->willReturn($versionConverter);
        $this->assetType->expects(self::any())
            ->method('getPackageConverter')
            ->willReturn($packageConverter);

        $this->driver
            ->expects(self::any())
            ->method('getDist')
            ->willReturnCallback(function ($value) {
                return [
                    'type' => 'vcs',
                    'url' => 'http://foobar.tld/dist/' . $value,
                ];
            });
        $this->driver
            ->expects(self::any())
            ->method('getSource')
            ->willReturnCallback(function ($value) {
                return [
                    'type' => 'vcs',
                    'url' => 'http://foobar.tld/source/' . $value,
                ];
            });
    }

    protected function tearDown(): void
    {
        unset($this->lazyLoader, $this->assetType, $this->loader, $this->driver, $this->io, $this->assetRepositoryManager, $this->lazyPackage);
    }

    public function testMissingAssetType()
    {
        self::expectError();
        $loader = $this->createLazyLoader('TYPE');
        $loader->load($this->lazyPackage);
    }

    public function testMissingLoader()
    {
        self::expectError();
        /** @var AssetTypeInterface $assetType */
        $assetType = $this->assetType;
        $loader = $this->createLazyLoader('TYPE');
        $loader->setAssetType($assetType);
        $loader->load($this->lazyPackage);
    }

    public function testMissingDriver()
    {
        self::expectError();
        /** @var AssetTypeInterface $assetType */
        $assetType = $this->assetType;
        /** @var LoaderInterface $cLoader */
        $cLoader = $this->loader;
        /** @var LazyPackageInterface $lazyPackage */
        $lazyPackage = $this->lazyPackage;
        $loader = $this->createLazyLoader('TYPE');
        $loader->setAssetType($assetType);
        $loader->setLoader($cLoader);
        $loader->load($lazyPackage);
    }

    public function testMissingIo()
    {
        self::expectError();
        /** @var AssetTypeInterface $assetType */
        $assetType = $this->assetType;
        /** @var LoaderInterface $cLoader */
        $cLoader = $this->loader;
        /** @var VcsDriverInterface $driver */
        $driver = $this->driver;
        $loader = $this->createLazyLoader('TYPE');
        $loader->setAssetType($assetType);
        $loader->setLoader($cLoader);
        $loader->setDriver($driver);
        $loader->load($this->lazyPackage);
    }

    public function getConfigIo(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @param bool $verbose
     *
     * @dataProvider getConfigIo
     */
    public function testWithoutJsonFile(bool $verbose = false)
    {
        $driver = $this->driver;
        $driver
            ->expects(self::any())
            ->method('getComposerInformation')
            ->willReturn(null);

        $this->lazyLoader = $this->createLazyLoaderConfigured('TYPE', $verbose);
        $package = $this->lazyLoader->load($this->lazyPackage);

        self::assertFalse($package);

        $filename = $this->assetType->getFilename();
        $validOutput = [''];

        if ($verbose) {
            $validOutput = [
                'Reading ' . $filename . ' of <info>' . $this->lazyPackage->getName() . '</info> (<comment>' . $this->lazyPackage->getPrettyVersion() . '</comment>)',
                'Importing empty TYPE ' . $this->lazyPackage->getPrettyVersion() . ' (' . $this->lazyPackage->getVersion() . ')',
                '',
            ];
        }
        self::assertSame($validOutput, $this->io->getTraces());

        $packageCache = $this->lazyLoader->load($this->lazyPackage);
        self::assertFalse($packageCache);
        self::assertSame($validOutput, $this->io->getTraces());
    }

    /**
     * @param bool $verbose
     *
     * @dataProvider getConfigIo
     */
    public function testWithJsonFile(bool $verbose = false)
    {
        $arrayPackage = [
            'name' => 'PACKAGE_NAME',
            'version' => '1.0',
        ];

        $realPackage = new CompletePackage('PACKAGE_NAME', '1.0.0.0', '1.0');

        /** @var \PHPUnit\Framework\MockObject\MockObject $driver */
        $driver = $this->driver;
        $driver
            ->expects(self::any())
            ->method('getComposerInformation')
            ->willReturn($arrayPackage);

        /** @var \PHPUnit\Framework\MockObject\MockObject $loader */
        $loader = $this->loader;
        $loader
            ->expects(self::any())
            ->method('load')
            ->willReturn($realPackage);

        $this->lazyLoader = $this->createLazyLoaderConfigured('TYPE', $verbose);
        $package = $this->lazyLoader->load($this->lazyPackage);

        $filename = $this->assetType->getFilename();
        $validOutput = [''];

        if ($verbose) {
            $validOutput = [
                'Reading ' . $filename . ' of <info>' . $this->lazyPackage->getName() . '</info> (<comment>' . $this->lazyPackage->getPrettyVersion() . '</comment>)',
                'Importing TYPE' . ' ' . $this->lazyPackage->getPrettyVersion() . ' (' . $this->lazyPackage->getVersion() . ')',
                '',
            ];
        }

        self::assertInstanceOf('Composer\Package\CompletePackageInterface', $package);
        self::assertSame($validOutput, $this->io->getTraces());

        $packageCache = $this->lazyLoader->load($this->lazyPackage);
        self::assertInstanceOf('Composer\Package\CompletePackageInterface', $packageCache);
        self::assertSame($package, $packageCache);
        self::assertSame($validOutput, $this->io->getTraces());
    }

    public function getConfigIoForException(): array
    {
        return [
            ['tag', false, 'Exception', '<warning>Skipped tag 1.0, MESSAGE</warning>'],
            ['tag', true, 'Exception', '<warning>Skipped tag 1.0, MESSAGE</warning>'],
            ['branch', false, 'Exception', '<error>Skipped branch 1.0, MESSAGE</error>'],
            ['branch', true, 'Exception', '<error>Skipped branch 1.0, MESSAGE</error>'],
            ['tag', false, TransportException::class, '<warning>Skipped tag 1.0, no ASSET.json file was found</warning>'],
            ['tag', true, TransportException::class, '<warning>Skipped tag 1.0, no ASSET.json file was found</warning>'],
            ['branch', false, TransportException::class, '<error>Skipped branch 1.0, no ASSET.json file was found</error>'],
            ['branch', true, TransportException::class, '<error>Skipped branch 1.0, no ASSET.json file was found</error>'],
        ];
    }

    /**
     * @param string $type
     * @param bool $verbose
     * @param string $exceptionClass
     * @param string $validTrace
     *
     * @dataProvider getConfigIoForException
     */
    public function testTagWithTransportException(string $type, bool $verbose, string $exceptionClass, string $validTrace)
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject $loader */
        $loader = $this->loader;
        $loader
            ->expects(self::any())
            ->method('load')
            ->will(self::throwException(new $exceptionClass('MESSAGE')));

        $this->lazyLoader = $this->createLazyLoaderConfigured($type, $verbose);
        $package = $this->lazyLoader->load($this->lazyPackage);

        self::assertFalse($package);

        $filename = $this->assetType->getFilename();
        $validOutput = [''];

        if ($verbose) {
            $validOutput = [
                'Reading ' . $filename . ' of <info>' . $this->lazyPackage->getName() . '</info> (<comment>' . $this->lazyPackage->getPrettyVersion() . '</comment>)',
                'Importing empty ' . $type . ' ' . $this->lazyPackage->getPrettyVersion() . ' (' . $this->lazyPackage->getVersion() . ')',
                $validTrace,
                '',
            ];
        }
        self::assertSame($validOutput, $this->io->getTraces());

        $packageCache = $this->lazyLoader->load($this->lazyPackage);
        self::assertFalse($packageCache);
        self::assertSame($validOutput, $this->io->getTraces());
    }

    /**
     * Creates the lazy asset package loader with full configuration.
     *
     * @param string $type
     * @param bool $verbose
     *
     * @return LazyAssetPackageLoader
     */
    protected function createLazyLoaderConfigured(string $type, bool $verbose = false): LazyAssetPackageLoader
    {
        $this->io = new MockIO($verbose);

        $cLoader = $this->loader;
        $loader = $this->createLazyLoader($type);
        $loader->setAssetType($this->assetType);
        $loader->setLoader($cLoader);
        $loader->setDriver($this->driver);
        $loader->setIO($this->io);
        $loader->setAssetRepositoryManager($this->assetRepositoryManager);

        return $loader;
    }

    /**
     * Creates the lazy asset package loader.
     *
     * @param string $type
     *
     * @return LazyAssetPackageLoader
     */
    protected function createLazyLoader(string $type): LazyAssetPackageLoader
    {
        $data = [
            'foo' => 'bar',
            'bar' => 'foo',
        ];

        return new LazyAssetPackageLoader($type, 'IDENTIFIER', $data);
    }
}
