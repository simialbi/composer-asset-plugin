<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Type;

use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;
use Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Abstract class for tests of asset type.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractAssetTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PackageConverterInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|PackageConverterInterface $packageConverter;

    /**
     * @var VersionConverterInterface
     */
    protected VersionConverterInterface|\PHPUnit\Framework\MockObject\MockObject $versionConverter;

    /**
     * @var AssetTypeInterface
     */
    protected AssetTypeInterface $type;

    protected function setUp(): void
    {
        $this->packageConverter = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface')->getMock();
        $this->versionConverter = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface')->getMock();
    }

    protected function tearDown(): void
    {
        unset($this->packageConverter, $this->versionConverter, $this->type);
    }

    public function testConverter()
    {
        static::assertInstanceOf('Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface', $this->type->getPackageConverter());
        static::assertInstanceOf('Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface', $this->type->getVersionConverter());
    }
}
