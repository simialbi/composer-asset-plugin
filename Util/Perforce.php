<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Util;

use Composer\IO\IOInterface;
use Composer\Util\Perforce as BasePerforce;
use Composer\Util\ProcessExecutor;

/**
 * Helper for perforce driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class Perforce extends BasePerforce
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * {@inheritDoc}
     */
    public function initialize($repoConfig): void
    {
        parent::initialize($repoConfig);

        $this->filename = (string)$repoConfig['filename'];
    }

    /**
     * {@inheritDoc}
     */
    public function getComposerInformation(string $identifier): ?array
    {
        $composerFileContent = $this->getFileContent($this->filename, $identifier);

        return !$composerFileContent
            ? null
            : json_decode($composerFileContent, true);
    }

    /**
     * {@inheritDoc}
     */
    public static function create($repoConfig, string|int $port, string $path, ProcessExecutor $process, IOInterface $io): static
    {
        $isWindows = \defined('PHP_WINDOWS_VERSION_BUILD');

        return new self($repoConfig, $port, $path, $process, $isWindows, $io);
    }
}
