<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Installer;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Fxp\Composer\AssetPlugin\Config\ConfigBuilder;
use Fxp\Composer\AssetPlugin\Installer\BowerInstaller;
use Fxp\Composer\AssetPlugin\Tests\TestCase;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;
use Fxp\Composer\AssetPlugin\Util\AssetPlugin;

/**
 * Tests of bower asset installer.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class BowerInstallerTest extends TestCase
{
    /**
     * @var Composer
     */
    protected Composer $composer;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var string
     */
    protected string $vendorDir;

    /**
     * @var string
     */
    protected string $binDir;

    /**
     * @var DownloadManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|DownloadManager $dm;

    /**
     * @var InstalledRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|InstalledRepositoryInterface $repository;

    /**
     * @var IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var Filesystem
     */
    protected Filesystem $fs;

    /**
     * @var AssetTypeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetTypeInterface $type;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();

        $this->composer = new Composer();
        $this->config = new Config();
        $this->composer->setConfig($this->config);

        $this->vendorDir = realpath(sys_get_temp_dir()) . \DIRECTORY_SEPARATOR . 'composer-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);

        $this->binDir = realpath(sys_get_temp_dir()) . \DIRECTORY_SEPARATOR . 'composer-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);

        $this->config->merge([
            'config' => [
                'vendor-dir' => $this->vendorDir,
                'bin-dir' => $this->binDir,
            ],
        ]);

        $this->dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
            ->disableOriginalConstructor()
            ->getMock();
        /** @var DownloadManager $dm */
        $dm = $this->dm;
        $this->composer->setDownloadManager($dm);

        $this->repository = $this->getMockBuilder('Composer\Repository\InstalledRepositoryInterface')->getMock();
        $this->io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $this->type = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Type\AssetTypeInterface')->getMock();
        $this->type->expects(self::any())
            ->method('getName')
            ->willReturn('foo');
        $this->type->expects(self::any())
            ->method('getComposerVendorName')
            ->willReturn('foo-asset');
        $this->type->expects(self::any())
            ->method('getComposerType')
            ->willReturn('foo-asset-library');
        $this->type->expects(self::any())
            ->method('getFilename')
            ->willReturn('foo.json');
        $this->type->expects(self::any())
            ->method('getVersionConverter')
            ->willReturn($this->getMockBuilder('Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface')->getMock());
        $this->type->expects(self::any())
            ->method('getPackageConverter')
            ->willReturn($this->getMockBuilder('Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface')->getMock());
    }

    protected function tearDown(): void
    {
        $this->fs->removeDirectory($this->vendorDir);
        $this->fs->removeDirectory($this->binDir);
    }

    public function testInstallerCreationShouldNotCreateVendorDirectory()
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->fs->removeDirectory($this->vendorDir);
        $this->composer->setPackage($rootPackage);

        new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        self::assertDirectoryDoesNotExist($this->vendorDir);
    }

    public function testInstallerCreationShouldNotCreateBinDirectory()
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->fs->removeDirectory($this->binDir);
        $this->composer->setPackage($rootPackage);

        new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        self::assertDirectoryDoesNotExist($this->binDir);
    }

    public function testIsInstalled()
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->composer->setPackage($rootPackage);

        $library = new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        /** @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package = $this->createPackageMock();
        $package
            ->expects(self::any())
            ->method('getPrettyName')
            ->willReturn('foo-asset/package');

        /** @var PackageInterface $package */
        $packageDir = $this->vendorDir . '/' . $package->getPrettyName();
        mkdir($packageDir, 0777, true);

        /** @var \PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->repository;
        $repository
            ->expects(self::exactly(2))
            ->method('hasPackage')
            ->with($package)
            ->will(self::onConsecutiveCalls(true, false));

        /** @var InstalledRepositoryInterface $repository */
        self::assertTrue($library->isInstalled($repository, $package));
        self::assertFalse($library->isInstalled($repository, $package));

        $this->ensureDirectoryExistsAndClear($packageDir);
    }

    public function getAssetIgnoreFiles(): array
    {
        return [
            [[]],
            [['foo', 'bar']],
        ];
    }

    public function getAssetMainFiles(): array
    {
        return [
            [[]],
            [[
                'fxp-asset' => [
                    'main-files' => [
                        'foo-asset/bar' => [
                            'foo',
                            'bar',
                        ],
                    ],
                ],
            ]],
        ];
    }

    /**
     * @dataProvider getAssetIgnoreFiles
     */
    public function testInstall(array $ignoreFiles)
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->composer->setPackage($rootPackage);

        $library = new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        /** @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package = $this->createPackageMock($ignoreFiles);
        $package
            ->expects(self::any())
            ->method('getPrettyName')
            ->willReturn('foo-asset/package');

        /** @var PackageInterface $package */
        $packageDir = $this->vendorDir . '/' . $package->getPrettyName();
        mkdir($packageDir, 0777, true);

        /** @var \PHPUnit\Framework\MockObject\MockObject $dm */
        $dm = $this->dm;
        $dm
            ->expects(self::once())
            ->method('download')
            ->with($package, $this->vendorDir . \DIRECTORY_SEPARATOR . 'foo-asset/package');

        /** @var \PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->repository;
        $repository
            ->expects(self::once())
            ->method('addPackage')
            ->with($package);

        /* @var InstalledRepositoryInterface $repository */
        $library->install($repository, $package);
        self::assertFileExists($this->vendorDir, 'Vendor dir should be created');
        self::assertFileExists($this->binDir, 'Bin dir should be created');

        $this->ensureDirectoryExistsAndClear($packageDir);
    }

    /**
     * @dataProvider getAssetIgnoreFiles
     */
    public function testUpdate(array $ignoreFiles)
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->composer->setPackage($rootPackage);

        $library = new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        /** @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package = $this->createPackageMock($ignoreFiles);
        $package
            ->expects(self::any())
            ->method('getPrettyName')
            ->willReturn('foo-asset/package');

        /** @var PackageInterface $package */
        $packageDir = $this->vendorDir . '/' . $package->getPrettyName();
        mkdir($packageDir, 0777, true);

        /** @var \PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->repository;

        $repository
            ->expects(self::exactly(2))
            ->method('hasPackage')
            ->with($package)
            ->willReturn(true);

        /* @var InstalledRepositoryInterface $repository */
        $library->update($repository, $package, $package);
        self::assertFileExists($this->vendorDir, 'Vendor dir should be created');
        self::assertFileExists($this->binDir, 'Bin dir should be created');

        $this->ensureDirectoryExistsAndClear($packageDir);
    }

    public function testUninstall()
    {
        self::expectException('\InvalidArgumentException');
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->composer->setPackage($rootPackage);

        $library = new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        $package = $this->createPackageMock();

        /* @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package
            ->expects(self::any())
            ->method('getPrettyName')
            ->willReturn('foo-asset/pkg');

        /** @var \PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->repository;
        $repository
            ->expects(self::exactly(2))
            ->method('hasPackage')
            ->with($package)
            ->will(self::onConsecutiveCalls(true, false));

        $repository
            ->expects(self::once())
            ->method('removePackage')
            ->with($package);

        /** @var \PHPUnit\Framework\MockObject\MockObject $dm */
        $dm = $this->dm;
        $dm
            ->expects(self::once())
            ->method('remove')
            ->with($package, $this->vendorDir . \DIRECTORY_SEPARATOR . 'foo-asset/pkg');

        /* @var InstalledRepositoryInterface $repository */
        /* @var PackageInterface $package */
        $library->uninstall($repository, $package);

        $library->uninstall($repository, $package);
    }

    public function testGetInstallPath()
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->composer->setPackage($rootPackage);

        $library = new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        $package = $this->createPackageMock();

        /* @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package
            ->expects(self::once())
            ->method('getTargetDir')
            ->willReturn(null);
        $package
            ->expects(self::any())
            ->method('getName')
            ->willReturn('foo-asset/bar');
        $package
            ->expects(self::any())
            ->method('getPrettyName')
            ->willReturn('foo-asset/bar');

        /** @var PackageInterface $package */
        $exceptDir = $this->vendorDir . '/' . $package->getName();
        $exceptDir = str_replace('\\', '/', $exceptDir);
        $packageDir = $library->getInstallPath($package);
        $packageDir = str_replace('\\', '/', $packageDir);

        self::assertEquals($exceptDir, $packageDir);
    }

    public function testGetInstallPathWithTargetDir()
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;

        $this->composer->setPackage($rootPackage);

        $library = new BowerInstaller(ConfigBuilder::build($this->composer), $io, $this->composer, $type);
        $package = $this->createPackageMock();

        /* @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package
            ->expects(self::once())
            ->method('getTargetDir')
            ->willReturn('Some/Namespace');
        $package
            ->expects(self::any())
            ->method('getPrettyName')
            ->willReturn('foo-asset/bar');

        /** @var PackageInterface $package */
        $exceptDir = $this->vendorDir . '/' . $package->getPrettyName() . '/Some/Namespace';
        $exceptDir = str_replace('\\', '/', $exceptDir);
        $packageDir = $library->getInstallPath($package);
        $packageDir = str_replace('\\', '/', $packageDir);

        self::assertEquals($exceptDir, $packageDir);
    }

    /**
     * @dataProvider getAssetMainFiles
     */
    public function testMainFiles(array $mainFiles)
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock($mainFiles);
        $this->composer->setPackage($rootPackage);
        $config = ConfigBuilder::build($this->composer);

        $package = new Package('foo-asset/bar', '1.0.0', '1.0.0');
        $package = AssetPlugin::addMainFiles($config, $package);
        $extra = $package->getExtra();

        if (isset($mainFiles['fxp-asset']['main-files'])) {
            self::assertEquals($extra['bower-asset-main'], $mainFiles['fxp-asset']['main-files']['foo-asset/bar']);
        } else {
            self::assertIsArray($extra);
            self::assertEmpty($extra);
        }
    }

    /**
     * @param array $ignoreFiles
     * @return PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createPackageMock(array $ignoreFiles = []): \PHPUnit\Framework\MockObject\MockObject|PackageInterface
    {
        $package = $this->getMockBuilder('Composer\Package\Package')
            ->setConstructorArgs([md5(mt_rand()), '1.0.0.0', '1.0.0'])
            ->getMock();

        $package
            ->expects(self::any())
            ->method('getExtra')
            ->willReturn([
                'bower-asset-ignore' => $ignoreFiles,
            ]);

        return $package;
    }

    /**
     * @param array $mainFiles
     * @return \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected function createRootPackageMock(array $mainFiles = []): RootPackageInterface|\PHPUnit\Framework\MockObject\MockObject
    {
        $package = $this->getMockBuilder('Composer\Package\RootPackageInterface')
            ->getMock();

        $package
            ->expects(self::any())
            ->method('getConfig')
            ->willReturn($mainFiles);

        return $package;
    }
}
