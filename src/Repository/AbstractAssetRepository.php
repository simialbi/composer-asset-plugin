<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Cache;
use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\LoaderInterface;
use Composer\Package\PackageInterface;
use Composer\Package\Version\StabilityFilter;
use Composer\Package\Version\VersionParser;
use Composer\PartialComposer;
use Composer\Pcre\Preg;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\ConfigurableRepositoryInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositorySecurityException;
use Composer\Semver\CompilingMatcher;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Util\HttpDownloader;
use Composer\Util\Url;
use JetBrains\PhpStorm\ArrayShape;

abstract class AbstractAssetRepository implements ConfigurableRepositoryInterface, RepositoryInterface
{
    protected array $options = [];
    protected array $repoConfig;
    protected PartialComposer $composer;
    protected IOInterface $io;
    protected Config $config;
    protected HttpDownloader $httpDownloader;
    protected ?EventDispatcher $eventDispatcher;
    protected LoaderInterface $loader;
    protected array $packages = [];
    protected array $packageMap = [];
    protected Cache $cache;

    protected string $url;
    protected string|null $lazyLoadUrl = null;
    protected string|null $searchUrl = null;

    /**
     * Create a new asset repository
     *
     * @param array $repoConfig The configuration for the repository
     * @param PartialComposer $composer the composer instance
     * @param IOInterface $io The composer input output interface
     * @param Config $config The composer configuration
     * @param HttpDownloader $httpDownloader The downloader
     * @param EventDispatcher|null $eventDispatcher The event handler
     */
    public function __construct(array $repoConfig, PartialComposer $composer, IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $eventDispatcher)
    {
        $this->repoConfig = $repoConfig;
        $this->composer = $composer;
        $this->io = $io;
        $this->config = $config;
        $this->httpDownloader = $httpDownloader;
        $this->eventDispatcher = $eventDispatcher;
        $this->loader = new ArrayLoader();
        $this->cache = new Cache($io, $config->get('cache-repo-dir') . '/' . Preg::replace('{[^a-z0-9.]}i', '-', Url::sanitize($this->getUrl())), 'a-z0-9.$~');
        $this->cache->setReadOnly($config->get('cache-read-only'));
        if (isset($repoConfig['options'])) {
            $this->options = $repoConfig['options'];
        }
        if (isset($repoConfig['url'])) {
            $this->url = $repoConfig['url'];
        }
        if (isset($repoConfig['lazy-load-url'])) {
            $this->lazyLoadUrl = $repoConfig['lazy-load-url'];
        }
        if (isset($repoConfig['search-url'])) {
            $this->searchUrl = $repoConfig['search-url'];
        }
    }

