<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository\Vcs;

use Composer\Cache;
use Composer\Repository\Vcs\PerforceDriver as BasePerforceDriver;
use Fxp\Composer\AssetPlugin\Util\Perforce;

/**
 * Perforce vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class PerforceDriver extends BasePerforceDriver
{
    /**
     * @var Perforce
     */
    protected $perforce;

    /**
     * @var array
     */
    protected $infoCache = [];

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    public function initialize(): void
    {
        $this->depot = $this->repoConfig['depot'];
        $this->branch = '';
        if (!empty($this->repoConfig['branch'])) {
            $this->branch = $this->repoConfig['branch'];
        }

        $this->initAssetPerforce($this->repoConfig);
        $this->perforce->p4Login();
        $this->perforce->checkStream();

        $this->perforce->writeP4ClientSpec();
        $this->perforce->connectClient();

        $this->cache = new Cache($this->io, $this->config->get('cache-repo-dir') . '/' . $this->originUrl . '/' . $this->depot);
    }

    /**
     * {@inheritDoc}
     */
    public function getComposerInformation($identifier): ?array
    {
        $this->infoCache[$identifier] = Util::readCache($this->infoCache, $this->cache, $this->repoConfig['asset-type'], $identifier, true);

        if (!isset($this->infoCache[$identifier])) {
            $composer = $this->getComposerContent($identifier);

            Util::writeCache($this->cache, $this->repoConfig['asset-type'], $identifier, $composer, true);
            $this->infoCache[$identifier] = $composer;
        }

        return $this->infoCache[$identifier];
    }

    /**
     * Get composer content.
     *
     * @param string $identifier
     *
     * @return array
     */
    protected function getComposerContent(string $identifier): array
    {
        $composer = $this->perforce->getComposerInformation($identifier);

        if (empty($composer) || !\is_array($composer)) {
            $composer = ['_nonexistent_package' => true];
        }

        return $composer;
    }

    /**
     * Initialize perforce asset
     *
     * @param array $repoConfig
     *
     * @return void
     */
    private function initAssetPerforce(array $repoConfig): void
    {
        if (!empty($this->perforce)) {
            return;
        }

        $repoDir = $this->config->get('cache-vcs-dir') . '/' . $this->depot;
        $this->perforce = Perforce::create($repoConfig, $this->getUrl(), $repoDir, $this->process, $this->io);
    }
}
