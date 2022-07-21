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

use Composer\Cache;
use Composer\Config;
use Composer\Config\ConfigSourceInterface;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\Vcs\GitHubDriver;

/**
 * Tests of vcs github repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class GitHubDriverTest extends \PHPUnit\Framework\TestCase
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
        $this->config = new Config();
        $this->config->merge([
            'config' => [
                'home' => sys_get_temp_dir() . '/composer-test',
                'cache-repo-dir' => sys_get_temp_dir() . '/composer-test-cache'
            ]
        ]);

        $assetConfig = new \Fxp\Composer\AssetPlugin\Config\Config([]);

        $this->assetRepositoryManager = $this->getMockBuilder(AssetRepositoryManager::class)
            ->disableOriginalConstructor()->getMock();
        $this->assetRepositoryManager->expects(self::any())
            ->method('getConfig')
            ->willReturn($assetConfig);
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
    public function testPrivateRepository(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $repoSshUrl = 'git@github.com:composer-test/repo-name.git';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();

        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('execute')
            ->willReturn(1);

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));

        $io->expects(self::once())
            ->method('askAndHideAnswer')
            ->with(self::equalTo('Token (hidden): '))
            ->willReturn('sometoken');

        $io->expects(self::any())
            ->method('setAuthentication')
            ->with(self::equalTo('github.com'), self::matchesRegularExpression('{sometoken|abcdef}'), self::matchesRegularExpression('{x-oauth-basic}'));

//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://github.com/composer-test/repo-name'), self::equalTo(false))
//            ->willReturn('');
//
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getContents')
//            ->willReturn('');
//
//        $remoteFilesystem->expects(self::at(3))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));
//
//        $remoteFilesystem->expects(self::at(4))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/'), self::equalTo(false))
//            ->willReturn('{}');
//
//        $remoteFilesystem->expects(self::at(5))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master', 'private' => true]));

        $configSource = $this->getMockBuilder('Composer\Config\ConfigSourceInterface')->getMock();
        $authConfigSource = $this->getMockBuilder('Composer\Config\ConfigSourceInterface')->getMock();

        /* @var ConfigSourceInterface $configSource */
        /* @var ConfigSourceInterface $authConfigSource */
        /* @var ProcessExecutor $process */
        /* @var RemoteFilesystem $remoteFilesystem */
        /* @var IOInterface $io */

        $this->config->setConfigSource($configSource);
        $this->config->setAuthConfigSource($authConfigSource);

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName
        ];

        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);

        self::assertEquals('test_master', $gitHubDriver->getRootIdentifier());

        $dist = $gitHubDriver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals('https://api.github.com/repos/composer-test/repo-name/zipball/SOMESHA', $dist['url']);
        self::assertEquals('SOMESHA', $dist['reference']);

        $source = $gitHubDriver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoSshUrl, $source['url']);
        self::assertEquals('SOMESHA', $source['reference']);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepository(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();

        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('execute')
            ->willReturn(1);//        $remoteFilesystem = $this->getMockBuilder('Composer\Util\RemoteFilesystem')
//            ->setConstructorArgs([$io])
//            ->getMock();
//
//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];
        $repoUrl = 'https://github.com/composer-test/repo-name.git';

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);

        self::assertEquals('test_master', $gitHubDriver->getRootIdentifier());

        $dist = $gitHubDriver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals('https://api.github.com/repos/composer-test/repo-name/zipball/SOMESHA', $dist['url']);
        self::assertEquals($sha, $dist['reference']);

        $source = $gitHubDriver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoUrl, $source['url']);
        self::assertEquals($sha, $source['reference']);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepository2(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'feature/3.2-foo';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
//        $remoteFilesystem = $this->getMockBuilder('Composer\Util\RemoteFilesystem')
//            ->setConstructorArgs([$io])
//            ->getMock();
//
//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));
//
//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/contents/' . $filename . '?ref=feature%2F3.2-foo'), self::equalTo(false))
//            ->willReturn('{"encoding":"base64","content":"' . base64_encode('{"support": {"source": "' . $repoUrl . '" }}') . '"}');
//
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/commits/feature%2F3.2-foo'), self::equalTo(false))
//            ->willReturn('{"commit": {"committer":{ "date": "2012-09-10"}}}');


        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('execute')
            ->willReturn(1);

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];
        $repoUrl = 'https://github.com/composer-test/repo-name.git';

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);

        self::assertEquals('test_master', $gitHubDriver->getRootIdentifier());

        $dist = $gitHubDriver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals('https://api.github.com/repos/composer-test/repo-name/zipball/SOMESHA', $dist['url']);
        self::assertEquals($sha, $dist['reference']);

        $source = $gitHubDriver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoUrl, $source['url']);
        self::assertEquals($sha, $source['reference']);

        $gitHubDriver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPrivateRepositoryNoInteraction(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $repoSshUrl = 'git@github.com:composer-test/repo-name.git';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')
            ->disableOriginalConstructor()
            ->getMock();

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(false);


        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));
