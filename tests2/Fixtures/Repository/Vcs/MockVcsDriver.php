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

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Repository\Vcs\VcsDriverInterface;

/**
 * Mock vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockVcsDriver implements VcsDriverInterface
{
    /**
     * @var bool
     */
    public static bool $supported = true;

    /**
     * @var mixed
     */
    public mixed $contents;

    public function initialize(): void
    {
        // no action
    }

    public function getComposerInformation($identifier): ?array
    {
        return null;
    }

    public function getRootIdentifier(): string
    {
        return '';
    }

    public function getBranches(): array
    {
        return [];
    }

    public function getTags(): array
    {
        return [];
    }

    public function getDist($identifier): ?array
    {
        return null;
    }

    public function getSource($identifier): array
    {
        return [];
    }

    public function getUrl(): string
    {
        return '';
    }

    public function hasComposerFile($identifier): bool
    {
        return false;
    }

    public function cleanup(): void
    {
        // no action
    }

    public static function supports(IOInterface $io, Config $config, $url, $deep = false): bool
    {
        return static::$supported;
    }

    public function getFileContent($file, $identifier): ?string
    {
        return null;
    }

    public function getChangeDate($identifier): ?\DateTimeImmutable
    {
        return null;
    }

    /**
     * @return mixed
     */
    protected function getContents(): mixed
    {
        return $this->contents;
    }
}
