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
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Fxp\Composer\AssetPlugin\Config\ConfigBuilder;

/**
 * Tests for the plugin config.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class ConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Composer $composer;

    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Config $composerConfig;

    /**
     * @var IOInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|IOInterface $io;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface $package;

    protected function setUp(): void
    {
        $this->composer = $this->getMockBuilder(Composer::class)->disableOriginalConstructor()->getMock();
        $this->composerConfig = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $this->io = $this->getMockBuilder(IOInterface::class)->getMock();
        $this->package = $this->getMockBuilder(RootPackageInterface::class)->getMock();

        $this->composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($this->package);

        $this->composer->expects(self::any())
            ->method('getConfig')
            ->willReturn($this->composerConfig);
    }

    public function getDataForGetConfig(): array
    {
        return [
            ['foo', 42, 42],
            ['bar', 'foo', 'empty'],
            ['baz', false, true],
            ['repositories', 42, 0],
            ['global-composer-foo', 90, 0],
            ['global-composer-bar', 70, 0],
            ['global-config-foo', 23, 0],
            ['env-boolean', false, true, 'FXP_ASSET__ENV_BOOLEAN=false'],
            ['env-integer', -32, 0, 'FXP_ASSET__ENV_INTEGER=-32'],
            ['env-json', ['foo' => 'bar'], [], 'FXP_ASSET__ENV_JSON="{"foo": "bar"}"'],
            ['env-json-array', [['foo' => 'bar']], [], 'FXP_ASSET__ENV_JSON_ARRAY="[{"foo": "bar"}]"'],
            ['env-string', 'baz', 'foo', 'FXP_ASSET__ENV_STRING=baz'],
        ];
    }

    /**
     * @dataProvider getDataForGetConfig
     *
     * @param string $key The key
     * @param mixed $expected The expected value
     * @param mixed|null $default The default value
     * @param string|null $env The env variable
     */
    public function testGetConfig(string $key, mixed $expected, mixed $default = null, ?string $env = null)
    {
        // add env variables
        if (null !== $env) {
            putenv($env);
        }

        $globalPath = realpath(__DIR__ . '/../Fixtures/package/global');
        $this->composerConfig->expects(self::any())
            ->method('has')
            ->with('home')
            ->willReturn(true);

        $this->composerConfig->expects(self::any())
            ->method('get')
            ->with('home')
            ->willReturn($globalPath);

        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn([
                'asset-baz' => false,
                'asset-repositories' => 42,
            ]);

        $this->package->expects(self::any())
            ->method('getConfig')
            ->willReturn([
                'fxp-asset' => [
                    'bar' => 'foo',
                    'baz' => false,
                    'env-foo' => 55,
                ],
            ]);

        if (str_starts_with($key, 'global-')) {
            $this->io->expects(self::atLeast(2))
                ->method('isDebug')
                ->willReturn(true);

            $this->io->expects(self::at(1))
                ->method('writeError')
                ->with(sprintf('Loading fxp-asset config in file %s/composer.json', $globalPath));
            $this->io->expects(self::at(3))
                ->method('writeError')
                ->with(sprintf('Loading fxp-asset config in file %s/config.json', $globalPath));
        }

        $config = ConfigBuilder::build($this->composer, $this->io);
        $value = $config->get($key, $default);

        // remove env variables
        if (null !== $env) {
            $envKey = substr($env, 0, strpos($env, '='));
            putenv($envKey);
            self::assertFalse(getenv($envKey));
        }

        self::assertSame($expected, $value);
        // test cache
        self::assertSame($expected, $config->get($key, $default));
    }

    public function testGetEnvConfigWithInvalidJson()
    {
        putenv('FXP_ASSET__ENV_JSON="{"foo"}"');
        $config = ConfigBuilder::build($this->composer, $this->io);
        $ex = null;

        try {
            $config->get('env-json');
        } catch (\Exception $e) {
            $ex = $e;
        }

        putenv('FXP_ASSET__ENV_JSON');
        self::assertFalse(getenv('FXP_ASSET__ENV_JSON'));
        self::expectException('\Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException');
        self::expectExceptionMessage('The "FXP_ASSET__ENV_JSON" environment variable isn\'t a valid JSON');

        if (null === $ex) {
            throw new \Exception('The expected exception was not thrown');
        }

        throw $ex;
    }

    public function testValidateConfig()
    {
        $deprecated = [
            'asset-installer-paths' => 'deprecated',
            'asset-ignore-files' => 'deprecated',
            'asset-private-bower-registries' => 'deprecated',
            'asset-pattern-skip-version' => 'deprecated',
            'asset-optimize-with-installed-packages' => 'deprecated',
            'asset-optimize-with-conjunctive' => 'deprecated',
            'asset-repositories' => 'deprecated',
            'asset-registry-options' => 'deprecated',
            'asset-vcs-driver-options' => 'deprecated',
            'asset-main-files' => 'deprecated',
        ];

        $this->package->expects(self::any())
            ->method('getExtra')
            ->willReturn($deprecated);

        foreach (array_keys($deprecated) as $i => $option) {
            $this->io->expects(self::at($i))
                ->method('write')
                ->with('<warning>The "extra.' . $option . '" option is deprecated, use the "config.fxp-asset.' . substr($option, 6) . '" option</warning>');
        }

        ConfigBuilder::validate($this->io, $this->package);
    }
}
