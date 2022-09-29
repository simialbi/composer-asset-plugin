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

use JetBrains\PhpStorm\ArrayShape;

/**
 * Converter for NPM package to composer package.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class NpmPackageConverter extends AbstractPackageConverter
{
    /**
     * {@inheritDoc}
     */
    #[ArrayShape([
        'name' => 'array',
        'type' => 'array',
        'version' => 'array',
        'version_normalized' => 'string',
        'description' => 'string',
        'keywords' => 'string',
        'homepage' => 'string',
        'license' => 'array',
        'time' => 'string',
        'author' => 'array',
        'contributors' => 'array',
        'bin' => 'array',
        'dist' => 'array'
    ])]
    protected function getMapKeys(): array
    {
        $assetType = $this->assetType;

        return [
            'name' => ['name', function ($value) use ($assetType) {
                return $assetType->formatComposerName(NpmPackageUtil::convertName($value));
            }],
            'type' => ['type', function () use ($assetType) {
                return $assetType->getComposerType();
            }],
            'version' => ['version', function ($value) use ($assetType) {
                return $assetType->getVersionConverter()->convertVersion($value);
            }],
            'version_normalized' => 'version_normalized',
            'description' => 'description',
            'keywords' => 'keywords',
            'homepage' => 'homepage',
            'license' => ['license', function ($value) {
                return NpmPackageUtil::convertLicenses($value);
            }],
            'time' => 'time',
            'author' => ['authors', function ($value) {
                return NpmPackageUtil::convertAuthor($value);
            }],
            'contributors' => ['authors', function ($value, $prevValue) {
                return NpmPackageUtil::convertContributors($value, $prevValue);
            }],
            'bin' => ['bin', function ($value) {
                return (array)$value;
            }],
            'dist' => ['dist', function ($value) {
                return NpmPackageUtil::convertDist($value);
            }]
        ];
    }

    /**
     * {@inheritDoc}
     */
    #[ArrayShape([
        'bugs' => 'string',
        'files' => 'string',
        'main' => 'string',
        'man' => 'string',
        'directories' => 'string',
        'repository' => 'string',
        'scripts' => 'string',
        'config' => 'string',
        'bundledDependencies' => 'string',
        'optionalDependencies' => 'string',
        'engines' => 'string',
        'engineStrict' => 'string',
        'os' => 'string',
        'cpu' => 'string',
        'preferGlobal' => 'string',
        'private' => 'string',
        'publishConfig' => 'string'
    ])]
    protected function getMapExtras(): array
    {
        return [
            'bugs' => 'npm-asset-bugs',
            'files' => 'npm-asset-files',
            'main' => 'npm-asset-main',
            'man' => 'npm-asset-man',
            'directories' => 'npm-asset-directories',
            'repository' => 'npm-asset-repository',
            'scripts' => 'npm-asset-scripts',
            'config' => 'npm-asset-config',
            'bundledDependencies' => 'npm-asset-bundled-dependencies',
            'optionalDependencies' => 'npm-asset-optional-dependencies',
            'engines' => 'npm-asset-engines',
            'engineStrict' => 'npm-asset-engine-strict',
            'os' => 'npm-asset-os',
            'cpu' => 'npm-asset-cpu',
            'preferGlobal' => 'npm-asset-prefer-global',
            'private' => 'npm-asset-private',
            'publishConfig' => 'npm-asset-publish-config'
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function convertDependencies(array $asset, string $assetKey, array &$composer, string $composerKey, array &$vcsRepos = []): void
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function convertDependency(string $dependency, string $version, array &$vcsRepos, array $composer): array
    {
        $dependency = NpmPackageUtil::convertName($dependency);

        return parent::convertDependency($dependency, $version, $vcsRepos, $composer);
    }
}
