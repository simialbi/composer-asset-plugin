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
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Fxp\Composer\AssetPlugin\Config\ConfigBuilder;
use Fxp\Composer\AssetPlugin\Installer\AssetInstaller;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Tests of asset installer.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class AssetInstallerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Composer $composer;

    /**
     * @var IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface $package;

    /**
     * @var AssetTypeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetTypeInterface $type;

    protected function setUp(): void
    {
        $this->io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $config = $this->getMockBuilder('Composer\Config')->getMock();
        $config->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($key) {

                return match ($key) {
                    'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-repo-cache',
                    'cache-read-only' => false,
                    'vendor-dir' => sys_get_temp_dir() . '/composer-test/vendor',
                    'bin-dir' => sys_get_temp_dir() . '/composer-test/vendor/bin',
                    'bin-compat' => 'auto',
                    default => null
                };
            });

        $this->package = $this->getMockBuilder('Composer\Package\RootPackageInterface')->getMock();

        $this->composer = $this->getMockBuilder('Composer\Composer')->getMock();
        $this->composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($this->package);
        $this->composer->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);

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
        unset($this->package, $this->composer, $this->io);

        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir() . '/composer-test-repo-cache');
        $fs->remove(sys_get_temp_dir() . '/composer-test');
    }

    public function testDefaultVendorDir()
    {
        $installer = $this->createInstaller();
        $vendorDir = realpath(sys_get_temp_dir()) . '/composer-test/vendor/' . $this->type->getComposerVendorName();
        $vendorDir = str_replace('\\', '/', $vendorDir);

        $installerPath = $installer->getInstallPath($this->createPackageMock('foo-asset/foo'));
        $installerPath = str_replace('\\', '/', $installerPath);
        self::assertEquals($vendorDir . '/foo', $installerPath);

        $installerPath2 = $installer->getInstallPath($this->createPackageMock('foo-asset/foo/bar'));
        $installerPath2 = str_replace('\\', '/', $installerPath2);
        self::assertEquals($vendorDir . '/foo/bar', $installerPath2);
    }

    public function testCustomFooDir()
    {
        $vendorDir = realpath(sys_get_temp_dir()) . '/composer-test/web';
        $vendorDir = str_replace('\\', '/', $vendorDir);

        $package = $this->package;
        $package->expects(self::any())
            ->method('getExtra')
            ->willReturn([
                'asset-installer-paths' => [
                    $this->type->getComposerType() => $vendorDir,
                ],
            ]);

        $installer = $this->createInstaller();

        $installerPath = $installer->getInstallPath($this->createPackageMock('foo-asset/foo'));
        $installerPath = str_replace('\\', '/', $installerPath);
        self::assertEquals($vendorDir . '/foo', $installerPath);

        $installerPath2 = $installer->getInstallPath($this->createPackageMock('foo-asset/foo/bar'));
        $installerPath2 = str_replace('\\', '/', $installerPath2);
        self::assertEquals($vendorDir . '/foo/bar', $installerPath2);
    }

    public function testInstall()
    {
        /** @var RootPackageInterface $rootPackage */
        $rootPackage = $this->createRootPackageMock();
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var AssetTypeInterface $type */
        $type = $this->type;
        $vendorDir = realpath(sys_get_temp_dir()) . \DIRECTORY_SEPARATOR . 'composer-test' . \DIRECTORY_SEPARATOR . 'vendor';

        $this->composer->setPackage($rootPackage);

        $dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->composer->expects(self::any())
            ->method('getDownloadManager')
            ->willReturn($dm);

        /** @var \PHPUnit\Framework\MockObject\MockObject $package */
        $package = $this->createPackageMock('foo-asset/package');

        /** @var PackageInterface $package */
        $packageDir = $vendorDir . '/' . $package->getPrettyName();

        $dm->expects(self::once())
            ->method('download')
            ->with($package, $vendorDir . \DIRECTORY_SEPARATOR . 'foo-asset/package');

        $repository = $this->getMockBuilder('Composer\Repository\InstalledRepositoryInterface')->getMock();
        $repository->expects(self::once())
            ->method('addPackage')
            ->with($package);

        $config = ConfigBuilder::build($this->composer);
        $library = new AssetInstaller($config, $io, $this->composer, $type);

        /* @var InstalledRepositoryInterface $repository */
        $library->install($repository, $package);
        self::assertFileExists($vendorDir, 'Vendor dir should be created');

        $this->ensureDirectoryExistsAndClear($packageDir);
    }

    /**
     * Creates the asset installer.
     *
     * @return AssetInstaller
     */
    protected function createInstaller(): AssetInstaller
    {
        /** @var IOInterface $io */
        $io = $this->io;
        /** @var Composer $composer */
        $composer = $this->composer;
        /** @var AssetTypeInterface $type */
        $type = $this->type;
        $config = ConfigBuilder::build($composer);

        return new AssetInstaller($config, $io, $composer, $type);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected function createRootPackageMock(): RootPackageInterface|\PHPUnit\Framework\MockObject\MockObject
    {
        $package = $this->getMockBuilder('Composer\Package\RootPackageInterface')
            ->getMock();

        $package->expects(self::any())
            ->method('getConfig')
            ->willReturn([]);

        return $package;
    }

    protected function ensureDirectoryExistsAndClear($directory)
    {
        $fs = new Filesystem();
        if (is_dir($directory)) {
            $fs->removeDirectory($directory);
        }
        mkdir($directory, 0777, true);
    }

    /**
     * Creates the mock package.
     *
     * @param string $name
     *
     * @return PackageInterface
     */
    private function createPackageMock(string $name): PackageInterface
    {
        return $this->getMockBuilder('Composer\Package\Package')
            ->setConstructorArgs([$name, '1.0.0.0', '1.0.0'])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }
}
