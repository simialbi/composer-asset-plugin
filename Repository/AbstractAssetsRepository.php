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

use Composer\Config;
use Composer\DependencyResolver\Pool;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Pcre\Preg;
use Composer\Repository\ComposerRepository;
use Composer\Repository\RepositoryManager;
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Assets;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Abstract assets repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractAssetsRepository extends ComposerRepository
{
    /**
     * @var AssetTypeInterface
     */
    protected AssetTypeInterface $assetType;

    /**
     * @var AssetVcsRepository[]
     */
    protected array $repos;

    /**
     * @var bool
     */
    protected bool $searchable;

    /**
     * @var bool
     */
    protected bool $fallbackProviders;

    /**
     * @var RepositoryManager
     */
    protected RepositoryManager $repositoryManager;

    /**
     * @var AssetRepositoryManager
     */
    protected mixed $assetRepositoryManager;

    /**
     * @var VcsPackageFilter
     */
    protected mixed $packageFilter;

    /**
     * @phpstan-var array{url: string, options?: mixed[], type?: 'composer', allow_ssl_downgrade?: bool}
     * @var array
     */
    #[ArrayShape([
        'url' => 'string',
        'options' => '?array',
        'type' => '?string',
        'allow_ssl_downgrade' => '?bool'
    ])]
    protected array $repoConfig;

    /**
     * @var IOInterface
     */
    protected IOInterface $io;

    /**
     * @var HttpDownloader
     */
    protected HttpDownloader $httpDownloader;

    /**
     * @var string
     */
    protected string $baseUrl;

    /**
     * @var string
     */
    protected string $url;

    /**
     * Constructor.
     *
     * @param array $repoConfig
     * @param IOInterface $io
     * @param Config $config
     * @param HttpDownloader $httpDownloader
     * @param EventDispatcher|null $eventDispatcher
     */
    public function __construct(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, EventDispatcher $eventDispatcher = null)
    {
        $repoConfig = array_merge($repoConfig, [
            'url' => $this->getUrl(),
        ]);
        $this->assetRepositoryManager = $repoConfig['asset-repository-manager'];
        $this->repositoryManager = $this->assetRepositoryManager->getRepositoryManager();
        $this->httpDownloader = $httpDownloader;

        parent::__construct($repoConfig, $io, $config, $httpDownloader, $eventDispatcher);

        $this->assetType = Assets::createType($this->getType());
        $this->lazyProvidersUrl = $this->getPackageUrl();
        $this->providersUrl = $this->lazyProvidersUrl;
        $this->searchUrl = $this->getSearchUrl();
        $this->hasProviders = true;
        $this->packageFilter = $repoConfig['vcs-package-filter'] ?? null;
        $this->repos = [];
        $this->searchable = (bool)$this->getOption($repoConfig['asset-options'], 'searchable', true);
        $this->fallbackProviders = false;
    }

    /**
     * {@inheritDoc}
     * @throws \Seld\JsonLint\ParsingException
     */
    public function search(string $query, int $mode = 0, ?string $type = null): array
    {
        if (!$this->searchable) {
            return [];
        }

        $url = str_replace('%query%', urlencode(Util::cleanPackageName($query)), $this->searchUrl);
        $data = $this->httpDownloader->get($url)->decodeJson();
        $results = [];

        /** @var array $item */
        foreach ($data as $item) {
            $results[] = $this->createSearchItem($item);
        }

        return $results;
    }

    /**
     * @param string $name
     * @param array|null $acceptableStability
     * @param array|null $stabilityFlags
     * @param array $alreadyLoaded
     *
     * @return array
     * @throws \Exception
     */
    protected function whatProvides(
        string $name,
        array  $acceptableStability = null,
        array  $stabilityFlags = null,
        array  $alreadyLoaded = []
    ): array
    {
        if (null !== ($provides = $this->findWhatProvides($name))) {
            return $provides;
        }

        try {
            $repoName = Util::convertAliasName($name);
            $packageName = Util::cleanPackageName($repoName);
            $packageUrl = $this->buildPackageUrl($packageName);
            $cacheName = $packageName . '-' . sha1($packageName) . '-package.json';
            $data = $this->fetchFile($packageUrl, $cacheName);
            $repo = $this->createVcsRepositoryConfig($data, Util::cleanPackageName($name));
            $repo['asset-repository-manager'] = $this->assetRepositoryManager;
            $repo['vcs-package-filter'] = $this->packageFilter;
            $repo['vcs-driver-options'] = Util::getArrayValue($this->repoConfig, 'vcs-driver-options', []);

            Util::addRepository($this->io, $this->repositoryManager, $this->repos, $name, $repo);

            $this->providers[$name] = [];
        } catch (\Exception $ex) {
            $this->whatProvidesManageException($name, $ex);
        }

        return $this->providers[$name];
    }

    /**
     * Get minimal packages
     *
     * @return array
     */
    public function getMinimalPackages(): array
    {
        return [];
    }

    /**
     * Build the package url.
     *
     * @param string $packageName The package name
     *
     * @return string
     */
    protected function buildPackageUrl(string $packageName): string
    {
        return str_replace('%package%', $packageName, $this->lazyProvidersUrl);
    }

    /**
     * Finds what provides in cache or return empty array if the
     * name is not a asset package.
     *
     * @param string $name
     *
     * @return null|array
     */
    protected function findWhatProvides(string $name): ?array
    {
        $assetPrefix = $this->assetType->getComposerVendorName() . '/';

        if (!str_contains($name, $assetPrefix)) {
            return [];
        }

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $data = null;
        if ($this->hasVcsRepository($name)) {
            $this->providers[$name] = [];
            $data = $this->providers[$name];
        }

        return $data;
    }

    /**
     * Checks if the package vcs repository is already include in repository manager.
     *
     * @param string $name The package name of the vcs repository
     *
     * @return bool
     */
    protected function hasVcsRepository(string $name): bool
    {
        foreach ($this->repositoryManager->getRepositories() as $mRepo) {
            if ($mRepo instanceof AssetVcsRepository
                && $name === $mRepo->getComposerPackageName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[ArrayShape(['providers' => 'array'])]
    protected function loadRootServerFile(?int $rootMaxAge = null): array
    {
        return [
            'providers' => []
        ];
    }

    /**
     * Gets the option.
     *
     * @param array $options The options
     * @param string $key The key
     * @param mixed|null $default The default value
     *
     * @return mixed The option value or default value if key is not found
     */
    protected function getOption(array $options, string $key, mixed $default = null): mixed
    {
        return $options[$key] ?? $default;
    }

    /**
     * Creates the search result item.
     *
     * @param array $item The item
     *
     * @return array An array('name' => '...', 'description' => '...')
     */
    #[ArrayShape(['name' => 'string', 'description' => '?string'])]
    protected function createSearchItem(array $item): array
    {
        return [
            'name' => $this->assetType->getComposerVendorName() . '/' . $item['name'],
            'description' => null,
        ];
    }

    /**
     * Manage exception for "whatProvides" method.
     *
     * @param string $name
     * @param \Exception $exception
     *
     * @return void
     * @throws \Exception When exception is not a TransportException instance
     */
    protected function whatProvidesManageException(string $name, \Exception $exception): void
    {
        if ($exception instanceof TransportException) {
            $this->fallbackWhatProvides($name, $exception);

            return;
        }

        throw $exception;
    }

    /**
     * Searchs if the registry has a package with the same name exists with a
     * different camelcase.
     *
     * @param string $name
     * @param TransportException $ex
     *
     * @return void
     * @throws \Exception
     */
    protected function fallbackWhatProvides(string $name, TransportException $ex): void
    {
        $providers = [];

        if (404 === $ex->getCode() && !$this->fallbackProviders) {
            $this->fallbackProviders = true;
            $repoName = Util::convertAliasName($name);
            $results = $this->search($repoName);

            foreach ($results as $item) {
                if ($name === strtolower($item['name'])) {
                    $providers = $this->whatProvides($item['name']);

                    break;
                }
            }
        }

        $this->fallbackProviders = false;
        $this->providers[$name] = $providers;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function canonicalizeUrl(string $url): string
    {
        if ('/' === $url[0]) {
            if (Preg::isMatch('{^[^:]++://[^/]*+}', $this->url, $matches)) {
                return $matches[0] . $url;
            }

            return $this->url;
        }

        return $url;
    }

    /**
     * Gets the asset type name.
     *
     * @return string
     */
    abstract protected function getType(): string;

    /**
     * Gets the URL of repository.
     *
     * @return string
     */
    abstract protected function getUrl(): string;

    /**
     * Gets the URL for get the package information.
     *
     * @return string
     */
    abstract protected function getPackageUrl(): string;

    /**
     * Gets the URL for get the search result.
     *
     * @return string
     */
    abstract protected function getSearchUrl(): string;

    /**
     * Creates a config of vcs repository.
     *
     * @param array $data The repository config
     * @param string|null $registryName The package name in asset registry
     *
     * @return array An array('type' => '...', 'url' => '...')
     */
    #[ArrayShape([
        'type' => 'string',
        'url' => 'string'
    ])]
    abstract protected function createVcsRepositoryConfig(array $data, ?string $registryName = null): array;
}
