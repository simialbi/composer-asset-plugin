<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\CompositeRepository;
use Fxp\Composer\AssetPlugin\Composer\ScriptHandler;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\FxpAssetPlugin;

/**
 * Tests for the composer script handler.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class ScriptHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Composer $composer;

    /**
     * @var \Composer\Config|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \Composer\Config|\PHPUnit\Framework\MockObject\MockObject $config;

    /**
     * @var IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var InstallOperation|OperationInterface|\PHPUnit\Framework\MockObject\MockObject|UpdateOperation
     */
    protected OperationInterface|\PHPUnit\Framework\MockObject\MockObject|InstallOperation|UpdateOperation $operation;

    /**
     * @var PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected PackageInterface|\PHPUnit\Framework\MockObject\MockObject $package;

    protected function setUp(): void
    {
        $this->composer = $this->getMockBuilder('Composer\Composer')->getMock();
        $this->io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $this->package = $this->getMockBuilder('Composer\Package\PackageInterface')->getMock();

        $this->config = $this->getMockBuilder('Composer\Config')->getMock();
        $this->config->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                $val = null;

                return match ($key) {
                    'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-repo-cache',
                    'vendor-dir' => sys_get_temp_dir() . '/composer-test/vendor',
                    default => $val,
                };
            });

        $rootPackage = $this->getMockBuilder('Composer\Package\RootPackageInterface')->getMock();

        $this->composer->expects(self::any())
            ->method('getConfig')
            ->willReturn($this->config);
        $this->composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($rootPackage);

        $plugin = $this->getMockBuilder(FxpAssetPlugin::class)->disableOriginalConstructor()->getMock();
        $plugin->expects(self::any())
            ->method('getConfig')
            ->willReturn(new Config([]));

        $pm = $this->getMockBuilder(PluginManager::class)->disableOriginalConstructor()->getMock();
        $pm->expects(self::any())
            ->method('getPlugins')
            ->willReturn([$plugin]);

        $this->composer->expects(self::any())
            ->method('getPluginManager')
            ->willReturn($pm);
    }

    protected function tearDown(): void
    {
        unset($this->composer, $this->io, $this->operation, $this->package);
    }

    public function getPackageComposerTypes(): array
    {
        return [
            ['npm-asset-library'],
            ['bower-asset-library'],
            ['library'],
        ];
    }

    /**
     * @dataProvider getPackageComposerTypes
     *
     * @param string $composerType
     */
    public function testDeleteIgnoreFiles(string $composerType)
    {
        $this->operation = $this->getMockBuilder('Composer\DependencyResolver\Operation\OperationInterface')->getMock();
        self::assertInstanceOf('Composer\DependencyResolver\Operation\OperationInterface', $this->operation);

        ScriptHandler::deleteIgnoredFiles($this->createEvent($composerType));
    }

    /**
     * @dataProvider getPackageComposerTypes
     *
     * @param string $composerType
     */
    public function testDeleteIgnoreFilesWithInstallOperation(string $composerType)
    {
        $this->operation = $this->getMockBuilder('Composer\DependencyResolver\Operation\InstallOperation')
            ->disableOriginalConstructor()
            ->getMock();
        self::assertInstanceOf('Composer\DependencyResolver\Operation\OperationInterface', $this->operation);

        ScriptHandler::deleteIgnoredFiles($this->createEvent($composerType));
    }

    /**
     * @dataProvider getPackageComposerTypes
     *
     * @param string $composerType
     */
    public function testDeleteIgnoreFilesWithUpdateOperation(string $composerType)
    {
        $this->operation = $this->getMockBuilder('Composer\DependencyResolver\Operation\UpdateOperation')
            ->disableOriginalConstructor()
            ->getMock();
        self::assertInstanceOf('Composer\DependencyResolver\Operation\OperationInterface', $this->operation);

        ScriptHandler::deleteIgnoredFiles($this->createEvent($composerType));
    }

    /**
     * @dataProvider getPackageComposerTypes
     *
     * @param string $composerType
     */
    public function testGetConfig(string $composerType)
    {
        self::expectException('\RuntimeException');
        self::expectExceptionMessage('The fxp composer asset plugin is not found');
        $rootPackage = $this->getMockBuilder('Composer\Package\RootPackageInterface')->getMock();

        $this->composer = $this->getMockBuilder('Composer\Composer')->getMock();
        $this->composer->expects(self::any())
            ->method('getConfig')
            ->willReturn($this->config);
        $this->composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($rootPackage);

        $pm = $this->getMockBuilder(PluginManager::class)->disableOriginalConstructor()->getMock();
        $pm->expects(self::any())
            ->method('getPlugins')
            ->willReturn([]);

        $this->composer->expects(self::any())
            ->method('getPluginManager')
            ->willReturn($pm);

        $this->operation = $this->getMockBuilder('Composer\DependencyResolver\Operation\OperationInterface')->getMock();

        ScriptHandler::getConfig($this->createEvent($composerType));
    }

    /**
     * @param string $composerType
     *
     * @return PackageEvent
     */
    protected function createEvent(string $composerType): PackageEvent
    {
        $this->package->expects(self::any())
            ->method('getType')
            ->willReturn($composerType);

        if ($this->operation instanceof UpdateOperation) {
            $this->operation->expects(self::any())
                ->method('getTargetPackage')
                ->willReturn($this->package);
        }

        if ($this->operation instanceof InstallOperation) {
            $this->operation->expects(self::any())
                ->method('getPackage')
                ->willReturn($this->package);
        }

        /** @var PolicyInterface $policy */
//        $policy = $this->getMockBuilder('Composer\DependencyResolver\PolicyInterface')->getMock();
        /** @var Pool $pool */
//        $pool = $this->getMockBuilder('Composer\DependencyResolver\Pool')->disableOriginalConstructor()->getMock();
        /** @var CompositeRepository $installedRepo */
        $installedRepo = $this->getMockBuilder('Composer\Repository\CompositeRepository')->disableOriginalConstructor()->getMock();
        /** @var Request $request */
//        $request = $this->getMockBuilder('Composer\DependencyResolver\Request')->disableOriginalConstructor()->getMock();
        $operations = [$this->getMockBuilder('Composer\DependencyResolver\Operation\OperationInterface')->getMock()];

        return new PackageEvent(
            'foo-event',
            $this->composer,
            $this->io,
            true,
            $installedRepo,
            $operations,
            $this->operation
        );
    }
}
