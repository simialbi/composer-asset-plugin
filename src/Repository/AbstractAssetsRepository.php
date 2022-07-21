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
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Pcre\Preg;
use Composer\Repository\ComposerRepository;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;
use Fxp\Composer\AssetPlugin\Assets;
use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;
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
     * @var Config
     */
    protected Config $config;

    /**
     * @var ProcessExecutor|null
     */
    protected ?ProcessExecutor $process;

    /**
     * @var VersionParser
     */
    protected VersionParser $versionParser;

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
     * @param ProcessExecutor|null $process
     */
    public function __construct(
        array            $repoConfig,
        IOInterface      $io,
        Config           $config,
        HttpDownloader   $httpDownloader,
        ?EventDispatcher $eventDispatcher = null,
        ?ProcessExecutor $process = null
    )
    {
        $this->config = $config;
        $repoConfig = array_merge($repoConfig, [
            'url' => $this->getUrl(),
        ]);
        $this->assetRepositoryManager = $repoConfig['asset-repository-manager'];
        $this->repositoryManager = $this->assetRepositoryManager->getRepositoryManager();

        parent::__construct($repoConfig, $io, $config, $httpDownloader, $eventDispatcher);

        $this->io = $io;
        $this->repoConfig = $repoConfig;
        $this->httpDownloader = $httpDownloader;
        $this->process = $process;
        $this->url = $repoConfig['url'];
        $this->baseUrl = rtrim(Preg::replace('{(?:/[^/\\\\]+\.json)?(?:[?#].*)?$}', '', $this->url), '/');
        $this->assetType = Assets::createType($this->getType());
        $this->lazyProvidersUrl = $this->getPackageUrl();
        $this->providersUrl = $this->lazyProvidersUrl;
        $this->searchUrl = $this->searchUrl ?? $this->getSearchUrl();
        $this->versionParser = new VersionParser();
        $this->hasProviders = true;
        $this->packageFilter = $repoConfig['vcs-package-filter'] ?? null;
        $this->repos = [];
        $this->searchable = (bool)$this->getOption($repoConfig['asset-options'], 'searchable', true);
        $this->fallbackProviders = false;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     * @throws \Exception
     */
    #[ArrayShape([
        'namesFound' => 'array',
        'packages' => 'array'
    ])]
    public function loadPackages(array $packageNameMap, array $acceptableStability, array $stabilityFlags, array $alreadyLoaded = []): array
    {
        $packages = [];
        $namesFound = [];

        if ($this->hasProviders) {
            foreach ($packageNameMap as $name => $constraint) {
                $matches = [];
                $candidates = $this->whatProvides($name, $constraint, $acceptableStability, $stabilityFlags, $alreadyLoaded);
                foreach ($candidates as $candidate) {
                    if (!is_object($candidate) || $candidate->getName() !== $name) {
                        continue;
                    }

                    $namesFound[$name] = true;

                    if (!$constraint || $constraint->matches(new Constraint('==', $candidate->getVersion()))) {
                        $matches[spl_object_hash($candidate)] = $candidate;
                        if ($candidate instanceof AliasPackage && !isset($matches[spl_object_hash($candidate->getAliasOf())])) {
                            $matches[spl_object_hash($candidate->getAliasOf())] = $candidate->getAliasOf();
                        }
                    }
                }

                // add aliases of matched packages even if they did not match the constraint
                foreach ($candidates as $candidate) {
                    if ($candidate instanceof AliasPackage) {
                        if (isset($matches[spl_object_hash($candidate->getAliasOf())])) {
                            $matches[spl_object_hash($candidate)] = $candidate;
                        }
                    }
                }
                $packages = array_merge($packages, $matches);
            }
        }

        return [
            'namesFound' => $namesFound,
            'packages' => $packages
        ];
    }

    /**
     * Search package by name
     * @param string $name
     * @param ConstraintInterface $constraint
     * @param array|null $acceptableStability
     * @param array|null $stabilityFlags
     * @param array $alreadyLoaded
     *
     * @return BasePackage[]
     * @throws \Composer\Repository\RepositorySecurityException
     */
    protected function whatProvides(
        string              $name,
        ConstraintInterface $constraint,
        ?array              $acceptableStability = null,
        ?array              $stabilityFlags = null,
        array               $alreadyLoaded = []
    ): array
    {
        if (!str_starts_with($name, "{$this->getType()}-asset/")) {
            return [];
        }

        $packages = null;
        $data = null;
        try {
            $repoName = Util::convertAliasName($name);
            $packageName = Util::cleanPackageName($repoName);
            $packageUrl = $this->buildPackageUrl($packageName);
            $cacheKey = $packageName . '-' . strtr($name, '/', '$') . '-package.json';

            if ($contents = $this->cache->read($cacheKey)) {
                $contents = json_decode($contents, true);

                $data = $contents;
            }

            if (!$data) {
                $data = $this->fetchFile($packageUrl, $cacheKey);
            }

            if (!$packages) {
                $repo = $this->createVcsRepositoryConfig($data, Util::cleanPackageName($name));
                $repo['asset-repository-manager'] = $this->assetRepositoryManager;
                $repo['vcs-package-filter'] = $this->packageFilter;
                $repo['vcs-driver-options'] = Util::getArrayValue($this->repoConfig, 'vcs-driver-options', []);
                $repo = Util::addRepository($this->io, $this->repositoryManager, $this->repos, $name, $repo);
                /** @var \Fxp\Composer\AssetPlugin\Repository\AssetVcsRepository $repo */

                $packages = $repo->packages ?? $repo->loadPackages([$repoName => $constraint], $acceptableStability, $stabilityFlags, $alreadyLoaded)['packages'];
            }
        } catch (TransportException) {
            $packages = [];
        }

        return $packages;
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
            'description' => $item['description'] ?? null
        ];
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
        'url' => 'string',
        'registry-versions' => '?array'
    ])]
    abstract protected function createVcsRepositoryConfig(array $data, ?string $registryName = null): array;
}
