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

use Fxp\Composer\AssetPlugin\Converter\BowerPackageConverter;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Tests of bower package converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class BowerPackageConverterTest extends AbstractPackageConverterTest
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var AssetTypeInterface $type */
        $type = $this->type;
        $this->converter = new BowerPackageConverter($type);
        $this->asset = (array) json_decode(file_get_contents(__DIR__.'/../Fixtures/package/bower.json'), true);
    }

    public function testConvert()
    {
        $composer = $this->converter->convert($this->asset);

        self::assertArrayHasKey('name', $composer);
        self::assertSame('ASSET/'.$this->asset['name'], $composer['name']);

        self::assertArrayHasKey('type', $composer);
        self::assertSame('ASSET_TYPE', $composer['type']);

        self::assertArrayHasKey('description', $composer);
        self::assertSame($this->asset['description'], $composer['description']);

        self::assertArrayHasKey('version', $composer);
        self::assertSame('1.0.0-pre', $composer['version']);

        self::assertArrayHasKey('keywords', $composer);
        self::assertSame($this->asset['keywords'], $composer['keywords']);

        self::assertArrayHasKey('require', $composer);
        self::assertSame(array(
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
        ), $composer['require']);

        self::assertArrayNotHasKey('require-dev', $composer);

        self::assertArrayHasKey('license', $composer);
        self::assertSame($this->asset['license'], $composer['license']);

        self::assertArrayHasKey('bin', $composer);
        self::assertSame($this->asset['bin'], $composer['bin']);

        self::assertArrayHasKey('extra', $composer);

        self::assertArrayHasKey('bower-asset-main', $composer['extra']);
        self::assertSame($this->asset['main'], $composer['extra']['bower-asset-main']);

        self::assertArrayHasKey('bower-asset-ignore', $composer['extra']);
        self::assertSame($this->asset['ignore'], $composer['extra']['bower-asset-ignore']);

        self::assertArrayHasKey('bower-asset-private', $composer['extra']);
        self::assertSame($this->asset['private'], $composer['extra']['bower-asset-private']);

        self::assertArrayNotHasKey('homepage', $composer);
        self::assertArrayNotHasKey('time', $composer);
        self::assertArrayNotHasKey('authors', $composer);
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
}
