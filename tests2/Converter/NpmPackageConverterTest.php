<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Converter;

use Fxp\Composer\AssetPlugin\Converter\NpmPackageConverter;
use Fxp\Composer\AssetPlugin\Converter\NpmPackageUtil;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Tests of npm package converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class NpmPackageConverterTest extends AbstractPackageConverterTest
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var AssetTypeInterface $type */
        $type = $this->type;
        $this->converter = new NpmPackageConverter($type);
        $this->asset = $this->loadPackage();
    }

    public function testConvert()
    {
        $composer = $this->converter->convert($this->asset);

        self::assertArrayHasKey('name', $composer);
        self::assertSame('ASSET/' . $this->asset['name'], $composer['name']);

        self::assertArrayHasKey('type', $composer);
        self::assertSame('ASSET_TYPE', $composer['type']);

        self::assertArrayHasKey('description', $composer);
        self::assertSame($this->asset['description'], $composer['description']);

        self::assertArrayHasKey('version', $composer);
        self::assertSame('1.0.0-pre', $composer['version']);

        self::assertArrayHasKey('keywords', $composer);
        self::assertSame($this->asset['keywords'], $composer['keywords']);

        self::assertArrayHasKey('homepage', $composer);
        self::assertSame($this->asset['homepage'], $composer['homepage']);

        self::assertArrayHasKey('license', $composer);
        self::assertSame($this->asset['license'], $composer['license']);

        self::assertArrayHasKey('authors', $composer);
        self::assertSame(array_merge([$this->asset['author']], $this->asset['contributors']), $composer['authors']);

        self::assertArrayHasKey('require', $composer);
        self::assertSame([
            'ASSET/library1' => '>= 1.0.0',
            'ASSET/library2' => '>= 1.0.0',
            'ASSET/library2-0.9.0' => '0.9.0',
            'ASSET/library3' => '*',
            'ASSET/library4' => '1.2.3',
            'ASSET/library5' => 'dev-default#0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b',
            'ASSET/library6' => 'dev-branch',
            'ASSET/library7' => 'dev-1.2.* || 1.2.*',
            'ASSET/library8' => 'dev-1.2.x || 1.2.x',
            'ASSET/library9' => 'dev-master',
            'ASSET/library10' => '1.0.0',
            'ASSET/library11' => '*',
            'ASSET/library12' => '>=1 <2',
            'ASSET/library13' => '>=1 <2',
            'ASSET/library14' => '*',
            'ASSET/library15' => '*',
            'ASSET/library16' => '>=1 <2',
            'ASSET/test-library17-file' => '*',
            'ASSET/test-library18-file' => '1.2.3',
            'ASSET/test-library19-file' => '*',
            'ASSET/test-library20-file' => '*',
            'ASSET/library21' => '1 || 2',
        ], $composer['require']);

        self::assertArrayNotHasKey('require-dev', $composer);

        self::assertArrayHasKey('bin', $composer);
        self::assertIsArray($composer['bin']);
        self::assertSame($this->asset['bin'], $composer['bin'][0]);

        self::assertArrayHasKey('extra', $composer);

        self::assertArrayHasKey('npm-asset-bugs', $composer['extra']);
        self::assertSame($this->asset['bugs'], $composer['extra']['npm-asset-bugs']);

        self::assertArrayHasKey('npm-asset-files', $composer['extra']);
        self::assertSame($this->asset['files'], $composer['extra']['npm-asset-files']);

        self::assertArrayHasKey('npm-asset-main', $composer['extra']);
        self::assertSame($this->asset['main'], $composer['extra']['npm-asset-main']);

        self::assertArrayHasKey('npm-asset-man', $composer['extra']);
        self::assertSame($this->asset['man'], $composer['extra']['npm-asset-man']);

        self::assertArrayHasKey('npm-asset-directories', $composer['extra']);
        self::assertSame($this->asset['directories'], $composer['extra']['npm-asset-directories']);

        self::assertArrayHasKey('npm-asset-repository', $composer['extra']);
        self::assertSame($this->asset['repository'], $composer['extra']['npm-asset-repository']);

        self::assertArrayHasKey('npm-asset-scripts', $composer['extra']);
        self::assertSame($this->asset['scripts'], $composer['extra']['npm-asset-scripts']);

        self::assertArrayHasKey('npm-asset-config', $composer['extra']);
        self::assertSame($this->asset['config'], $composer['extra']['npm-asset-config']);

        self::assertArrayHasKey('npm-asset-bundled-dependencies', $composer['extra']);
        self::assertSame($this->asset['bundledDependencies'], $composer['extra']['npm-asset-bundled-dependencies']);

        self::assertArrayHasKey('npm-asset-optional-dependencies', $composer['extra']);
        self::assertSame($this->asset['optionalDependencies'], $composer['extra']['npm-asset-optional-dependencies']);

        self::assertArrayHasKey('npm-asset-engines', $composer['extra']);
        self::assertSame($this->asset['engines'], $composer['extra']['npm-asset-engines']);

        self::assertArrayHasKey('npm-asset-engine-strict', $composer['extra']);
        self::assertSame($this->asset['engineStrict'], $composer['extra']['npm-asset-engine-strict']);

        self::assertArrayHasKey('npm-asset-os', $composer['extra']);
        self::assertSame($this->asset['os'], $composer['extra']['npm-asset-os']);

        self::assertArrayHasKey('npm-asset-cpu', $composer['extra']);
        self::assertSame($this->asset['cpu'], $composer['extra']['npm-asset-cpu']);

        self::assertArrayHasKey('npm-asset-prefer-global', $composer['extra']);
        self::assertSame($this->asset['preferGlobal'], $composer['extra']['npm-asset-prefer-global']);

        self::assertArrayHasKey('npm-asset-private', $composer['extra']);
        self::assertSame($this->asset['private'], $composer['extra']['npm-asset-private']);

        self::assertArrayHasKey('npm-asset-publish-config', $composer['extra']);
        self::assertSame($this->asset['publishConfig'], $composer['extra']['npm-asset-publish-config']);

        self::assertArrayNotHasKey('time', $composer);
        self::assertArrayNotHasKey('support', $composer);
        self::assertArrayNotHasKey('conflict', $composer);
        self::assertArrayNotHasKey('replace', $composer);
        self::assertArrayNotHasKey('provide', $composer);
        self::assertArrayNotHasKey('suggest', $composer);
        self::assertArrayNotHasKey('autoload', $composer);
        self::assertArrayNotHasKey('autoload-dev', $composer);
        self::assertArrayNotHasKey('include-path', $composer);
        self::assertArrayNotHasKey('target-dir', $composer);
        self::assertArrayNotHasKey('archive', $composer);
    }

    public function testConvertWithScope()
    {
        $this->asset = $this->loadPackage('npm-scope.json');
        $composer = $this->converter->convert($this->asset);

        self::assertArrayHasKey('name', $composer);
        self::assertSame('ASSET/scope--test', $composer['name']);

        self::assertArrayHasKey('require', $composer);
        self::assertSame([
            'ASSET/scope--library1' => '>= 1.0.0',
            'ASSET/scope2--library2' => '>= 1.0.0',
        ], $composer['require']);

        self::assertArrayNotHasKey('require-dev', $composer);
    }

    public function getConvertDistData(): array
    {
        return [
            [['type' => null], []],
            [['foo' => 'http://example.com'], []], // unknown downloader type
            [['gzip' => 'http://example.com'], ['type' => 'gzip', 'url' => 'https://example.com']],
            [['tarball' => 'http://example.com'], ['type' => 'tar', 'url' => 'https://example.com']],
            [
                ['shasum' => 'abcdef0123456789abcdef0123456789abcdef01'],
                ['shasum' => 'abcdef0123456789abcdef0123456789abcdef01'],
            ],
        ];
    }

    /**
     * @dataProvider getConvertDistData
     *
     * @param array $value The value must be converted
     * @param array $result The result of convertion
     */
    public function testConvertDist(array $value, array $result)
    {
        self::assertSame($result, NpmPackageUtil::convertDist($value));
    }

    /**
     * Load the package.
     *
     * @param string $package The package file name
     *
     * @return array
     */
    private function loadPackage(string $package = 'npm.json'): array
    {
        return (array)json_decode(file_get_contents(__DIR__ . '/../Fixtures/package/' . $package), true);
    }
}
