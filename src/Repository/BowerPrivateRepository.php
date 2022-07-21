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
use Composer\Util\HttpDownloader;
use Fxp\Composer\AssetPlugin\Exception\InvalidCreateRepositoryException;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Bower repository for Private Installations.
 *
 * @author Marcus Stueben <marcus@it-stueben.de>
 */
class BowerPrivateRepository extends AbstractAssetsRepository
{
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
        $this->url = $repoConfig['private-registry-url'] ?? null;

        parent::__construct($repoConfig, $io, $config, $httpDownloader, $eventDispatcher);
    }

    /**
     * {@inheritDoc}
     */
    protected function getType(): string
    {
        return 'bower';
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
        return $this->canonicalizeUrl($this->baseUrl . '/search/%query%');
    }

    /**
     * {@inheritDoc}
     * @throws InvalidCreateRepositoryException
     */
    #[ArrayShape([
        'type' => 'string',
        'url' => 'string',
        'name' => 'string'
    ])]
    protected function createVcsRepositoryConfig(array $data, string $registryName = null): array
    {
        $myArray = [];
        $myArray['repository'] = $data;

        return [
            'type' => $this->assetType->getName() . '-vcs',
            'url' => $this->getVcsRepositoryUrl($myArray, $registryName),
            'name' => $registryName
        ];
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
            $msg .= PHP_EOL . 'If the config comes from the Bower Private Registry, override the config with a custom Asset VCS Repository';
            $ex = new InvalidCreateRepositoryException($msg);
            $ex->setData($data);

            throw $ex;
        }

        return (string)$data['repository']['url'];
    }
}
