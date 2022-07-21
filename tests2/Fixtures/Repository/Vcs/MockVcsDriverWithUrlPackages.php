<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs;

/**
 * Mock vcs driver for url packages test.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockVcsDriverWithUrlPackages extends MockVcsDriverWithPackages
{
    protected array $composer = [
        'branch:master' => [
            'version' => '2.0',
        ],
        'branch:1.x' => [
            'version' => '1.1',
        ],
        'tag:v1.0.0' => [
            'version' => '1.0',
        ],
        'tag:v1.0.1' => [
        ],
        'tag:invalid' => [
            'description' => 'invalid tag name',
        ],
    ];
}
