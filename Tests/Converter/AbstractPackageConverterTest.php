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

use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;
use Fxp\Composer\AssetPlugin\Tests\Fixtures\Converter\InvalidPackageConverter;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Abstract tests of asset package converter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractPackageConverterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AssetTypeInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetTypeInterface $type;

    /**
     * @var PackageConverterInterface
     */
    protected PackageConverterInterface $converter;

    /**
     * @var array
     */
    protected array $asset;

    protected function setUp(): void
    {
        $versionConverter = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface')->getMock();
        $versionConverter->expects(static::any())
            ->method('convertVersion')
            ->willReturnCallback(function ($value) {
                return $value;
            });
        $versionConverter->expects(static::any())
            ->method('convertRange')
            ->willReturnCallback(function ($value) {
                return $value;
            });
        $type = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Type\AssetTypeInterface')->getMock();
        $type->expects(static::any())
            ->method('getComposerVendorName')
            ->willReturn('ASSET');
        $type->expects(static::any())
            ->method('getComposerType')
            ->willReturn('ASSET_TYPE');
        $type->expects(static::any())
            ->method('getVersionConverter')
            ->willReturn($versionConverter);
        $type->expects(static::any())
            ->method('formatComposerName')
            ->willReturnCallback(function ($value) {
                return 'ASSET/' . $value;
            });

        $this->type = $type;
    }

    protected function tearDown(): void
    {
        unset($this->type, $this->converter);
        $this->asset = [];
    }

    public function testConversionWithInvalidKey()
    {
        self::expectException('\Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException');
        $this->converter = new InvalidPackageConverter($this->type);

        $this->converter->convert([
            'name' => 'foo',
        ]);
    }
}
