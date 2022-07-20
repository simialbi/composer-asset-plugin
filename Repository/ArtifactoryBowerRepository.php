<?php
/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) Simon Karlen <simi.albi@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;
use Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;

class ArtifactoryBowerRepository extends BowerRepository
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
        if (!isset($repoConfig['registry-url'])) {
            throw new InvalidArgumentException('You need to set the `fxp-asset.artifactory-url` in the composer.json config.');
        }
        $this->url = $repoConfig['registry-url'] ?? null;

        parent::__construct($repoConfig, $io, $config, $httpDownloader, $eventDispatcher, $process);
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
        return $this->canonicalizeUrl($this->baseUrl . '/packages/%package%');
    }

    /**
     * {@inheritDoc}
     */
    protected function getSearchUrl(): string
    {
        return $this->canonicalizeUrl($this->baseUrl . '/%query');
    }

    /**
     * Gets the URL for get the binaries.
     *
     * @return string
     */
    protected function getBinariesUrl(): string
    {
        return $this->canonicalizeUrl($this->baseUrl . '/binaries/%package%.git/%version%');
    }

    /**
     * Gets the URL for the references.
     *
     * @return string
     */
    protected function getRefsUrl(): string
    {
        return $this->canonicalizeUrl($this->baseUrl . '/refs/%package%');
    }

    /**
     * Build the references URL
     *
     * @param array $data The return data of package url
     *
     * @return string
     */
    protected function buildRefsUrl(array $data): string
    {
        return str_replace('%package%', str_replace('art://', '', $data['url']), $this->getRefsUrl());
    }

    /**
     * Build the binaries url
     *
     * @param string $packageName The package name
     * @param string $version The version string
     *
     * @return string
     */
    protected function buildBinariesUrl(string $packageName, string $version): string
    {
        return str_replace(['%package%', '%version%'], [$packageName, $version], $this->getBinariesUrl());
    }

    /**
     * {@inheritDoc}
     */
    protected function whatProvides(
        string $name,
        ConstraintInterface $constraint,
        ?array $acceptableStability = null,
        ?array $stabilityFlags = null,
        array $alreadyLoaded = []
    ): array
    {
        if (!str_starts_with($name, "{$this->getType()}-asset/")) {
            return [];
        }

        $packages = null;
        try {
            $repoName = Util::convertAliasName($name);
            $packageName = Util::cleanPackageName($repoName);
            $cacheKey = $packageName . '-artifactory-' . strtr($name, '/', '$') . '-package.json';

            if ($contents = $this->cache->read($cacheKey)) {
                $packages = JsonFile::parseJson($contents);
            } else {
                $packageUrl = $this->buildPackageUrl($packageName);
                $data = $this->fetchFile($packageUrl, '');
                $packageName = str_replace('art://', '', $data['url']);
                $refsUrl = $this->buildRefsUrl($data);
                $response = $this->httpDownloader->get($refsUrl);
                $refs = preg_split('/[\r\n]+/', $response->getBody());

                foreach ($refs as $ref) {
                    if (preg_match('/^([a-f\d]{44})\s+refs\/tags\/(\S+)/', $ref, $matches)) {
                        try {
                            $packages[] = $this->assetType->getPackageConverter()->convert([
                                'name' => $name,
                                'version' => [$matches[2], $matches[1], $this->buildBinariesUrl($packageName, $matches[2])],
                                'type' => $this->getType()
                            ]);
                        } catch (\UnexpectedValueException) {
                        }
                    }
                }
                $this->cache->write($cacheKey, JsonFile::encode($packages, 0));
            }

            $packages = $this->loader->loadPackages($packages);
        } catch (TransportException) {
            $packages = [];
        }

        return $packages;
    }

    /**
     * {@inheritDoc}
     */
    #[ArrayShape(['type' => 'string', 'url' => 'string', 'name' => 'string'])]
    protected function createVcsRepositoryConfig(array $data, string $registryName = null): array
    {
        $config = parent::createVcsRepositoryConfig($data, $registryName);
        $config['url'] = str_replace('%package%', str_replace('art://', '', $data['url']), $this->getBinariesUrl());

        return $config;
    }
}