//
//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://github.com/composer-test/repo-name'), self::equalTo(false))
//            ->willReturn('');
//
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getContents')
//            ->willReturn('');
//
//        $remoteFilesystem->expects(self::at(3))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));

        // clean local clone if present
        $fs = new Filesystem();
        $fs->removeDirectory(sys_get_temp_dir() . '/composer-test');

        $process->expects(self::at(0))
            ->method('execute')
            ->with(self::equalTo('git config github.accesstoken'))
            ->willReturn(1);

        $process->expects(self::at(1))
            ->method('execute')
            ->with(self::stringContains($repoSshUrl))
            ->willReturn(0);

        $process->expects(self::at(2))
            ->method('execute')
            ->with(self::stringContains('git show-ref --tags'));

        $process->expects(self::at(3))
            ->method('splitLines')
            ->willReturn([$sha . ' refs/tags/' . $identifier]);

        $process->expects(self::at(4))
            ->method('execute')
            ->with(self::stringContains('git branch --no-color --no-abbrev -v'));

        $process->expects(self::at(5))
            ->method('splitLines')
            ->willReturn(['  test_master     edf93f1fccaebd8764383dc12016d0a1a9672d89 Fix test & behavior']);

        $process->expects(self::at(6))
            ->method('execute')
            ->with(self::stringContains('git branch --no-color'));

        $process->expects(self::at(7))
            ->method('splitLines')
            ->willReturn(['* test_master']);

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        /** @var ProcessExecutor $process */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        self::assertEquals('test_master', $gitHubDriver->getRootIdentifier());

        $dist = $gitHubDriver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals('https://api.github.com/repos/composer-test/repo-name/zipball/SOMESHA', $dist['url']);
        self::assertEquals($sha, $dist['reference']);

        $source = $gitHubDriver->getSource($identifier);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoSshUrl, $source['url']);
        self::assertEquals($identifier, $source['reference']);

        $source = $gitHubDriver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoSshUrl, $source['url']);
        self::assertEquals($sha, $source['reference']);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testGetComposerInformationWithGitDriver(string $type, string $filename)
    {
        $repoUrl = 'https://github.com/composer-test/repo-name';
        $identifier = 'v0.0.0';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'no-api' => true,
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
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        $validEmpty = [
            '_nonexistent_package' => true,
        ];

        self::assertSame($validEmpty, $gitHubDriver->getComposerInformation($identifier));
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testGetComposerInformationWithCodeCache(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'dev-master';
        $sha = '92bebbfdcde75ef2368317830e54b605bc938123';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);
        $this->setAttribute($gitHubDriver, 'hasIssues', true);

        $composer1 = $gitHubDriver->getComposerInformation($sha);
        $composer2 = $gitHubDriver->getComposerInformation($sha);

        self::assertSame($composer1, $composer2);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testGetComposerInformationWithFilesystemCache(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'dev-master';
        $sha = '92bebbfdcde75ef2368317830e54b605bc938123';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        /** @var IOInterface $io */

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        $gitHubDriver1 = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver2 = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver1->initialize();
        $gitHubDriver2->initialize();
        $this->setAttribute($gitHubDriver1, 'tags', [$identifier => $sha]);
        $this->setAttribute($gitHubDriver1, 'hasIssues', true);
        $this->setAttribute($gitHubDriver2, 'tags', [$identifier => $sha]);
        $this->setAttribute($gitHubDriver2, 'hasIssues', true);

        $composer1 = $gitHubDriver1->getComposerInformation($sha);
        $composer2 = $gitHubDriver2->getComposerInformation($sha);

        self::assertSame($composer1, $composer2);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testGetComposerInformationWithEmptyContent(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
//
//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));
//
//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/contents/' . $filename . '?ref=' . $identifier), self::equalTo(false))
//            ->will(self::throwException(new TransportException('Not Found', 404)));
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/contents/' . $filename . '?ref=' . $identifier), self::equalTo(false))
//            ->will(self::throwException(new TransportException('Not Found', 404)));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        $validEmpty = [
            '_nonexistent_package' => true,
        ];

        self::assertSame($validEmpty, $gitHubDriver->getComposerInformation($identifier));
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     *
     * @expectedException \RuntimeException
     */
    public function testGetComposerInformationWithRuntimeException(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

//        $httpDownloader->expects(self::exactly(1))
//            ->method('get')
//            ->with(self::equalTo($repoApiUrl))
//            ->willReturn();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));
//
//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/contents/' . $filename . '?ref=' . $identifier), self::equalTo(false))
//            ->willReturn('{"encoding":"base64","content":""}');

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        $gitHubDriver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     *
     * @expectedException \RuntimeException
     */
    public function testGetComposerInformationWithTransportException(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));
//
//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/contents/' . $filename . '?ref=' . $identifier), self::equalTo(false))
//            ->will(self::throwException(new TransportException('Mock exception code 404', 404)));
//
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/repos/composer-test/repo-name/contents/' . $filename . '?ref=' . $identifier), self::equalTo(false))
//            ->will(self::throwException(new TransportException('Mock exception code 400', 400)));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        $gitHubDriver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testRedirectUrlRepository(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));
//
//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://github.com/composer-test/repo-name'), self::equalTo(false))
//            ->willReturn('');
//
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getLastHeaders')
//            ->willReturn([
//                'HTTP/1.1 301 Moved Permanently',
//                'Header-parameter: test',
//                'Location: ' . $repoUrl . '-new',
//            ]);
//
//        $remoteFilesystem->expects(self::at(3))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl . '-new'), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];
        $repoUrl = 'https://github.com/composer-test/repo-name.git';

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);

        self::assertEquals('test_master', $gitHubDriver->getRootIdentifier());

        $dist = $gitHubDriver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals('https://api.github.com/repos/composer-test/repo-name/zipball/SOMESHA', $dist['url']);
        self::assertEquals($sha, $dist['reference']);

        $source = $gitHubDriver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoUrl, $source['url']);
        self::assertEquals($sha, $source['reference']);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     *
     * @expectedException \RuntimeException
     */
    public function testRedirectUrlWithNonexistentRepository(string $type, string $filename)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));

        $io->expects(self::once())
            ->method('askAndHideAnswer')
            ->with(self::equalTo('Token (hidden): '))
            ->willReturn('sometoken');

        $io->expects(self::any())
            ->method('setAuthentication')
            ->with(self::equalTo('github.com'), self::matchesRegularExpression('{sometoken|abcdef}'), self::matchesRegularExpression('{x-oauth-basic}'));

