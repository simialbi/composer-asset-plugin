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
use Fxp\Composer\AssetPlugin\Repository\Vcs\SvnDriver;

/**
 * Tests of vcs svn repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class SvnDriverTest extends \PHPUnit\Framework\TestCase
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
                'secure-http' => false,
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
            ['npm', 'package.json', '1234'],
            ['npm', 'package.json', '/@1234'],
            ['bower', 'bower.json', '1234'],
            ['bower', 'bower.json', '/@1234']
        ];
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     * @param string $identifier
     */
    public function testPublicRepositoryWithEmptyComposer(string $type, string $filename, string $identifier)
    {
        $repoUrl = 'svn://example.tld/composer-test/repo-name/trunk';
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
        $driver = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
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
     * @param string $identifier
     */
    public function testPrivateRepositoryWithEmptyComposer(string $type, string $filename, string $identifier)
    {
        $this->config->merge([
            'config' => [
                'http-basic' => [
                    'example.tld' => [
                        'username' => 'peter',
                        'password' => 'quill',
                    ],
                ],
            ],
        ]);

        $repoBaseUrl = 'svn://example.tld/composer-test/repo-name';
        $repoUrl = $repoBaseUrl . '/trunk';
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
        $driver = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
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
     * @param string $identifier
     */
    public function testPublicRepositoryWithCodeCache(string $type, string $filename, string $identifier)
    {
        $repoBaseUrl = 'svn://example.tld/composer-test/repo-name';
        $repoUrl = $repoBaseUrl . '/trunk';
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
            ->willReturnCallback(function ($value) {
                return \is_string($value) ? preg_split('{\r?\n}', $value) : [];
            });
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output) use ($repoBaseUrl, $identifier, $repoConfig) {
                if ($command === sprintf('svn cat --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s/%s', $repoBaseUrl, $identifier, $repoConfig['filename'])))
                    || $command === sprintf('svn cat --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s%s', $repoBaseUrl, $repoConfig['filename'], trim($identifier, '/'))))) {
                    $output('out', '{"name": "foo"}');
                } elseif ($command === sprintf('svn info --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s/', $repoBaseUrl, $identifier)))
                    || $command === sprintf('svn info --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s', $repoBaseUrl, trim($identifier, '/'))))) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $value = [
                        'Last Changed Rev: ' . $identifier,
                        'Last Changed Date: ' . $date->format('Y-m-d H:i:s O') . ' (' . $date->format('l, j F Y') . ')',
                    ];

                    $output('out', implode(PHP_EOL, $value));
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver->initialize();
        $composer1 = $driver->getComposerInformation($identifier);
        $composer2 = $driver->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
        self::assertArrayHasKey('time', $composer1);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     * @param string $identifier
     */
    public function testPublicRepositoryWithFilesystemCache(string $type, string $filename, string $identifier)
    {
        $repoBaseUrl = 'svn://example.tld/composer-test/repo-name';
        $repoUrl = $repoBaseUrl . '/trunk';
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
            ->willReturnCallback(function ($value) {
                return \is_string($value) ? preg_split('{\r?\n}', $value) : [];
            });
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output) use ($repoBaseUrl, $identifier, $repoConfig) {
                if ($command === sprintf('svn cat --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s/%s', $repoBaseUrl, $identifier, $repoConfig['filename'])))
                    || $command === sprintf('svn cat --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s%s', $repoBaseUrl, $repoConfig['filename'], trim($identifier, '/'))))) {
                    $output('out', '{"name": "foo"}');
                } elseif ($command === sprintf('svn info --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s/', $repoBaseUrl, $identifier)))
                    || $command === sprintf('svn info --non-interactive  %s', ProcessExecutor::escape(sprintf('%s/%s', $repoBaseUrl, trim($identifier, '/'))))) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $value = [
                        'Last Changed Rev: ' . $identifier,
                        'Last Changed Date: ' . $date->format('Y-m-d H:i:s O') . ' (' . $date->format('l, j F Y') . ')',
                    ];

                    $output('out', implode(PHP_EOL, $value));
                }

                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver1 = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver2 = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver1->initialize();
        $driver2->initialize();
        $composer1 = $driver1->getComposerInformation($identifier);
        $composer2 = $driver2->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
        self::assertArrayHasKey('time', $composer1);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     * @param string $identifier
     */
    public function testPublicRepositoryWithInvalidUrl(string $type, string $filename, string $identifier)
    {
        self::expectException('\Composer\Downloader\TransportException');
        $repoUrl = 'svn://example.tld/composer-test/repo-name/trunk';
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
            ->willReturnCallback(function ($command) {
                return 0 === strpos($command, 'svn cat ') ? 1 : 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver->initialize();
        $driver->getComposerInformation($identifier);
    }

    /**
     * @return array
     */
    public function getSupportsUrls(): array
    {
        return [
            ['svn://example.tld/trunk', true, 'svn://example.tld/trunk'],
            ['svn+ssh://example.tld/trunk', true, 'svn+ssh://example.tld/trunk'],
            ['svn://svn.example.tld/trunk', true, 'svn://svn.example.tld/trunk'],
            ['svn+ssh://svn.example.tld/trunk', true, 'svn+ssh://svn.example.tld/trunk'],
            ['svn+http://svn.example.tld/trunk', true, 'http://svn.example.tld/trunk'],
            ['svn+https://svn.example.tld/trunk', true, 'https://svn.example.tld/trunk'],
            ['http://example.tld/svn/trunk', true, 'http://example.tld/svn/trunk'],
            ['https://example.tld/svn/trunk', true, 'https://example.tld/svn/trunk'],
            ['http://example.tld/sub', false, null],
            ['https://example.tld/sub', false, null]
        ];
    }

    /**
     * @dataProvider getSupportsUrls
     *
     * @param string $url
     * @param string $supperted
     * @param string $urlUsed
     */
    public function testSupports(string $url, string $supperted, string $urlUsed)
    {
        /** @var IOInterface $io */
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        self::assertSame($supperted, SvnDriver::supports($io, $this->config, $url, false));

        if (!$supperted) {
            return;
        }
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function () {
                return 0;
            });

        $repoConfig = [
            'url' => $url,
            'asset-type' => 'bower',
            'filename' => 'bower.json',
        ];

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new SvnDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $driver->initialize();

        self::assertEquals($urlUsed, $driver->getUrl());
    }
}
