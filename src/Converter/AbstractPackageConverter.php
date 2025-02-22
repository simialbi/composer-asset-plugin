<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Converter;

use Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Abstract class for converter for asset package to composer package.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractPackageConverter implements PackageConverterInterface
{
    /**
     * @var AssetTypeInterface
     */
    protected AssetTypeInterface $assetType;

    /**
     * Constructor.
     */
    public function __construct(AssetTypeInterface $assetType)
    {
        $this->assetType = $assetType;
    }

    public function convert(array $data, array &$vcsRepos = []): array
    {
        $keys = $this->getMapKeys();
        $dependencies = $this->getMapDependencies();
        $extras = $this->getMapExtras();

        return $this->convertData($data, $keys, $dependencies, $extras, $vcsRepos);
    }

    /**
     * Converts the all keys (keys, dependencies and extra keys).
     *
     * @param array $asset The asset data
     * @param array $keys The map of asset key and composer key
     * @param array $dependencies The map of asset dependency key and composer dependency key
     * @param array $extras The map of asset key and composer extra key
     * @param array $vcsRepos The list of new vcs configs
     *
     * @return array The composer package converted
     */
    protected function convertData(array $asset, array $keys, array $dependencies, array $extras, array &$vcsRepos = []): array
    {
        $composer = [];

        foreach ($keys as $assetKey => $composerKey) {
            $this->convertKey($asset, $assetKey, $composer, $composerKey);
        }

        foreach ($dependencies as $assetKey => $composerKey) {
            $this->convertDependencies($asset, $assetKey, $composer, $composerKey, $vcsRepos);
        }

        foreach ($extras as $assetKey => $composerKey) {
            $this->convertExtraKey($asset, $assetKey, $composer, $composerKey);
        }

        return $composer;
    }

    /**
     * Converts the simple key of package.
     *
     * @param array $asset The asset data
     * @param string $assetKey The asset key
     * @param array $composer The composer data
     * @param array|string $composerKey The composer key or array with composer key name and closure
     *
     * @return void
     * @throws InvalidArgumentException When the 'composerKey' argument of asset packager converter is not an string or an array with the composer key and closure
     */
    protected function convertKey(array $asset, string $assetKey, array &$composer, array|string $composerKey): void
    {
        if (\is_array($composerKey)) {
            PackageUtil::convertArrayKey($asset, $assetKey, $composer, $composerKey);
        } else {
            PackageUtil::convertStringKey($asset, $assetKey, $composer, $composerKey);
        }
    }

    /**
     * Converts the extra key of package.
     *
     * @param array $asset The asset data
     * @param string $assetKey The asset extra key
     * @param array $composer The composer data
     * @param array|string $composerKey The composer extra key or array with composer extra key name and closure
     * @param string $extraKey The extra key name
     *
     * @return void
     */
    protected function convertExtraKey(array $asset, string $assetKey, array &$composer, array|string $composerKey, string $extraKey = 'extra'): void
    {
        $extra = $composer[$extraKey] ?? [];

        $this->convertKey($asset, $assetKey, $extra, $composerKey);

        if (\count($extra) > 0) {
            $composer[$extraKey] = $extra;
        }
    }

    /**
     * Converts simple key of package.
     *
     * @param array $asset The asset data
     * @param string $assetKey The asset key of dependencies
     * @param array $composer The composer data
     * @param string $composerKey The composer key of dependencies
     * @param array $vcsRepos The list of new vcs configs
     *
     * @return void
     */
    protected function convertDependencies(array $asset, string $assetKey, array &$composer, string $composerKey, array &$vcsRepos = []): void
    {
        if (isset($asset[$assetKey]) && \is_array($asset[$assetKey])) {
            $newDependencies = [];

            foreach ($asset[$assetKey] as $dependency => $version) {
                [$dependency, $version] = $this->convertDependency($dependency, $version, $vcsRepos, $composer);
                $version = $this->assetType->getVersionConverter()->convertRange($version);
                if (!str_starts_with($version, $dependency)) {
                    $newDependencies[$this->assetType->getComposerVendorName() . '/' . $dependency] = $version;
                }
            }

            $composer[$composerKey] = $newDependencies;
        }
    }

    /**
     * Convert the .
     *
     * @param string $dependency The dependency
     * @param string $version The version
     * @param array $vcsRepos The list of new vcs configs
     * @param array $composer The partial composer data
     *
     * @return string[] The new dependency and the new version
     */
    protected function convertDependency(string $dependency, string $version, array &$vcsRepos, array $composer): array
    {
        [$dependency, $version] = PackageUtil::checkUrlVersion($this->assetType, $dependency, $version, $vcsRepos, $composer);
        [$dependency, $version] = PackageUtil::checkAliasVersion($this->assetType, $dependency, $version);
        [$dependency, $version] = PackageUtil::convertDependencyVersion($this->assetType, $dependency, $version);

        return [$dependency, $version];
    }

    /**
     * Get map keys.
     *
     * @return array
     */
    protected function getMapKeys(): array
    {
        return [];
    }

    /**
     * Get the map conversion of dependencies.
     *
     * @return array
     */
    #[ArrayShape(['dependencies' => "string"])]
    protected function getMapDependencies(): array
    {
        return [
            'dependencies' => 'require'
        ];
    }

    /**
     * Get map extras.
     *
     * @return array
     */
    protected function getMapExtras(): array
    {
        return [];
    }
}
