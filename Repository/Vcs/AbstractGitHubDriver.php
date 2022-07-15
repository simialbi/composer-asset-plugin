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
use Composer\Downloader\TransportException;
use Composer\Json\JsonFile;
use Composer\Repository\Vcs\GitHubDriver as BaseGitHubDriver;
use Composer\Util\Http\Response;
use Fxp\Composer\AssetPlugin\Repository\Util as RepoUtil;

/**
 * Abstract class for GitHub vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractGitHubDriver extends BaseGitHubDriver
{
    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var null|false|string
     */
    protected string|null|false $redirectApi;

    /**
     * {@inheritDoc}
     */
    public function initialize(): void
    {
        if (!isset($this->repoConfig['no-api'])) {
            $this->repoConfig['no-api'] = $this->getNoApiOption();
        }

        parent::initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function getBranches(): array
    {
        if ($this->gitDriver) {
            return $this->gitDriver->getBranches();
        }

        if (null === $this->branches) {
            $this->branches = [];
            $resource = $this->getApiUrl() . '/repos/' . $this->owner . '/' . $this->repository . '/git/refs/heads?per_page=100';
            $branchBlacklist = 'gh-pages' === $this->getRootIdentifier() ? [] : ['gh-pages'];

            $this->doAddBranches($resource, $branchBlacklist);
        }

        return $this->branches;
    }

    /**
     * Get the no-api repository option.
     *
     * @return bool
     */
    protected function getNoApiOption(): bool
    {
        $packageName = $this->repoConfig['package-name'];
        $opts = RepoUtil::getArrayValue($this->repoConfig, 'vcs-driver-options', []);
        $noApiOpt = RepoUtil::getArrayValue($opts, 'github-no-api', []);
        $defaultValue = false;

        if (\is_bool($noApiOpt)) {
            $defaultValue = $noApiOpt;
            $noApiOpt = [];
        }

        $noApiOpt['default'] = (bool)RepoUtil::getArrayValue($noApiOpt, 'default', $defaultValue);
        $noApiOpt['packages'] = (array)RepoUtil::getArrayValue($noApiOpt, 'packages', []);

        return (bool)RepoUtil::getArrayValue($noApiOpt['packages'], $packageName, $defaultValue);
    }

    /**
     * Get the remote content.
     *
     * @param string $url The URL of content
     * @param bool $fetchingRepoData Fetching the repo data or not
     *
     * @return Response The result
     */
    protected function getContents(string $url, bool $fetchingRepoData = false): Response
    {
        $url = $this->getValidContentUrl($url);

        if (null !== $this->redirectApi) {
            return parent::getContents($url, $fetchingRepoData);
        }

        try {
            $contents = $this->getRemoteContents($url);
            $this->redirectApi = false;

            return $contents;
        } catch (TransportException $e) {
            if ($this->hasRedirectUrl($url)) {
                $url = $this->getValidContentUrl($url);
            }

            return parent::getContents($url, $fetchingRepoData);
        }
    }

    /**
     * Get the valid content url.
     *
     * @param string $url The url
     *
     * @return string The url redirected
     */
    protected function getValidContentUrl(string $url): string
    {
        if (null === $this->redirectApi && false !== $redirectApi = $this->cache->read('redirect-api')) {
            $this->redirectApi = $redirectApi;
        }

        if (\is_string($this->redirectApi) && str_starts_with($url, $this->getRepositoryApiUrl())) {
            $url = $this->redirectApi . substr($url, \strlen($this->getRepositoryApiUrl()));
        }

        return $url;
    }

    /**
     * Check if the driver must find the new url.
     *
     * @param string $url The url
     *
     * @return bool
     * @throws
     *
     */
    protected function hasRedirectUrl(string $url): bool
    {
        if (null === $this->redirectApi && str_starts_with($url, $this->getRepositoryApiUrl())) {
            $this->redirectApi = $this->getNewRepositoryUrl();

            if (\is_string($this->redirectApi)) {
                $this->cache->write('redirect-api', $this->redirectApi);
            }
        }

        return \is_string($this->redirectApi);
    }

    /**
     * Get the new url of repository.
     *
     * @return false|string The new url or false if there is not a new url
     */
    protected function getNewRepositoryUrl(): string|false
    {
        try {
            $response = $this->getRemoteContents($this->getRepositoryUrl());
            $headers = $response->getHeaders();

            if (preg_match('{^(30[1278])}i', $response->getStatusCode())) {
                array_shift($headers);

                return $this->findNewLocationInHeader($headers);
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Find the new url api in the header.
     *
     * @param array $headers The http header
     *
     * @return false|string
     */
    protected function findNewLocationInHeader(array $headers): string|false
    {
        $url = false;

        foreach ($headers as $header) {
            if (str_starts_with($header, 'Location:')) {
                $newUrl = trim(substr($header, 9));
                preg_match('#^(?:(?:https?|git)://([^/]+)/|git@([^:]+):)([^/]+)/(.+?)(?:\.git|/)?$#', $newUrl, $match);
                $owner = $match[3];
                $repository = $match[4];
                $paramPos = strpos($repository, '?');
                $repository = \is_int($paramPos) ? substr($match[4], 0, $paramPos) : $repository;
                $url = $this->getRepositoryApiUrl($owner, $repository);

                break;
            }
        }

        return $url;
    }

    /**
     * Get the url API of the repository.
     *
     * @param string|null $owner
     * @param string|null $repository
     *
     * @return string
     */
    protected function getRepositoryApiUrl(?string $owner = null, ?string $repository = null): string
    {
        $owner = null !== $owner ? $owner : $this->owner;
        $repository = null !== $repository ? $repository : $this->repository;

        return $this->getApiUrl() . '/repos/' . $owner . '/' . $repository;
    }

    /**
     * Get the remote content.
     *
     * @param string $url
     *
     * @return Response
     */
    protected function getRemoteContents(string $url): Response
    {
        return $this->httpDownloader->get($url, []);
    }

    /**
     * Push the list of all branch.
     *
     * @param string $resource
     * @param array $branchBlacklist
     *
     * @return void
     */
    protected function doAddBranches(string $resource, array $branchBlacklist): void
    {
        do {
            $response = $this->getContents($resource);
            $branchData = $response->decodeJson();

            foreach ($branchData as $branch) {
                $name = substr($branch['ref'], 11);

                if (!\in_array($name, $branchBlacklist, true)) {
                    $this->branches[$name] = $branch['object']['sha'];
                }
            }

            $resource = $this->getNextPage($response);
        } while ($resource);
    }
}
