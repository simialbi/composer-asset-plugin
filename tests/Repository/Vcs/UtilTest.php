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

use Fxp\Composer\AssetPlugin\Repository\Vcs\Util;
use Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs\MockVcsDriver;

/**
 * Tests of util.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class UtilTest extends \PHPUnit\Framework\TestCase
{
    public function getDataProvider(): array
    {
        return [
            ['key'],
            ['key.subkey'],
            ['key.subkey.subsubkey']
        ];
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param string $resourceKey
     */
    public function testAddComposerTimeWithSimpleKey(string $resourceKey)
    {
        $composer = [
            'name' => 'test',
        ];
        $driver = new MockVcsDriver();

        $value = null;
        $keys = explode('.', $resourceKey);
        $start = \count($keys) - 1;

        for ($i = $start; $i >= 0; --$i) {
            if (null === $value) {
                $value = 'level ' . $i;
            }

            $value = [$keys[$i] => $value];
        }

        $driver->contents = json_encode($value);
        $composerValid = array_merge($composer, [
            'time' => 'level ' . (\count($keys) - 1),
        ]);

        $composer = Util::addComposerTime($composer, $resourceKey, 'http://example.tld', $driver);

        self::assertSame($composerValid, $composer);
    }
}