//        $remoteFilesystem->expects(self::at(1))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://github.com/composer-test/repo-name'), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));
//
//        $remoteFilesystem->expects(self::at(2))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));
//
//        $remoteFilesystem->expects(self::at(3))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo('https://api.github.com/'), self::equalTo(false))
//            ->willReturn('{}');
//
//        $remoteFilesystem->expects(self::at(4))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));
//
//        $remoteFilesystem->expects(self::at(5))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl . '/contents/' . $filename . '?ref=' . $identifier), self::equalTo(false))
//            ->will(self::throwException(new TransportException('HTTP/1.1 404 Not Found', 404)));

        $configSource = $this->getMockBuilder('Composer\Config\ConfigSourceInterface')->getMock();
        $authConfigSource = $this->getMockBuilder('Composer\Config\ConfigSourceInterface')->getMock();

        /* @var ConfigSourceInterface $configSource */
        /* @var ConfigSourceInterface $authConfigSource */

        $this->config->setConfigSource($configSource);
        $this->config->setAuthConfigSource($authConfigSource);

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $firstNonexistent = false;

        try {
            $gitHubDriver->initialize();
        } catch (TransportException $e) {
            $firstNonexistent = true;
        }

        self::assertTrue($firstNonexistent);

        $gitHubDriver->getComposerInformation($identifier);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testRedirectUrlRepositoryWithCache(string $type, string $filename)
    {
        $originUrl = 'github.com';
        $owner = 'composer-test';
        $repository = 'repo-name';
        $repoUrl = 'http://' . $originUrl . '/' . $owner . '/' . $repository;
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $repoApiUrlNew = $repoApiUrl . '-new';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrlNew), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'test_master']));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];
        $repoUrl = 'https://github.com/composer-test/repo-name.git';

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $cache = new Cache($io, $this->config->get('cache-repo-dir') . '/' . $originUrl . '/' . $owner . '/' . $repository);
        $cache->write('redirect-api', $repoApiUrlNew);

        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);

        self::assertEquals('test_master', $gitHubDriver->getRootIdentifier());

        $dist = $gitHubDriver->getDist($sha);
        self::assertEquals('zip', $dist['type']);
        self::assertEquals('https://api.github.com/repos/composer-test/repo-name/zipball/SOMESHA', $dist['url']);
        self::assertEquals($sha, $dist['reference']);

        $source = $gitHubDriver->getSource($sha);
        self::assertEquals('git', $source['type']);
        self::assertEquals($repoUrl, $source['url']);
        self::assertEquals($sha, $source['reference']);
    }

    public function getDataBranches(): array
    {
        $valid1 = [];
        $git1 = [];
        $valid2 = [
            'master' => '0123456789abcdef0123456789abcdef01234567',
        ];
        $git2 = [
            'master 0123456789abcdef0123456789abcdef01234567 Comment',
        ];
        $valid3 = [
            'gh-pages' => '0123456789abcdef0123456789abcdef01234567',
        ];
        $git3 = [
            'gh-pages 0123456789abcdef0123456789abcdef01234567 Comment',
        ];
        $valid4 = [
            'master' => '0123456789abcdef0123456789abcdef01234567',
            'gh-pages' => '0123456789abcdef0123456789abcdef01234567',
        ];
        $git4 = [
            'master 0123456789abcdef0123456789abcdef01234567 Comment',
            'gh-pages 0123456789abcdef0123456789abcdef01234567 Comment',
        ];

        return [
            ['npm', 'package.json', $valid1, $git1],
            ['npm', 'package.json', $valid2, $git2],
            ['npm', 'package.json', $valid3, $git3],
            ['npm', 'package.json', $valid4, $git4],
            ['bower', 'bower.json', $valid1, $git1],
            ['bower', 'bower.json', $valid2, $git2],
            ['bower', 'bower.json', $valid3, $git3],
            ['bower', 'bower.json', $valid4, $git4]
        ];
    }

    /**
     * @dataProvider getDataBranches
     *
     * @param string $type
     * @param string $filename
     */
    public function testGetBranchesWithGitDriver(string $type, string $filename, array $branches, array $gitBranches)
    {
        $repoUrl = 'https://github.com/composer-test/repo-name';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);
        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'no-api' => true,
        ];

        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn($gitBranches);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function () {
                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        self::assertSame($branches, $gitHubDriver->getBranches());
    }

    /**
     * @dataProvider getDataBranches
     *
     * @param string $type
     * @param string $filename
     */
    public function testGetBranches(string $type, string $filename, array $branches)
    {
        $repoUrl = 'http://github.com/composer-test/repo-name';
        $repoApiUrl = 'https://api.github.com/repos/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';
        $identifier = 'v0.0.0';
        $sha = 'SOMESHA';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();

//        $remoteFilesystem->expects(self::at(0))
//            ->method('getContents')
//            ->with(self::equalTo('github.com'), self::equalTo($repoApiUrl), self::equalTo(false))
//            ->willReturn($this->createJsonComposer(['master_branch' => 'gh-pages']));
//
//        $remoteFilesystem->expects(self::any())
//            ->method('getLastHeaders')
//            ->willReturn([]);

        $githubBranches = [];
        foreach ($branches as $branch => $sha) {
            $githubBranches[] = [
                'ref' => 'refs/heads/' . $branch,
                'object' => [
                    'sha' => $sha,
                ],
            ];
        }

        $remoteFilesystem->expects(self::at(1))
            ->method('getContents')
            ->willReturn(json_encode($githubBranches));

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
        ];

        /** @var IOInterface $io */
        /** @var RemoteFilesystem $remoteFilesystem */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();
        $this->setAttribute($gitHubDriver, 'tags', [$identifier => $sha]);

        self::assertEquals('gh-pages', $gitHubDriver->getRootIdentifier());
        self::assertSame($branches, $gitHubDriver->getBranches());
    }

    /**
     * @dataProvider getDataBranches
     *
     * @param string $type
     * @param string $filename
     */
    public function testNoApi(string $type, string $filename, array $branches, array $gitBranches)
    {
        $repoUrl = 'https://github.com/composer-test/repo-name';
        $packageName = $type . '-asset/repo-name';

        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $io->expects(self::any())
            ->method('isInteractive')
            ->willReturn(true);

        $httpDownloader = $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->setConstructorArgs([$io])
            ->getMock();

        $repoConfig = [
            'url' => $repoUrl,
            'asset-type' => $type,
            'asset-repository-manager' => $this->assetRepositoryManager,
            'filename' => $filename,
            'package-name' => $packageName,
            'vcs-driver-options' => [
                'github-no-api' => true,
            ],
        ];

        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(self::any())
            ->method('splitLines')
            ->willReturn($gitBranches);
        $process->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function () {
                return 0;
            });

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $gitHubDriver = new GitHubDriver($repoConfig, $io, $this->config, $httpDownloader, $process);
        $gitHubDriver->initialize();

        self::assertSame($branches, $gitHubDriver->getBranches());
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
     * @param string $login The username /organization of repository
     *
     * @return string The json content
     */
    protected function createJsonComposer(array $content, string $name = 'repo-name', string $login = 'composer-test'): string
    {
        return json_encode(array_merge_recursive($content, [
            'name' => $name,
            'owner' => [
                'login' => $login
            ]
        ]));
    }
}
