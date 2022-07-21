<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Repository;

use Fxp\Composer\AssetPlugin\Repository\ResolutionManager;

/**
 * Tests of Resolution Manager.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class ResolutionManagerTest extends \PHPUnit\Framework\TestCase
{
    public function testSolveResolutions()
    {
        $rm = new ResolutionManager([
            'foo/bar' => '^2.2.0',
            'bar/foo' => '^0.2.0'
        ]);

        $data = $rm->solveResolutions([
            'require' => [
                'foo/bar' => '2.0.*',
                'foo/baz' => '~1.0'
            ],
            'require-dev' => [
                'bar/foo' => '^0.1.0',
                'test/dev' => '~1.0@dev'
            ],
        ]);

        $expected = [
            'require' => [
                'foo/bar' => '^2.2.0',
                'foo/baz' => '~1.0'
            ],
            'require-dev' => [
                'bar/foo' => '^0.2.0',
                'test/dev' => '~1.0@dev'
            ],
        ];

        self::assertSame($expected, $data);
    }
}
