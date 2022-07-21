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

use Fxp\Composer\AssetPlugin\Converter\NpmPackageUtil;

/**
 * Tests of npm package util.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class NpmPackageUtilTest extends AbstractPackageConverterTest
{
    public function testConvertName()
    {
        $packageName = '@vendor/package';
        $expected = 'vendor--package';

        self::assertSame($expected, NpmPackageUtil::convertName($packageName));
    }

    public function testRevertName()
    {
        $packageName = 'vendor--package';
        $expected = '@vendor/package';

        self::assertSame($expected, NpmPackageUtil::revertName($packageName));
    }

    public function getLicenses(): array
    {
        return [
            [['MIT'], ['MIT']],
            [['type' => 'MIT'], ['MIT']],
            [['name' => 'MIT'], ['MIT']],
            [[['type' => 'MIT']], ['MIT']],
            [[['name' => 'MIT']], ['MIT']],
        ];
    }

    /**
     * @dataProvider getLicenses
     *
     * @param array|string $licenses
     * @param array|string $expected
     */
    public function testLicenses(array|string $licenses, array|string $expected)
    {
        self::assertSame($expected, NpmPackageUtil::convertLicenses($licenses));
    }
}
