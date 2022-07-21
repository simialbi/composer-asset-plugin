<?php
/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) Simon Karlen <simi.albi@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Converter;

use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Converter for artifactory bower package to composer package.
 *
 * @author Simon Karlen <simi.albi@outlook.com>
 */
class ArtifactoryBowerPackageConverter extends BowerPackageConverter implements PackageConverterInterface
{
    /**
     * {@inheritDoc}
     */
    #[ArrayShape([
        'name' => 'array',
        'type' => 'array',
        'version' => 'array'
    ])]
    protected function getMapKeys(): array
    {
        return [
            'name' => ['name', function (string $value) {
                return $this->assetType->formatComposerName($value);
            }],
            'type' => ['type', function () {
                return $this->assetType->getComposerType();
            }],
            'version' => [['version', 'version_normalized', 'dist'], function (array $value) {
                return [
                    'version' => $this->assetType->getVersionConverter()->convertVersion($value[0]),
                    'version_normalized' => (new VersionParser())->normalize($value[0]),
                    'dist' => [
                        'type' => 'tar',
                        'url' => $value[2],
                        'reference' => $value[1],
                        'shasum' => ''
                    ]
                ];
            }]
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getMapExtras(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function getMapDependencies(): array
    {
        return [];
    }
}
