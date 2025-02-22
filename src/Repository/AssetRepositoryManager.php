<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\DependencyResolver\Pool;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Config\Config;

/**
 * The asset repository manager.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class AssetRepositoryManager
{
    /**
     * @var IOInterface
     */
    protected IOInterface $io;

    /**
     * @var RepositoryManager
     */
    protected RepositoryManager $rm;

    /**
     * @var HttpDownloader
     */
    protected HttpDownloader $httpDownloader;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var VcsPackageFilter
     */
    protected VcsPackageFilter $packageFilter;

    /**
     * @var null|Pool
     */
    protected ?Pool $pool;

    /**
     * @var ResolutionManager
     */
    protected ResolutionManager $resolutionManager;

    /**
     * @var RepositoryInterface[]
     */
    protected array $repositories = [];

    /**
     * Constructor.
     *
     * @param IOInterface $io The IO
     * @param RepositoryManager $rm The repository manager
     * @param Config $config The asset config
     * @param VcsPackageFilter $packageFilter The package filter
     */
    public function __construct(IOInterface $io, RepositoryManager $rm, Config $config, HttpDownloader $httpDownloader, VcsPackageFilter $packageFilter)
    {
        $this->io = $io;
        $this->rm = $rm;
        $this->config = $config;
        $this->httpDownloader = $httpDownloader;
        $this->packageFilter = $packageFilter;
    }

    /**
     * Get the repository manager.
     *
     * @return RepositoryManager
     */
    public function getRepositoryManager(): RepositoryManager
    {
        return $this->rm;
    }

    /**
     * Get the asset config.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Set the dependency resolution manager.
     *
     * @param ResolutionManager $resolutionManager The dependency resolution manager
     *
     * @return static
     */
    public function setResolutionManager(ResolutionManager $resolutionManager): static
    {
        $this->resolutionManager = $resolutionManager;

        return $this;
    }

    /**
     * Solve the dependency resolutions.
     *
     * @param array $data
     *
     * @return array
     */
    public function solveResolutions(array $data): array
    {
        return isset($this->resolutionManager) ? $this->resolutionManager->solveResolutions($data) : $data;
    }

    /**
     * Adds asset vcs repositories.
     *
     * @param array $repositories The repositories
     *
     * @throws \UnexpectedValueException When config of repository is not an array
     * @throws \UnexpectedValueException When the config of repository has not a type defined
     * @throws \UnexpectedValueException When the config of repository has an invalid type
     */
    public function addRepositories(array $repositories): void
    {
        foreach ($repositories as $index => $repo) {
            $this->validateRepositories($index, $repo);

            if ('package' === $repo['type']) {
                $name = $repo['package']['name'];
            } else {
                $name = \is_int($index) ? preg_replace('{^https?://}i', '', $repo['url']) : $index;
                $name = $repo['name'] ?? $name;
                $repo['asset-repository-manager'] = $this;
                $repo['vcs-package-filter'] = $this->packageFilter;
            }

//            $repoInstance =
            Util::addRepository($this->io, $this->rm, $this->repositories, $name, $repo);

//            if (null === $this->pool && $repoInstance instanceof RepositoryInterface) {
//                $this->poolRepositories[] = $repoInstance;
//            }
        }
    }

    /**
     * Validates the config of repositories.
     *
     * @param int|string $index The index
     * @param array|mixed $repo The config repo
     *
     * @return void
     * @throws \UnexpectedValueException
     */
    protected function validateRepositories(int|string $index, mixed $repo): void
    {
        if (!\is_array($repo)) {
            throw new \UnexpectedValueException('Repository ' . $index . ' (' . json_encode($repo) . ') should be an array, ' . \gettype($repo) . ' given');
        }
        if (!isset($repo['type'])) {
            throw new \UnexpectedValueException('Repository ' . $index . ' (' . json_encode($repo) . ') must have a type defined');
        }

        $this->validatePackageRepositories($index, $repo);
        $this->validateVcsRepositories($index, $repo);
    }

    /**
     * Validates the config of package repositories.
     *
     * @param int|string $index The index
     * @param array|mixed $repo The config repo
     *
     * @return void
     * @throws \UnexpectedValueException
     */
    protected function validatePackageRepositories(int|string $index, mixed $repo): void
    {
        if ('package' !== $repo['type']) {
            return;
        }

        if (!isset($repo['package'])) {
            throw new \UnexpectedValueException('Repository ' . $index . ' (' . json_encode($repo) . ') must have a package definition"');
        }

        foreach (['name', 'type', 'version', 'dist'] as $key) {
            if (!isset($repo['package'][$key])) {
                throw new \UnexpectedValueException('Repository ' . $index . ' (' . json_encode($repo) . ') must have the "' . $key . '" key  in the package definition"');
            }
        }
    }

    /**
     * Validates the config of vcs repositories.
     *
     * @param int|string $index The index
     * @param array|mixed $repo The config repo
     *
     * @return void
     * @throws \UnexpectedValueException
     */
    protected function validateVcsRepositories(int|string $index, mixed $repo): void
    {
        if ('package' === $repo['type']) {
            return;
        }

        if (!str_contains($repo['type'], '-')) {
            throw new \UnexpectedValueException('Repository ' . $index . ' (' . json_encode($repo) . ') must have a type defined in this way: "%asset-type%-%type%"');
        }
        if (!isset($repo['url'])) {
            throw new \UnexpectedValueException('Repository ' . $index . ' (' . json_encode($repo) . ') must have a url defined');
        }
    }
}
