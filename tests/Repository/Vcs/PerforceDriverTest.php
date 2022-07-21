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
use Composer\Util\Filesystem;
use Fxp\Composer\AssetPlugin\Repository\Vcs\PerforceDriver;
use Fxp\Composer\AssetPlugin\Tests\TestCase;
use Fxp\Composer\AssetPlugin\Util\Perforce;

/**
 * Tests of vcs perforce repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class PerforceDriverTest extends TestCase
{
    const TEST_URL = 'TEST_PERFORCE_URL';
    const TEST_DEPOT = 'TEST_DEPOT_CONFIG';
    const TEST_BRANCH = 'TEST_BRANCH_CONFIG';
    protected Config $config;
    protected \PHPUnit\Framework\MockObject\MockObject|\Composer\IO\IOInterface $io;
    protected \PHPUnit\Framework\MockObject\MockObject|\Composer\Util\ProcessExecutor $process;
    protected \PHPUnit\Framework\MockObject\MockObject|\Composer\Util\HttpDownloader $httpDownloader;
    protected string|bool $testPath;

    /**
     * @var PerforceDriver
     */
    protected PerforceDriver $driver;

    protected array $repoConfig;

    /**
     * @var Perforce|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Perforce $perforce;

    protected function setUp(): void
    {
        $this->testPath = $this->getUniqueTmpDirectory();
        $this->config = $this->getTestConfig($this->testPath);
        $this->repoConfig = $this->getTestRepoConfig();
        $this->io = $this->getMockIOInterface();
        $this->process = $this->getMockProcessExecutor();
        $this->httpDownloader = $this->getMockHttpDownloader();
        $this->perforce = $this->getMockPerforce();
        $this->driver = new PerforceDriver($this->repoConfig, $this->io, $this->config, $this->httpDownloader, $this->process);
        $this->overrideDriverInternalPerforce($this->perforce);
    }

    protected function tearDown(): void
    {
        //cleanup directory under test path
        $fs = new Filesystem();
        $fs->removeDirectory($this->testPath);
        unset($this->driver, $this->perforce, $this->process, $this->httpDownloader, $this->io, $this->config, $this->testPath);
    }

    public function testInitializeCapturesVariablesFromRepoConfig()
    {
        $driver = new PerforceDriver($this->repoConfig, $this->io, $this->config, $this->httpDownloader, $this->process);
        $driver->initialize();
        self::assertEquals(self::TEST_URL, $driver->getUrl());
        self::assertEquals(self::TEST_DEPOT, $driver->getDepot());
        self::assertEquals(self::TEST_BRANCH, $driver->getBranch());
    }

    /**
     * Test that supports() simply return false.
     *
     * @covers \Composer\Repository\Vcs\PerforceDriver::supports
     */
    public function testSupportsReturnsFalseNoDeepCheck()
    {
        $this->expectOutputString('');
        self::assertFalse(PerforceDriver::supports($this->io, $this->config, 'existing.url'));
    }

    public function testInitializeLogsInAndConnectsClient()
    {
        $this->perforce->expects(self::exactly(1))->method('p4Login');
        $this->perforce->expects(self::exactly(2))->method('checkStream');
        $this->perforce->expects(self::exactly(3))->method('writeP4ClientSpec');
        $this->perforce->expects(self::exactly(4))->method('connectClient');
        $this->driver->initialize();
    }

    public function testPublicRepositoryWithEmptyComposer()
    {
        $identifier = 'TEST_IDENTIFIER';
        $this->perforce->expects(self::any())
            ->method('getComposerInformation')
            ->with(self::equalTo($identifier))
            ->willReturn('');

        $this->driver->initialize();
        $validEmpty = [
            '_nonexistent_package' => true,
        ];

        self::assertSame($validEmpty, $this->driver->getComposerInformation($identifier));
    }

    public function testPublicRepositoryWithCodeCache()
    {
        $identifier = 'TEST_IDENTIFIER';
        $this->perforce->expects(self::any())
            ->method('getComposerInformation')
            ->with(self::equalTo($identifier))
            ->willReturn(['name' => 'foo']);

        $this->driver->initialize();
        $composer1 = $this->driver->getComposerInformation($identifier);
        $composer2 = $this->driver->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
    }

    public function testPublicRepositoryWithFilesystemCache()
    {
        $identifier = 'TEST_IDENTIFIER';
        $this->perforce->expects(self::any())
            ->method('getComposerInformation')
            ->with(self::equalTo($identifier))
            ->willReturn(['name' => 'foo']);

        $driver2 = new PerforceDriver($this->repoConfig, $this->io, $this->config, $this->process, $this->remoteFileSystem);
        $reflectionClass = new \ReflectionClass($driver2);
        $property = $reflectionClass->getProperty('perforce');
        $property->setAccessible(true);
        $property->setValue($driver2, $this->perforce);

        $this->driver->initialize();
        $driver2->initialize();

        $composer1 = $this->driver->getComposerInformation($identifier);
        $composer2 = $driver2->getComposerInformation($identifier);

        self::assertNotNull($composer1);
        self::assertNotNull($composer2);
        self::assertSame($composer1, $composer2);
    }

    protected function getMockIOInterface(): \Composer\IO\IOInterface|\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
    }

    protected function getMockProcessExecutor(): \Composer\Util\ProcessExecutor|\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
    }

    protected function getMockHttpDownloader(): \PHPUnit\Framework\MockObject\MockObject|\Composer\Util\HttpDownloader
    {
        return $this->getMockBuilder('Composer\Util\HttpDownloader')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function overrideDriverInternalPerforce(Perforce $perforce)
    {
        $reflectionClass = new \ReflectionClass($this->driver);
        $property = $reflectionClass->getProperty('perforce');
        $property->setAccessible(true);
        $property->setValue($this->driver, $perforce);
    }

    protected function getTestConfig($testPath): Config
    {
        $config = new Config();
        $config->merge(['config' => ['home' => $testPath]]);

        return $config;
    }

    protected function getTestRepoConfig(): array
    {
        return [
            'url' => self::TEST_URL,
            'depot' => self::TEST_DEPOT,
            'branch' => self::TEST_BRANCH,
            'asset-type' => 'ASSET',
            'filename' => 'ASSET.json'
        ];
    }

    protected function getMockPerforce(): Perforce|\PHPUnit\Framework\MockObject\MockObject
    {
        $methods = ['p4login', 'checkStream', 'writeP4ClientSpec', 'connectClient', 'getComposerInformation', 'cleanupClientSpec'];

        return $this->getMockBuilder('Fxp\Composer\AssetPlugin\Util\Perforce')
            ->disableOriginalConstructor()
            ->addMethods($methods)
            ->getMock();
    }
}
