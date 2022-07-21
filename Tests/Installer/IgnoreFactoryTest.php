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
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;
use Fxp\Composer\AssetPlugin\Config\ConfigBuilder;
use Fxp\Composer\AssetPlugin\Installer\IgnoreFactory;
use Fxp\Composer\AssetPlugin\Installer\IgnoreManager;

/**
 * Tests of ignore factory.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class IgnoreFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Composer $composer;

    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Config $config;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface $rootPackage;

    /**
     * @var PackageInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected PackageInterface|\PHPUnit\Framework\MockObject\MockObject $package;

    protected function setUp(): void
    {
        $this->config = $this->getMockBuilder('Composer\Config')->getMock();
        $this->config->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-repo-cache',
                    'vendor-dir' => sys_get_temp_dir() . '/composer-test/vendor',
                    default => null,
                };
            });

        $this->rootPackage = $this->getMockBuilder('Composer\Package\RootPackageInterface')->getMock();
        $this->package = $this->getMockBuilder('Composer\Package\PackageInterface')->getMock();
        $this->package->expects(self::any())
            ->method('getName')
            ->willReturn('foo-asset/foo');

        $this->composer = $this->getMockBuilder('Composer\Composer')->getMock();
        $this->composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($this->rootPackage);
        $this->composer->expects(self::any())
            ->method('getConfig')
            ->willReturn($this->config);
    }

    protected function tearDown(): void
    {
        unset($this->composer, $this->config, $this->rootPackage, $this->package);

        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir() . '/composer-test-repo-cache');
        $fs->remove(sys_get_temp_dir() . '/composer-test');
    }

    public function testCreateWithoutIgnoreFiles()
    {
        $config = ConfigBuilder::build($this->composer);
        $manager = IgnoreFactory::create($config, $this->composer, $this->package);

        self::assertTrue($manager->isEnabled());
        self::assertFalse($manager->hasPattern());
        $this->validateInstallDir($manager, $this->config->get('vendor-dir') . '/' . $this->package->getName());
    }

    public function testCreateWithIgnoreFiles()
    {
        $config = [
            'fxp-asset' => [
                'ignore-files' => [
                    'foo-asset/foo' => [
                        'PATTERN',
                    ],
                    'foo-asset/bar' => [],
                ],
            ],
        ];

        $this->rootPackage->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);

        $config = ConfigBuilder::build($this->composer);
        $manager = IgnoreFactory::create($config, $this->composer, $this->package);

        self::assertTrue($manager->isEnabled());
        self::assertTrue($manager->hasPattern());
        $this->validateInstallDir($manager, $this->config->get('vendor-dir') . '/' . $this->package->getName());
    }

    public function testCreateWithCustomInstallDir()
    {
        $installDir = 'web/assets/';
        $config = ConfigBuilder::build($this->composer);
        $manager = IgnoreFactory::create($config, $this->composer, $this->package, $installDir);

        self::assertTrue($manager->isEnabled());
        self::assertFalse($manager->hasPattern());
        $this->validateInstallDir($manager, rtrim($installDir, '/'));
    }

    public function testCreateWithEnablingOfIgnoreFiles()
    {
        $config = [
            'fxp-asset' => [
                'ignore-files' => [
                    'foo-asset/foo' => true,
                    'foo-asset/bar' => [],
                ],
            ],
        ];

        $this->rootPackage->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);

        $config = ConfigBuilder::build($this->composer);
        $manager = IgnoreFactory::create($config, $this->composer, $this->package);

        self::assertTrue($manager->isEnabled());
        self::assertFalse($manager->hasPattern());
        $this->validateInstallDir($manager, $this->config->get('vendor-dir') . '/' . $this->package->getName());
    }

    public function testCreateWithDisablingOfIgnoreFiles()
    {
        $config = [
            'fxp-asset' => [
                'ignore-files' => [
                    'foo-asset/foo' => false,
                    'foo-asset/bar' => [],
                ],
            ],
        ];

        $this->rootPackage->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);

        $config = ConfigBuilder::build($this->composer);
        $manager = IgnoreFactory::create($config, $this->composer, $this->package);

        self::assertFalse($manager->isEnabled());
        self::assertFalse($manager->hasPattern());
        $this->validateInstallDir($manager, $this->config->get('vendor-dir') . '/' . $this->package->getName());
    }

    public function testCreateWithCustomIgnoreSection()
    {
        $config = [
            'fxp-asset' => [
                'custom-ignore-files' => [
                    'foo-asset/foo' => [
                        'PATTERN',
                    ],
                    'foo-asset/bar' => [],
                ],
            ],
        ];

        $this->rootPackage->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);

        $config = ConfigBuilder::build($this->composer);
        $manager = IgnoreFactory::create($config, $this->composer, $this->package, null, 'custom-ignore-files');

        self::assertTrue($manager->isEnabled());
        self::assertTrue($manager->hasPattern());
        $this->validateInstallDir($manager, $this->config->get('vendor-dir') . '/' . $this->package->getName());
    }

    /**
     * @param string $installDir
     */
    protected function validateInstallDir(IgnoreManager $manager, string $installDir)
    {
        $ref = new \ReflectionClass($manager);
        $prop = $ref->getProperty('installDir');
        $prop->setAccessible(true);

        self::assertSame($installDir, $prop->getValue($manager));
    }
}
