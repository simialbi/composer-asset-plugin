<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Repository\Vcs\VcsDriverInterface;
use Composer\Repository\VersionCacheInterface;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;

class VcsRepository extends \Composer\Repository\VcsRepository
{
    /**
     * @var string The package name to map
     */
    public string $assetPackageName = '';

    /**
     * @param array $repoConfig
     * @param IOInterface $io
     * @param Config $config
     * @param HttpDownloader $httpDownloader
     * @param EventDispatcher|null $dispatcher
     * @param ProcessExecutor|null $process
     * @param array|null $drivers
     * @param VersionCacheInterface|null $versionCache
     */
    public function __construct(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $dispatcher = null, ?ProcessExecutor $process = null, ?array $drivers = null, ?VersionCacheInterface $versionCache = null)
    {
        if (isset($repoConfig['asset-package-name'])) {
            $this->assetPackageName = $repoConfig['asset-package-name'];
        }
        parent::__construct($repoConfig, $io, $config, $httpDownloader, $dispatcher, $process, $drivers, $versionCache);
    }

    /**
     * {@inheritDoc}
     */
    protected function preProcess(VcsDriverInterface $driver, array $data, string $identifier): array
    {
        if (isset($this->assetPackageName)) {
            $this->packageName = $this->assetPackageName;
        }

        return parent::preProcess($driver, $data, $identifier);
    }
}
