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
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;
use Fxp\Composer\AssetPlugin\Repository\Vcs\GitBitbucketDriver;
use Fxp\Composer\AssetPlugin\Tests\ComposerUtil;

/**
 * Tests of vcs git bitbucket repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class GitBitbucketDriverTest extends \PHPUnit\Framework\TestCase
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
    public function testPublicRepositoryWithComposer(string $type, string $filename)
    {
        $repoBaseUrl = 'https://bitbucket.org/composer-test/repo-name';
        $repoUrl = $repoBaseUrl . '.git';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $remoteFilesystem = $this->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->setConstructorArgs([$io])
            ->getMock();

        $remoteFilesystem->expects(self::any())
            ->method('getContents')
            ->withConsecutive(
                [
                    'bitbucket.org',
                    'https://api.bitbucket.org/2.0/repositories/composer-test/repo-name?fields=-project%2C-owner',
                    false,
                ],
                [
                    'bitbucket.org',
                    ComposerUtil::getValueByVersion([
                        '^1.7.0' => 'https://api.bitbucket.org/2.0/repositories/composer-test/repo-name?fields=mainbranch',
                        '1.6.*' => 'https://api.bitbucket.org/1.0/repositories/composer-test/repo-name/main-branch',
                    ]),
                    false,
                ],
                [
                    'bitbucket.org',
                    'https://api.bitbucket.org/1.0/repositories/composer-test/repo-name/src/v0.0.0/' . $filename,
                    false,
                ]
            )
            ->willReturnOnConsecutiveCalls(
                '{"scm":"git","website":"","has_wiki":false,"name":"repo","links":{"branches":{"href":"https:\/\/api.bitbucket.org\/2.0\/repositories\/composer-test\/repo-name\/refs\/branches"},"tags":{"href":"https:\/\/api.bitbucket.org\/2.0\/repositories\/composer-test\/repo-name\/refs\/tags"},"clone":[{"href":"https:\/\/user@bitbucket.org\/composer-test\/repo-name.git","name":"https"},{"href":"ssh:\/\/git@bitbucket.org\/composer-test\/repo-name.git","name":"ssh"}],"html":{"href":"https:\/\/bitbucket.org\/composer-test\/repo-name"}},"language":"php","created_on":"2015-02-18T16:22:24.688+00:00","updated_on":"2016-05-17T13:20:21.993+00:00","is_private":true,"has_issues":false}',
                '{"name": "test_master"}',
                '{"name": "composer-test/repo-name","description": "test repo","license": "GPL","authors": [{"name": "Name","email": "local@domain.tld"}],"require": {"creator/package": "^1.0"},"require-dev": {"phpunit/phpunit": "~4.8"}}'
            );

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $driver = new GitBitbucketDriver($repoConfig, $io, $this->config, null, $remoteFilesystem);
        $driver->initialize();

        $expectedRootIdentifier = ComposerUtil::getValueByVersion([
            '^1.7.0' => 'master',
            '1.6.*' => 'test_master'
        ]);
        self::assertEquals($expectedRootIdentifier, $driver->getRootIdentifier());

        $dist = $driver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals($this->getScheme($repoBaseUrl) . '/get/SOMESHA.zip', $dist['url']);
        self::assertEquals($sha, $dist['reference']);

        $source = $driver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoUrl, $source['url']);
        self::assertEquals($sha, $source['reference']);

        $driver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithEmptyComposer(string $type, string $filename)
    {
        $repoBaseUrl = 'https://bitbucket.org/composer-test/repo-name';
        $repoUrl = $repoBaseUrl . '.git';
        $repoApiUrl = 'https://api.bitbucket.org/1.0/repositories/composer-test/repo-name';
        $identifier = 'v0.0.0';
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $remoteFilesystem = $this->getMockBuilder('Composer\Util\RemoteFilesystem')
            ->setConstructorArgs([$io])
            ->getMock();

        $remoteFilesystem->expects(self::at(0))
            ->method('getContents')
            ->with(
                self::equalTo('bitbucket.org'),
                self::equalTo($repoApiUrl . '/src/' . $identifier . '/' . $filename),
                self::equalTo(false)
            )
            ->will(self::throwException(new TransportException('Not Found', 404)));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $driver = new GitBitbucketDriver($repoConfig, $io, $this->config, null, $remoteFilesystem);
        $driver->initialize();

        $validEmpty = [
            '_nonexistent_package' => true,
        ];

        self::assertSame($validEmpty, $driver->getComposerInformation($identifier));
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

    /**
     * Creates the json composer content.
     *
     * @param array $content The composer content
     * @param string $name The name of repository
     *
     * @return string The json content
     */
    protected function createJsonComposer(array $content, string $name = 'repo-name')
    {
        return json_encode(array_merge_recursive($content, [
            'name' => $name,
        ]));
    }

    /**
     * @param array $content The composer content
     * @param string $name The name of repository
     *
     * @return string The API return value with the json content
     */
    protected function createApiJsonWithRepoData(array $content, string $name = 'repo-name'): string
    {
        $composerContent = $this->createJsonComposer($content, $name);

        return json_encode(
            [
                'node' => 'nodename',
                'path' => '/path/to/file',
                'data' => $composerContent,
                'size' => \strlen($composerContent),
            ]
        );
    }

    /**
     * Get the url with https or http protocol depending on SSL support.
     *
     * @param string $url
     *
     * @return string The correct url
     */
    protected function getScheme(string $url): string
    {
        if (\extension_loaded('openssl')) {
            return $url;
        }

        return str_replace('https:', 'http:', $url);
    }
}
