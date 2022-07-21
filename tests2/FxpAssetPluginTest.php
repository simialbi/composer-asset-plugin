<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Installer\InstallerEvent;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\CommandEvent;
use Composer\Repository\RepositoryManager;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use Composer\Util\ProcessExecutor;
use Fxp\Composer\AssetPlugin\FxpAssetPlugin;

/**
 * Tests of asset plugin.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class FxpAssetPluginTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FxpAssetPlugin
     */
    protected FxpAssetPlugin $plugin;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Composer
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Composer $composer;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|IOInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|HttpDownloader
     */
    protected \PHPUnit\Framework\MockObject\MockObject|HttpDownloader $httpDownloader;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface $package;

    protected function setUp(): void
    {
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
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
        $this->package->expects(self::any())
            ->method('getRequires')
            ->willReturn([]);
        $this->package->expects(self::any())
            ->method('getDevRequires')
            ->willReturn([]);
        $this->httpDownloader = new HttpDownloader($io, $config);

        $jsonFile = $this->getMockBuilder('Composer\Json\JsonFile')
            ->setConstructorArgs([
                __DIR__ . 'Fixtures' . DIRECTORY_SEPARATOR . 'package' . DIRECTORY_SEPARATOR . 'composer.json',
                $this->httpDownloader,
                $io
            ])
            ->getMock();
        $installedRepository = $this->getMockBuilder('Composer\Repository\InstalledFilesystemRepository')
            ->setConstructorArgs([$jsonFile])
            ->getMock();
        $installedRepository->expects(self::any())
            ->method('getPackages')
            ->willReturn([]);
        /** @var IOInterface $io */
        /** @var Config $config */
        $processExecutor = new ProcessExecutor($io);
        $loop = new Loop($this->httpDownloader, $processExecutor);
        $rm = new RepositoryManager($io, $config, $this->httpDownloader);
        $rm->setLocalRepository($installedRepository);
        $im = new InstallationManager($loop, $io);

        $factory = $this->getMockBuilder('Composer\Factory')->getMock();
        $composer = new Composer();
        $factory->expects(self::any())
            ->method('createComposer')
            ->with(
                self::equalTo($io),
                self::equalTo($config),
                self::equalTo(false),
                self::equalTo(null),
                self::equalTo(true),
                self::equalTo(false)
            )
            ->willReturn($composer);
//        $composer = $factory->createComposer($io, $config);
//        var_dump(get_class($composer)); exit;

//        $composer = Factory::create($io, $config);
//        $this->registerMockObject();
        $composer = $this->getMockBuilder('Composer\Composer')->getMock();
//        $composer->setLoop($loop);
//        $composer->setInstallationManager($im);
//        $composer->setRepositoryManager($rm);
//        $composer->setConfig($config);
        $composer->expects(self::any())
            ->method('getRepositoryManager')
            ->willReturn($rm);
        $composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($this->package);
        $composer->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);
        $composer->expects(self::any())
            ->method('getInstallationManager')
            ->willReturn($im);

        $this->plugin = new FxpAssetPlugin();
        $this->composer = $composer;
        $this->io = $io;
    }

    protected function tearDown(): void
    {
        unset($this->plugin, $this->composer, $this->io, $this->httpDownloader, $this->package);

        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir() . '/composer-test-repo-cache');
    }

    public function testAssetRepositories()
    {
        $this->package->expects(self::any())
            ->method('getConfig')
            ->willReturn([
                'fxp-asset' => [
                    'private-bower-registries' => [
                        'my-private-bower-server' => 'https://my-private-bower-server.tld/packages',
                    ]
                ]
            ]);

        $this->plugin->activate($this->composer, $this->io);
        $repos = $this->composer->getRepositoryManager()->getRepositories();

        self::assertCount(3, $repos);
        foreach ($repos as $repo) {
            self::assertInstanceOf('Composer\Repository\ComposerRepository', $repo);
        }
    }

    /**
     * @dataProvider getDataForAssetVcsRepositories
     *
     * @param string $type
     */
    public function testAssetVcsRepositories(string $type)
    {
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn([]);

        $this->plugin->activate($this->composer, $this->io);
        $rm = $this->composer->getRepositoryManager();
        $repo = $rm->createRepository($type, [
            'type' => $type,
            'url' => 'http://foo.tld',
            'name' => 'foo',
        ]);

        self::assertInstanceOf('Composer\Repository\VcsRepository', $repo);
    }

    public function getDataForAssetVcsRepositories(): array
    {
        return [
            ['npm-vcs'],
            ['npm-git'],
            ['npm-github'],

            ['bower-vcs'],
            ['bower-git'],
            ['bower-github']
        ];
    }

    public function testAssetRepositoryWithValueIsNotArray()
    {
        self::expectException('\UnexpectedValueException');
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                'invalid_repo',
            ]]);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testAssetRepositoryWithInvalidType()
    {
        self::expectException('\UnexpectedValueException');
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                [],
            ]]);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testAssetRepositoryWithInvalidTypeFormat()
    {
        self::expectException('\UnexpectedValueException');
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                ['type' => 'invalid_type'],
            ]]);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testAssetRepositoryWithInvalidUrl()
    {
        self::expectException('\UnexpectedValueException');
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                ['type' => 'npm-vcs'],
            ]]);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testAssetRepository()
    {
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                ['type' => 'npm-vcs', 'url' => 'http://foo.tld', 'name' => 'foo'],
            ]]);

        $this->plugin->activate($this->composer, $this->io);
        $repos = $this->composer->getRepositoryManager()->getRepositories();

        self::assertCount(3, $repos);
        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Repository\AssetVcsRepository', $repos[2]);
    }

    public function testAssetRepositoryWithAlreadyExistRepositoryName()
    {
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                ['type' => 'npm-vcs', 'url' => 'http://foo.tld', 'name' => 'foo'],
                ['type' => 'npm-vcs', 'url' => 'http://foo.tld', 'name' => 'foo']
            ]]);

        $this->plugin->activate($this->composer, $this->io);
        $repos = $this->composer->getRepositoryManager()->getRepositories();

        self::assertCount(3, $repos);
        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Repository\AssetVcsRepository', $repos[2]);
    }

    public function testAssetPackageWithoutPackage()
    {
        self::expectException('\UnexpectedValueException');
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                ['type' => 'package']
            ]]);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testAssetPackageWithInvalidPackage()
    {
        self::expectException('\UnexpectedValueException');
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                ['type' => 'package', 'package' => ['key' => 'value']],
            ]]);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testAssetPackageRepositories()
    {
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn(['asset-repositories' => [
                [
                    'type' => 'package',
                    'package' => [
                        'name' => 'foo',
                        'type' => 'ASSET-asset-library',
                        'version' => '0.0.0.0',
                        'dist' => [
                            'url' => 'foo.tld/bar',
                            'type' => 'file'
                        ]
                    ]
                ],
            ]]);

        $rm = $this->composer->getRepositoryManager();
        $rm->setRepositoryClass('package', 'Composer\Repository\PackageRepository');
        $this->plugin->activate($this->composer, $this->io);
        $repos = $this->composer->getRepositoryManager()->getRepositories();

        self::assertCount(3, $repos);
        self::assertInstanceOf('Composer\Repository\PackageRepository', $repos[2]);
    }

    public function testOptionsForAssetRegistryRepositories()
    {
        $this->package->expects(self::any())
            ->method('getConfig')
            ->willReturn([
                'fxp-asset' => [
                    'registry-options' => [
                        'npm-option1' => 'value 1',
                        'bower-option1' => 'value 2',
                    ]
                ]
            ]);
        self::assertInstanceOf('Composer\Package\RootPackageInterface', $this->package);

        $this->plugin->activate($this->composer, $this->io);
    }

    public function testSubscribeEvents()
    {
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn([]);

        self::assertCount(2, $this->plugin->getSubscribedEvents());
        self::assertCount(0, $this->composer->getRepositoryManager()->getRepositories());

        /** @var InstallerEvent|\PHPUnit\Framework\MockObject\MockObject $eventInstaller */
        $eventInstaller = $this->getMockBuilder('Composer\Installer\InstallerEvent')
            ->disableOriginalConstructor()
            ->getMock();
        /** @var CommandEvent|\PHPUnit\Framework\MockObject\MockObject $eventCommand */
        $eventCommand = $this->getMockBuilder('Composer\Plugin\CommandEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $eventCommand->expects(self::any())
            ->method('getCommandName')
            ->willReturn('show');

        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->onPluginCommand($eventCommand);
        $this->plugin->onPreDependenciesSolving($eventInstaller);
    }

    public function testAssetInstallers()
    {
        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn([]);

        $this->plugin->activate($this->composer, $this->io);
        $im = $this->composer->getInstallationManager();

        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Installer\BowerInstaller', $im->getInstaller('bower-asset-library'));
        self::assertInstanceOf('Fxp\Composer\AssetPlugin\Installer\AssetInstaller', $im->getInstaller('npm-asset-library'));
    }

    public function testGetConfig()
    {
        $this->plugin->activate($this->composer, $this->io);

        $config = $this->plugin->getConfig();
        self::assertInstanceOf(\Fxp\Composer\AssetPlugin\Config\Config::class, $config);
    }
}
