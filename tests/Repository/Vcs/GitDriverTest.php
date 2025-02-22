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
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\Vcs\GitDriver;

/**
 * Tests of vcs git repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class GitDriverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var AssetRepositoryManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private \PHPUnit\Framework\MockObject\MockObject|AssetRepositoryManager $assetRepositoryManager;

    protected function setUp(): void
    {
        $this->assetRepositoryManager = $this->getMockBuilder(AssetRepositoryManager::class)->disableOriginalConstructor()->getMock();
        $this->config = new Config();
        $this->config->merge([
            'config' => [
                'home' => sys_get_temp_dir() . '/composer-test',
                'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-cache',
                'cache-vcs-dir' => sys_get_temp_dir() . '/composer-test-cache'
            ]
        ]);

        // Mock for skip asset
        $fs = new Filesystem();
        $fs->ensureDirectoryExists(sys_get_temp_dir() . '/composer-test-cache/https---github.com-fxpio-composer-asset-plugin.git');
        file_put_contents(sys_get_temp_dir() . '/composer-test-cache/https---github.com-fxpio-composer-asset-plugin.git/config', '');
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();

        if (file_exists(sys_get_temp_dir() . '/composer-test-cache')) {
            chmod(sys_get_temp_dir() . '/composer-test-cache', 0777);
        }

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
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        $repoUrl = 'https://github.com/fxpio/composer-asset-plugin';
        $identifier = 'v0.0.0';
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager
        ];

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
        $gitDriver = new GitDriver($repoConfig, $io, $this->config, $process, null);
        $gitDriver->initialize();

        $validEmpty = [
            '_nonexistent_package' => true
        ];

        self::assertSame($validEmpty, $gitDriver->getComposerInformation($identifier));
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testLocalRepositoryWithEmptyComposer(string $type, string $filename)
    {
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        $path = sys_get_temp_dir() . '/composer-test/local-repository.git';
        $fs = new Filesystem();
        $fs->ensureDirectoryExists($path);

        $repoUrl = 'file://' . $path;
        $identifier = 'v0.0.0';
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager
        ];

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
        $gitDriver = new GitDriver($repoConfig, $io, $this->config, $process, null);
        $gitDriver->initialize();

        $validEmpty = [
            '_nonexistent_package' => true
        ];

        self::assertSame($validEmpty, $gitDriver->getComposerInformation($identifier));
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithSkipUpdate(string $type, string $filename)
    {
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config(['git-skip-update' => '1 hour']));

        $repoUrl = 'https://github.com/fxpio/composer-asset-plugin.git';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager,
        ];

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('git show %s', sprintf('%s:%s', escapeshellarg($identifier), $repoConfig['filename']))) {
                    $output = '{"name": "foo"}';
                } elseif (str_contains($command, 'git log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->getTimestamp();
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitDriver1 = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitDriver1->initialize();

        $gitDriver2 = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitDriver2->initialize();

        $validEmpty = [
            '_nonexistent_package' => true,
        ];

        $composer1 = $gitDriver1->getComposerInformation($identifier);
        $composer2 = $gitDriver2->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
        self::assertNotSame($validEmpty, $composer1);
        self::assertNotSame($validEmpty, $composer2);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithCodeCache(string $type, string $filename)
    {
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        $repoUrl = 'https://github.com/fxpio/composer-asset-plugin.git';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('git show %s', sprintf('%s:%s', escapeshellarg($identifier), $repoConfig['filename']))) {
                    $output = '{"name": "foo"}';
                } elseif (str_contains($command, 'git log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->getTimestamp();
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitDriver = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitDriver->initialize();
        $composer1 = $gitDriver->getComposerInformation($identifier);
        $composer2 = $gitDriver->getComposerInformation($identifier);

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
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        $repoUrl = 'https://github.com/fxpio/composer-asset-plugin.git';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('git show %s', sprintf('%s:%s', escapeshellarg($identifier), $repoConfig['filename']))) {
                    $output = '{"name": "foo"}';
                } elseif (str_contains($command, 'git log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->getTimestamp();
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitDriver1 = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitDriver2 = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitDriver1->initialize();
        $gitDriver2->initialize();
        $composer1 = $gitDriver1->getComposerInformation($identifier);
        $composer2 = $gitDriver2->getComposerInformation($identifier);

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
    public function testPublicRepositoryWithNotWritableFilesystemCache(string $type, string $filename)
    {
        self::expectException('\RuntimeException');
        self::expectExceptionMessageMatches('/Can not clone https:\/\/github.com\/fxpio\/composer-asset-plugin.git to access package information. The "([\s\S]+)" directory is not writable by the current user./');
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        chmod($this->config->get('cache-vcs-dir'), 0400);
        $isWritable = is_writable($this->config->get('cache-vcs-dir'));
        self::assertFalse($isWritable);

        $repoUrl = 'https://github.com/fxpio/composer-asset-plugin.git';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitDriver = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);

        $gitDriver->initialize();
        $gitDriver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithInvalidSShUrl(string $type, string $filename)
    {
        self::expectException('\InvalidArgumentException');
        self::expectExceptionMessage('The source URL ssh://git@github.com:port/fxpio/composer-asset-plugin.git is invalid, ssh URLs should have a port number after ":".');
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        $repoUrl = 'ssh://git@github.com:port/fxpio/composer-asset-plugin.git';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitDriver = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);

        $gitDriver->initialize();
        $gitDriver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithFailedToUpdatePackage(string $type, string $filename)
    {
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn(new \Fxp\Composer\AssetPlugin\Config\Config([]));

        $repoUrl = 'https://github.com/fxpio/composer-asset-plugin.git';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
            'asset-repository-manager' => $this->assetRepositoryManager,
        ];
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::at(0))
            ->method('writeError')
            ->with('<error>Failed to update https://github.com/fxpio/composer-asset-plugin.git, package information from this repository may be outdated</error>');

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::atLeastOnce())
            ->method('splitLines')
            ->willReturn([]);
        $process->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnCallback(static function ($command, &$output = null) {
                if ('git rev-parse --git-dir' === $command) {
                    $output = '.';
                } elseif ('git remote set-url origin ' . escapeshellarg('https://github.com/fxpio/composer-asset-plugin.git') . ' && git remote update --prune origin' === $command) {
                    throw new \Exception('Skip sync mirror');
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitDriver = new GitDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitDriver->initialize();
        $gitDriver->getComposerInformation($identifier);
    }

    protected function setAttribute($object, $attribute, $value)
    {
        $attr = new \ReflectionProperty($object, $attribute);
        $attr->setAccessible(true);
        $attr->setValue($object, $value);
    }
}
