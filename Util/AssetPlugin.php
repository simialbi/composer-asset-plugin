<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Util;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Fxp\Composer\AssetPlugin\Assets;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Installer\AssetInstaller;
use Fxp\Composer\AssetPlugin\Installer\BowerInstaller;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Helper for FxpAssetPlugin.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class AssetPlugin
{
    /**
     * Adds asset installers.
     *
     * @param Config $config
     * @param Composer $composer
     * @param IOInterface $io
     *
     * @return void
     */
    public static function addInstallers(Config $config, Composer $composer, IOInterface $io): void
    {
        $im = $composer->getInstallationManager();

        $im->addInstaller(new BowerInstaller($config, $io, $composer, Assets::createType('bower')));
        $im->addInstaller(new AssetInstaller($config, $io, $composer, Assets::createType('npm')));
    }

    /**
     * Remove asset installers.
     *
     * @param Composer $composer
     *
     * @return void
     */
    public static function removeInstallers(Composer $composer): void
    {
        $im = $composer->getInstallationManager();

        $bower = $im->getInstaller('bower');
        $npm = $im->getInstaller('npm');

        $im->removeInstaller($bower);
        $im->removeInstaller($npm);
    }

    /**
     * Creates the asset options.
     *
     * @param array $config The composer config section of asset options
     * @param string $assetType The asset type
     *
     * @return array The asset registry options
     */
    public static function createAssetOptions(array $config, string $assetType): array
    {
        $options = [];

        foreach ($config as $key => $value) {
            if (str_starts_with($key, $assetType . '-')) {
                $key = substr($key, \strlen($assetType) + 1);
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Create the repository config.
     *
     * @param AssetRepositoryManager $arm The asset repository manager
     * @param VcsPackageFilter $filter The vcs package filter
     * @param Config $config The plugin config
     * @param string $assetType The asset type
     *
     * @return array
     */
    #[ArrayShape([
        'asset-repository-manager' => '\Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager',
        'vcs-package-filter' => '\Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter',
        'asset-options' => 'array',
        'vcs-driver-options' => 'array|null'
    ])]
    public static function createRepositoryConfig(AssetRepositoryManager $arm, VcsPackageFilter $filter, Config $config, string $assetType): array
    {
        return [
            'asset-repository-manager' => $arm,
            'vcs-package-filter' => $filter,
            'asset-options' => static::createAssetOptions($config->getArray('registry-options'), $assetType),
            'vcs-driver-options' => $config->getArray('vcs-driver-options'),
        ];
    }

    /**
     * Adds asset registry repositories.
     *
     * @throws
     */
    public static function addRegistryRepositories(AssetRepositoryManager $arm, VcsPackageFilter $filter, Config $config): void
    {
        foreach (Assets::getRegistryFactories() as $factoryClass) {
            $ref = new \ReflectionClass($factoryClass);

            if ($ref->implementsInterface('Fxp\Composer\AssetPlugin\Repository\RegistryFactoryInterface')) {
                \call_user_func([$factoryClass, 'create'], $arm, $filter, $config);
            }
        }
    }

    /**
     * Sets vcs type repositories.
     */
    public static function setVcsTypeRepositories(RepositoryManager $rm): void
    {
        foreach (Assets::getTypes() as $assetType) {
            foreach (Assets::getVcsRepositoryDrivers() as $driverType => $repositoryClass) {
                $rm->setRepositoryClass($assetType . '-' . $driverType, $repositoryClass);
            }
        }
    }

    /**
     * Adds the main file definitions from the root package.
     *
     * @param Config $config
     * @param PackageInterface $package
     * @param string $section
     *
     * @return PackageInterface
     */
    public static function addMainFiles(Config $config, PackageInterface $package, string $section = 'main-files'): PackageInterface
    {
        if ($package instanceof Package) {
            $packageExtra = $package->getExtra();
            $rootMainFiles = $config->getArray($section);

            foreach ($rootMainFiles as $packageName => $files) {
                if ($packageName === $package->getName()) {
                    $packageExtra['bower-asset-main'] = $files;

                    break;
                }
            }

            $package->setExtra($packageExtra);
        }

        return $package;
    }
}