    /**
     * Get the actual repo configuration
     *
     * @return array
     */
    public function getRepoConfig(): array
    {
        return $this->repoConfig;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->packages);
    }

    /**
     * {@inheritDoc}
     */
    public function hasPackage(PackageInterface $package): bool
    {
        return isset($this->packageMap[$package->getUniqueName()]);
    }

    /**
     * {@inheritDoc}
     */
    public function findPackage(string $name, $constraint): ?BasePackage
    {
        $name = strtolower($name);

        if (!$constraint instanceof ConstraintInterface) {
            $versionParser = new VersionParser();
            $constraint = $versionParser->parseConstraints($constraint);
        }

        foreach ($this->getPackages() as $package) {
            if ($name === $package->getName()) {
                $pkgConstraint = new Constraint('==', $package->getVersion());
                if ($constraint->matches($pkgConstraint)) {
                    return $package;
                }
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function findPackages(string $name, $constraint = null): array
    {
        // normalize name
        $name = strtolower($name);
        $packages = [];

        if (null !== $constraint && !$constraint instanceof ConstraintInterface) {
            $versionParser = new VersionParser();
            $constraint = $versionParser->parseConstraints($constraint);
        }

        foreach ($this->getPackages() as $package) {
            if ($name === $package->getName()) {
                if (null === $constraint || $constraint->matches(new Constraint('==', $package->getVersion()))) {
                    $packages[] = $package;
                }
            }
        }

        return $packages;
    }

    /**
     * {@inheritDoc}
     */
    public function getPackages(): array
    {
        return $this->packages;
    }


    /**
     * Adds a new package to the repository
     *
     * @param PackageInterface $package The package to add
     *
     * @return void
     */
    public function addPackage(PackageInterface $package): void
    {
        if (!$package instanceof BasePackage) {
            throw new \InvalidArgumentException('Only subclasses of BasePackage are supported');
        }
        $package->setRepository($this);
        $this->packages[] = $package;

        if ($package instanceof AliasPackage) {
            $aliasedPackage = $package->getAliasOf();
            if (null === $aliasedPackage->getRepository()) {
                $this->addPackage($aliasedPackage);
            }
        }
    }


    /**
     * {@inheritDoc}
     */
    #[ArrayShape([
        'namesFound' => 'array',
        'packages' => 'array'
    ])] public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []): array
    {
        $namesFound = [];
        $packages = [];
        foreach ($packageNameMap as $name => $constraint) {
            if (!Preg::match('#^' . $this->getRepoType() . '-asset/#', $name)) {
                continue;
            }
            unset($data);
            try {
                if (!isset($this->packageMap[$name])) {
                    $cleanName = str_replace( $this->getRepoType() . '-asset/', '', $name);
                    $url = str_replace('%package%', $cleanName, $this->getLazyLoadUrl());

                    if ($cachedData = $this->cache->read($this->getRepoType(). '-' . $cleanName . '.json')) {
                        $cachedData = json_decode($cachedData, true);
                        if (($age = $this->cache->getAge($this->getRepoType(). '-' . $cleanName . '.json')) && $age <= 900) {
                            $data = $cachedData;
                        } elseif (isset($cachedData['last-modified'])) {
                            $response = $this->fetchFileIfLastModified($url, $this->getRepoType(). '-' . $cleanName . '.json', $cachedData['last-modified']);
                            $data = true === $response ? $cachedData : $response;
                        }
                    }

                    if (!isset($data)) {
                        $data = $this->fetchFile($url, $this->getRepoType(). '-' . $cleanName . '.json', true);
                    }
                    unset($data['last-modified']);

                    $this->packageMap[$name] = $url;

                    foreach ($this->convertPackage($data) as $item) {
                        $this->addPackage($this->loader->load($item));
                    }
                }

                foreach ($this->packages as $package) {
                    /** @var \Composer\Package\CompletePackage $package */
                    if ($name === $package->getName() && $this->isVersionAcceptable(
                            $constraint,
                            $package->getName(),
                            ['version' => $package->getPrettyVersion(), 'version_normalized' => $package->getVersion()],
                            $acceptableStabilities,
                            $stabilityFlags)) {
                        $namesFound[$package->getName()] = true;
                        $packages[] = $package;
                    }
                }
            } catch (\ErrorException|\Seld\JsonLint\ParsingException|\InvalidArgumentException) {
                continue;
            }
        }

        return [
            'namesFound' => $namesFound,
            'packages' => $packages
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws \ErrorException|\Seld\JsonLint\ParsingException
     */
    #[ArrayShape([[
        'name' => 'string',
        'description' => 'string|null',
        'abandoned' => 'string|true'
    ]])]
    public function search(string $query, int $mode = 0, ?string $type = null): array
    {
        if (null === ($searchUrl = $this->getSearchUrl()) || $mode === self::SEARCH_VENDOR) {
            return [];
        }

        $searchUrl = str_replace('%query%', $query, $searchUrl);
        $data = $this->fetchFile($searchUrl);
        $results = [];

        foreach ($data as $item) {
            $results[] = $this->convertResultItem($item);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function getProviders(string $packageName)
    {
        // TODO: Implement getProviders() method.
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getRepoName(): string;

    /**
     * Get the repository type.
     *
     * @return string
     */
    abstract public function getRepoType(): string;

    /**
     * Get the repository url.
     *
     * @return string
     */
    public function getUrl(): string {
        return $this->url;
    }

    /**
     * Get the lazy load package url.
     *
     * @return string|null
     */
    public function getLazyLoadUrl(): ?string {
        return $this->lazyLoadUrl;
    }

    /**
     * Get the search url (if is one).
     *
     * @return string|null
     */
    public function getSearchUrl(): ?string {
        return $this->searchUrl;
    }

    /**
     * Convert registry search result item.
     *
     * @param array $item The item to convert
     *
     * @return array
     */
    abstract protected function convertResultItem(array $item): array;

    /**
     * Convert package information to composer format.
     *
     * @param array $item The json decoded package information.
     *
     * @return array
     */
    abstract protected function convertPackage(array $item): array;

    /**
     * Fetch a file or return data from cache according to timestamp passed.
     *
     * @param string $filename The file url to download
     * @param string $cacheKey The cache key to check
     * @param string $lastModifiedTime The last modified timestamp
     * @return bool|array
     */
    final protected function fetchFileIfLastModified(string $filename, string $cacheKey, string $lastModifiedTime): bool|array
    {
        try {
            $options = $this->options;
            if ($this->eventDispatcher) {
                $preFileDownloadEvent = new PreFileDownloadEvent(PluginEvents::PRE_FILE_DOWNLOAD, $this->httpDownloader, $filename, 'metadata', ['repository' => $this]);
                $preFileDownloadEvent->setTransportOptions($this->options);
                $this->eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);
                $filename = $preFileDownloadEvent->getProcessedUrl();
                $options = $preFileDownloadEvent->getTransportOptions();
            }

            if (isset($options['http']['header'])) {
                $options['http']['header'] = (array)$options['http']['header'];
            }
            $options['http']['header'][] = 'If-Modified-Since: ' . $lastModifiedTime;
            $response = $this->httpDownloader->get($filename, $options);
            $json = (string)$response->getBody();
            if ($json === '' && $response->getStatusCode() === 304) {
                return true;
            }

            if ($this->eventDispatcher) {
                $postFileDownloadEvent = new PostFileDownloadEvent(PluginEvents::POST_FILE_DOWNLOAD, null, null, $filename, 'metadata', ['response' => $response, 'repository' => $this]);
                $this->eventDispatcher->dispatch($postFileDownloadEvent->getName(), $postFileDownloadEvent);
            }

            $data = $response->decodeJson();
            HttpDownloader::outputWarnings($this->io, $this->getUrl(), $data);

            $lastModifiedDate = $response->getHeader('last-modified');
            $response->collect();
            if ($lastModifiedDate) {
                $data['last-modified'] = $lastModifiedDate;
                $json = JsonFile::encode($data, 0);
            }
            if (!$this->cache->isReadOnly()) {
                $this->cache->write($cacheKey, $json);
            }

            return $data;
        } catch (\Exception $e) {
            if ($e instanceof \LogicException) {
                throw $e;
            }

            if ($e instanceof TransportException && $e->getStatusCode() === 404) {
                throw $e;
            }

            return true;
        }
    }

    /**
     * Fetch a file and optinally cache the contents
     * @param string $filename The file url to fetch
     * @param string|null $cacheKey The cache key to cache the contents
     * @param bool $storeLastModifiedTime Whether or not to store the last modified time
     * @return mixed
     * @throws \ErrorException|\Seld\JsonLint\ParsingException
     */
    final protected function fetchFile(string $filename, ?string $cacheKey = null, bool $storeLastModifiedTime = false): mixed
    {
        if (null === $cacheKey) {
            $cacheKey = $filename;
            $filename = $this->getUrl() . '/' . $filename;
        }

        // url-encode $ signs in URLs as bad proxies choke on them
        if (($pos = strpos($filename, '$')) && Preg::isMatch('{^https?://}i', $filename)) {
            $filename = substr($filename, 0, $pos) . '%24' . substr($filename, $pos + 1);
        }

        $retries = 3;
        while ($retries-- > 0) {
            try {
                $options = $this->options;
                if ($this->eventDispatcher) {
                    $preFileDownloadEvent = new PreFileDownloadEvent(PluginEvents::PRE_FILE_DOWNLOAD, $this->httpDownloader, $filename, 'metadata', ['repository' => $this]);
                    $preFileDownloadEvent->setTransportOptions($this->options);
                    $this->eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);
                    $filename = $preFileDownloadEvent->getProcessedUrl();
                    $options = $preFileDownloadEvent->getTransportOptions();
                }

                $response = $this->httpDownloader->get($filename, $options);
                $json = (string)$response->getBody();

                if ($this->eventDispatcher) {
                    $postFileDownloadEvent = new PostFileDownloadEvent(
                        PluginEvents::POST_FILE_DOWNLOAD,
                        null,
                        null,
                        $filename,
                        'metadata',
                        ['response' => $response, 'repository' => $this]
                    );
                    $this->eventDispatcher->dispatch($postFileDownloadEvent->getName(), $postFileDownloadEvent);
                }

                $data = $response->decodeJson();
                HttpDownloader::outputWarnings($this->io, $this->getUrl(), $data);

                if ($cacheKey && !$this->cache->isReadOnly()) {
                    if ($storeLastModifiedTime) {
                        $lastModifiedDate = $response->getHeader('last-modified');
                        if ($lastModifiedDate) {
                            $data['last-modified'] = $lastModifiedDate;
                            $json = JsonFile::encode($data, 0);
                        }
                    }
                    $this->cache->write($cacheKey, $json);
                }

                $response->collect();

                break;
            } catch (\Exception $e) {
                if ($e instanceof \LogicException) {
                    throw $e;
                }

                if ($e instanceof TransportException && $e->getStatusCode() === 404) {
                    throw $e;
                }

                if ($e instanceof RepositorySecurityException) {
                    throw $e;
                }

                if ($cacheKey && ($contents = $this->cache->read($cacheKey))) {
                    $data = JsonFile::parseJson($contents, $this->cache->getRoot() . $cacheKey);

                    break;
                }

                throw $e;
            }
        }

        if (!isset($data)) {
            throw new \LogicException("ComposerRepository: Undefined \$data. Please report at https://github.com/composer/composer/issues/new.");
        }

        return $data;
    }

    /**
     * Check if version is acceptable
     *
     * @param ConstraintInterface|null $constraint
     * @param string $name package name (must be lowercase already)
     * @param array $versionData
     * @param array|null $acceptableStabilities
     * @param array|null $stabilityFlags an array of package name => BasePackage::STABILITY_* value
     *
     * @return bool
     */
    protected function isVersionAcceptable(?ConstraintInterface $constraint, string $name, array $versionData, ?array $acceptableStabilities = null, ?array $stabilityFlags = null): bool
    {
        $versions = [$versionData['version_normalized']];

        if ($alias = $this->loader->getBranchAlias($versionData)) {
            $versions[] = $alias;
        }

        foreach ($versions as $version) {
            if (null !== $acceptableStabilities && null !== $stabilityFlags && !StabilityFilter::isPackageAcceptable($acceptableStabilities, $stabilityFlags, [$name], VersionParser::parseStability($version))) {
                continue;
            }

            if ($constraint && !CompilingMatcher::match($constraint, Constraint::OP_EQ, $version)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
