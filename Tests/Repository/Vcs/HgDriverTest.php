<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Repository\Vcs;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Fxp\Composer\AssetPlugin\Repository\Vcs\HgDriver;

/**
 * Tests of vcs mercurial repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class HgDriverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config
     */
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->config->merge([
            'config' => [
                'home' => sys_get_temp_dir() . '/composer-test',
                'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-cache',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->removeDirectory(sys_get_temp_dir() . '/composer-test');
        $fs->removeDirectory(sys_get_temp_dir() . '/composer-test-cache');
    }

    public function getAssetTypes(): array
    {
        return [
            ['npm', 'package.json'],
            ['bower', 'bower.json']
        ];
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithEmptyComposer(string $type, string $filename)
    {
        $repoUrl = 'https://bitbucket.org/composer-test/repo-name';
        $identifier = 'v0.0.0';
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        ];
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function () {
                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new HgDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver->initialize();

        $validEmpty = [
            '_nonexistent_package' => true,
        ];

        self::assertSame($validEmpty, $driver->getComposerInformation($identifier));
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithCodeCache(string $type, string $filename)
    {
        $repoUrl = 'https://bitbucket.org/composer-test/repo-name';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('hg cat -r %s %s', ProcessExecutor::escape($identifier), $repoConfig['filename'])) {
                    $output = '{"name": "foo"}';
                } elseif (false !== strpos($command, 'hg log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->format(\DateTime::RFC3339);
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new HgDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver->initialize();
        $composer1 = $driver->getComposerInformation($identifier);
        $composer2 = $driver->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithFilesystemCache(string $type, string $filename)
    {
        $repoUrl = 'https://bitbucket.org/composer-test/repo-name';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('hg cat -r %s %s', ProcessExecutor::escape($identifier), $repoConfig['filename'])) {
                    $output = '{"name": "foo"}';
                } elseif (false !== strpos($command, 'hg log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->format(\DateTime::RFC3339);
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver1 = new HgDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver2 = new HgDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver1->initialize();
        $driver2->initialize();
        $composer1 = $driver1->getComposerInformation($identifier);
        $composer2 = $driver2->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
    }

    /**
     * @param object $object
     * @param string $attribute
     * @param mixed $value
     */
    protected function setAttribute(object $object, string $attribute, mixed $value)
    {
        $attr = new \ReflectionProperty($object, $attribute);
        $attr->setAccessible(true);
        $attr->setValue($object, $value);
    }
}
