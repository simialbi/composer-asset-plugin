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

use Fxp\Composer\AssetPlugin\Converter\SemverConverter;
use Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface;

/**
 * Tests for the conversion of Semver syntax to composer syntax.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class SemverConverterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SemverConverter|VersionConverterInterface
     */
    protected VersionConverterInterface|SemverConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new SemverConverter();
    }

    protected function tearDown(): void
    {
        unset($this->converter);
    }

    /**
     * @dataProvider getTestVersions
     *
     * @param string|null $semver
     * @param string $composer
     */
    public function testConverter(string $semver, string $composer)
    {
        self::assertEquals($composer, $this->converter->convertVersion($semver));

        if (!ctype_alpha($semver) && !\in_array($semver, [null, ''], true)) {
            self::assertEquals('v' . $composer, $this->converter->convertVersion('v' . $semver));
        }
    }

    public function getTestVersions(): array
    {
        return [
            ['1.2.3', '1.2.3'],
            ['1.2.3alpha', '1.2.3-alpha1'],
            ['1.2.3-alpha', '1.2.3-alpha1'],
            ['1.2.3a', '1.2.3-alpha1'],
            ['1.2.3a1', '1.2.3-alpha1'],
            ['1.2.3-a', '1.2.3-alpha1'],
            ['1.2.3-a1', '1.2.3-alpha1'],
            ['1.2.3b', '1.2.3-beta1'],
            ['1.2.3b1', '1.2.3-beta1'],
            ['1.2.3-b', '1.2.3-beta1'],
            ['1.2.3-b1', '1.2.3-beta1'],
            ['1.2.3beta', '1.2.3-beta1'],
            ['1.2.3-beta', '1.2.3-beta1'],
            ['1.2.3beta1', '1.2.3-beta1'],
            ['1.2.3-beta1', '1.2.3-beta1'],
            ['1.2.3rc1', '1.2.3-RC1'],
            ['1.2.3-rc1', '1.2.3-RC1'],
            ['1.2.3rc2', '1.2.3-RC2'],
            ['1.2.3-rc2', '1.2.3-RC2'],
            ['1.2.3rc.2', '1.2.3-RC.2'],
            ['1.2.3-rc.2', '1.2.3-RC.2'],
            ['1.2.3+0', '1.2.3-patch0'],
            ['1.2.3-0', '1.2.3-patch0'],
            ['1.2.3pre', '1.2.3-beta1'],
            ['1.2.3-pre', '1.2.3-beta1'],
            ['1.2.3dev', '1.2.3-dev'],
            ['1.2.3-dev', '1.2.3-dev'],
            ['1.2.3+build2012', '1.2.3-patch2012'],
            ['1.2.3-build2012', '1.2.3-patch2012'],
            ['1.2.3+build.2012', '1.2.3-patch.2012'],
            ['1.2.3-build.2012', '1.2.3-patch.2012'],
            ['1.3.0–rc30.79', '1.3.0-RC30.79'],
            ['1.2.3-SNAPSHOT', '1.2.3-dev'],
            ['1.2.3-npm-packages', '1.2.3'],
            ['1.2.3-bower-packages', '1.2.3'],
            ['20170124.0.0', '20170124.000000'],
            ['20170124.1.0', '20170124.001000'],
            ['20170124.1.1', '20170124.001001'],
            ['20170124.100.200', '20170124.100200'],
            ['20170124.0', '20170124.000000'],
            ['20170124.1', '20170124.001000'],
            ['20170124', '20170124'],
            ['latest', 'default || *'],
            ['', '*'],
        ];
    }

    /**
     * @dataProvider getTestRanges
     *
     * @param string $semver
     * @param string $composer
     */
    public function testRangeConverter(string $semver, string $composer)
    {
        self::assertEquals($composer, $this->converter->convertRange($semver));
    }

    public function getTestRanges(): array
    {
        return [
            ['>1.2.3', '>1.2.3'],
            ['<1.2.3', '<1.2.3'],
            ['>=1.2.3', '>=1.2.3'],
            ['<=1.2.3', '<=1.2.3'],
            ['~1.2.3', '~1.2.3'],
            ['~1', '~1'],
            ['1', '~1'],
            ['^1.2.3', '>=1.2.3,<2.0.0'],
            ['^1.2', '>=1.2.0,<2.0.0'],
            ['^1.x', '>=1.0.0,<2.0.0'],
            ['^1', '>=1.0.0,<2.0.0'],
            ['>1.2.3 <2.0', '>1.2.3,<2.0'],
            ['>1.2 <2.0', '>1.2,<2.0'],
            ['>1 <2', '>1,<2'],
            ['>=1.2.3 <2.0', '>=1.2.3,<2.0'],
            ['>=1.2 <2.0', '>=1.2,<2.0'],
            ['>=1 <2', '>=1,<2'],
            ['>=1.0 <1.1 || >=1.2', '>=1.0,<1.1|>=1.2'],
            ['>=1.0 && <1.1 || >=1.2', '>=1.0,<1.1|>=1.2'],
            ['< 1.2.3', '<1.2.3'],
            ['> 1.2.3', '>1.2.3'],
            ['<= 1.2.3', '<=1.2.3'],
            ['>= 1.2.3', '>=1.2.3'],
            ['~ 1.2.3', '~1.2.3'],
            ['~1.2.x', '~1.2.0'],
            ['~ 1.2', '~1.2'],
            ['~ 1', '~1'],
            ['^ 1.2.3', '>=1.2.3,<2.0.0'],
            ['~> 1.2.3', '~1.2.3,>1.2.3'],
            ['1.2.3 - 2.3.4', '>=1.2.3,<=2.3.4'],
            ['1.0.0 - 1.3.x', '>=1.0.0,<1.4.0'],
            ['1.0 - 1.x', '>=1.0,<2.0'],
            ['1.2.3 - 2', '>=1.2.3,<3.0'],
            ['1.x - 2.x', '>=1.0,<3.0'],
            ['2 - 3', '>=2,<4.0'],
            ['>=0.10.x', '>=0.10.0'],
            ['>=0.10.*', '>=0.10.0'],
            ['<=0.10.x', '<=0.10.9999999'],
            ['<=0.10.*', '<=0.10.9999999'],
            ['=1.2.x', '1.2.x'],
            ['1.x.x', '1.x'],
            ['1.x.x.x', '1.x'],
            ['2.X.X.X', '2.x'],
            ['2.X.x.x', '2.x'],
            ['>=1.2.3 <2.0', '>=1.2.3,<2.0'],
            ['^1.2.3', '>=1.2.3,<2.0.0'],
            ['^0.2.3', '>=0.2.3,<0.3.0'],
            ['^0.0.3', '>=0.0.3,<0.0.4'],
            ['^1.2.3-beta.2', '>=1.2.3-beta.2,<2.0.0'],
            ['^0.0.3-beta', '>=0.0.3-beta1,<0.0.4'],
            ['^1.2.x', '>=1.2.0,<2.0.0'],
            ['^0.0.x', '>=0.0.0,<0.1.0'],
            ['^0.0', '>=0.0.0,<0.1.0'],
            ['^1.x', '>=1.0.0,<2.0.0'],
            ['^0.x', '>=0.0.0,<1.0.0'],
            ['~v1', '~1'],
            ['~v1-beta', '~1-beta1'],
            ['~v1.2', '~1.2'],
            ['~v1.2-beta', '~1.2-beta1'],
            ['~v1.2.3', '~1.2.3'],
            ['~v1.2.3-beta', '~1.2.3-beta1'],
        ];
    }
}
