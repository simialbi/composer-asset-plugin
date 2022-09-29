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
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;
use Fxp\Composer\AssetPlugin\Converter\NpmPackageUtil;
use Fxp\Composer\AssetPlugin\Converter\PackageUtil;
use Fxp\Composer\AssetPlugin\Exception\InvalidCreateRepositoryException;
use JetBrains\PhpStorm\ArrayShape;

/**
 * NPM repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class NpmRepository extends AbstractAssetsRepository
{
    /**
     * {@inheritDoc}
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
        $cfg = $config->get('fxp-asset') ?? [];
        $this->url = $cfg['npm-registry-url'] ?? 'https://registry.npmjs.org';
        $this->searchUrl = $cfg['npm-search-url'] ?? 'https://www.npmjs.com/search/suggestions?q=%query%';

        parent::__construct($repoConfig, $io, $config, $httpDownloader, $eventDispatcher, $process);
    }

    /**
     * {@inheritDoc}
     */
    protected function getType(): string
    {
        return 'npm';
    }

    /**
     * {@inheritDoc}
     */
    protected function getUrl(): string
    {
        return $this->url;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPackageUrl(): string
    {
        return $this->canonicalizeUrl($this->baseUrl . '/%package%');
    }

    /**
     * {@inheritDoc}
     */
    protected function getSearchUrl(): string
    {
        return $this->canonicalizeUrl($this->searchUrl);
    }

    /**
     * {@inheritDoc}
     */
    protected function buildPackageUrl(string $packageName): string
    {
        $packageName = urlencode(NpmPackageUtil::revertName($packageName));
        $packageName = str_replace('%40', '@', $packageName);

        return parent::buildPackageUrl($packageName);
    }

    /**
     * {@inheritDoc}
     * @throws InvalidCreateRepositoryException
     */
    #[ArrayShape([
        'type' => 'string',
        'url' => 'string',
        'name' => 'string',
        'registry-versions' => 'array'
    ])]
    protected function createVcsRepositoryConfig(array $data, string $registryName = null): array
    {
        $type = $data['repository']['type'] ?? 'vcs';

        // Add release date in $packageConfigs
        if (isset($data['versions'], $data['time'])) {
            $time = $data['time'];
            array_walk($data['versions'], function (&$packageConfigs, $version) use ($time) {
                PackageUtil::convertStringKey($time, $version, $packageConfigs, 'time');
            });
        }

        return [
            'type' => $this->assetType->getName() . '-' . $type,
            'url' => $this->getVcsRepositoryUrl($data, $registryName),
            'name' => $registryName,
            'registry-versions' => isset($data['versions']) ? $this->createArrayRepositoryConfig($data['versions']) : [],
        ];
    }

    /**
     * Create the array repository with the asset configs.
     *
     * A warning message is displayed if the constraint versions of packages
     * are broken. These versions are skipped and the plugin hope that other
     * versions will be OK.
     *
     * @param array $packageConfigs The configs of assets package versions
     *
     * @return CompletePackageInterface[]
     */
    protected function createArrayRepositoryConfig(array $packageConfigs): array
    {
        $packages = [];
        $loader = new ArrayLoader();

        foreach ($packageConfigs as $version => $config) {
            try {
                $config['version'] = $version;
                $config = $this->assetType->getPackageConverter()->convert($config);
                $config = $this->assetRepositoryManager->solveResolutions($config);
                $packages[] = $loader->load($config);
            } catch (\UnexpectedValueException $exception) {
                $this->io->write("<warning>Skipped {$config['name']} version {$version}: {$exception->getMessage()}</warning>", IOInterface::VERBOSE);
            }
        }

        return $packages;
    }

    /**
     * Get the URL of VCS repository.
     *
     * @param array $data The repository config
     * @param string|null $registryName The package name in asset registry
     *
     * @return string
     * @throws InvalidCreateRepositoryException When the repository.url parameter does not exist
     */
    protected function getVcsRepositoryUrl(array $data, ?string $registryName = null): string
    {
        if (!isset($data['repository']['url'])) {
            $msg = sprintf('The "repository.url" parameter of "%s" %s asset package must be present for create a VCS Repository', $registryName, $this->assetType->getName());
            $msg .= PHP_EOL . 'If the config comes from the NPM Registry, override the config with a custom Asset VCS Repository';
            $ex = new InvalidCreateRepositoryException($msg);
            $ex->setData($data);

            throw $ex;
        }

        return $this->convertUrl((string)$data['repository']['url']);
    }

    /**
     * Convert the url repository.
     *
     * @param string $url The url
     *
     * @return string The url converted
     */
    private function convertUrl(string $url): string
    {
        if (str_starts_with($url, 'git+http')) {
            return substr($url, 4);
        }

        return $url;
    }
}
