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
 * Mock vcs driver for skip parsing test.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockVcsDriverSkipParsing extends MockVcsDriver
{
    public function getRootIdentifier(): string
    {
        return 'ROOT';
    }

    public function hasComposerFile($identifier): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws
     */
    public function getComposerInformation($identifier): ?array
    {
        throw new \Exception('MESSAGE with '.$identifier);
    }
}
