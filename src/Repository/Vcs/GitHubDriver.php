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

use Composer\Downloader\TransportException;
use Composer\Json\JsonFile;

/**
 * GitHub vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class GitHubDriver extends AbstractGitHubDriver
{
    /**
     * {@inheritDoc}
     *
     * @throws
     */
    public function getComposerInformation($identifier): ?array
    {
        if ($this->gitDriver) {
            return $this->gitDriver->getComposerInformation($identifier);
        }

        $this->infoCache[$identifier] = Util::readCache($this->infoCache, $this->cache, $this->repoConfig['asset-type'], $identifier);

        if (!isset($this->infoCache[$identifier])) {
            $resource = $this->getApiUrl() . '/repos/' . $this->owner . '/' . $this->repository . '/contents/' . $this->repoConfig['filename'] . '?ref=' . urlencode($identifier);
            $composer = $this->getComposerContent($resource);

            if ($composer) {
                $composer = $this->convertComposerContent($composer, $resource, $identifier);
            } else {
                $composer = ['_nonexistent_package' => true];
            }

            Util::writeCache($this->cache, $this->repoConfig['asset-type'], $identifier, $composer);
            $this->infoCache[$identifier] = $composer;
        }

        return $this->infoCache[$identifier];
    }

    /**
     * Gets content of composer information.
     *
     * @param string $resource
     *
     * @return string|false|null
     *
     * @throws TransportException
     * @throws \Exception
     * @throws \RuntimeException
     */
    protected function getComposerContent(string $resource): string|false|null
    {
        $notFoundRetries = 2;
        $composer = null;

        while ($notFoundRetries) {
            try {
                $composer = $this->parseComposerContent($resource);

                break;
            } catch (TransportException $e) {
                if (404 !== $e->getCode()) {
                    throw $e;
                }

                // retry fetching if github returns a 404 since they happen randomly
                --$notFoundRetries;
                $composer = false;
            }
        }

        return $composer;
    }

    /**
     * Parse the composer content.
     *
     * @param string $resource
     *
     * @return string
     * @throws \RuntimeException When the resource could not be retrieved
     */
    protected function parseComposerContent(string $resource): string
    {
        $composer = (array)$this->getContents($resource)->decodeJson();

        if (empty($composer['content']) || 'base64' !== $composer['encoding'] || !($composer = base64_decode($composer['content'], true))) { // todo
            throw new \RuntimeException('Could not retrieve ' . $this->repoConfig['filename'] . ' from ' . $resource);
        }

        return $composer;
    }

    /**
     * Converts json composer file to array.
     *
     * @param string $composer
     * @param string $resource
     * @param string $identifier
     *
     * @return array
     * @throws \ReflectionException
     * @throws \Seld\JsonLint\ParsingException
     */
    protected function convertComposerContent(string $composer, string $resource, string $identifier): array
    {
        $composer = JsonFile::parseJson($composer, $resource);
        $resource = "{$this->getApiUrl()}/repos/$this->owner/$this->repository/commits/" . urlencode($identifier);
        $composer = Util::addComposerTime($composer, 'commit.committer.date', $resource, $this);
        if (isset($composer['support']) && is_scalar($composer['support'])) {
            $composer['support'] = [];
        }

        if (!isset($composer['support']['source'])) {
            $label = array_search($identifier, $this->getTags(), true) ?: array_search($identifier, $this->getBranches(), true) ?: $identifier;
            $composer['support']['source'] = sprintf('https://%s/%s/%s/tree/%s', $this->originUrl, $this->owner, $this->repository, $label);
        }
        if (!isset($composer['support']['issues']) && $this->hasIssues) {
            $composer['support']['issues'] = sprintf('https://%s/%s/%s/issues', $this->originUrl, $this->owner, $this->repository);
        }

        return $composer;
    }

    /**
     * Setup git driver.
     *
     * @param string $url
     *
     * @return void
     */
    protected function setupGitDriver(string $url): void
    {
        $this->gitDriver = new GitDriver(
            [
                'url' => $url,
                'asset-type' => $this->repoConfig['asset-type'],
                'filename' => $this->repoConfig['filename'],
                'asset-repository-manager' => $this->repoConfig['asset-repository-manager']
            ],
            $this->io,
            $this->config,
            $this->httpDownloader,
            $this->process
        );
        $this->gitDriver->initialize();
    }
}
