<?php
/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) Simon Karlen <simi.albi@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Type;

use Fxp\Composer\AssetPlugin\Converter\ArtifactoryBowerPackageConverter;
use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;

/**
 * Artifactory Bower asset type.
 *
 * @author Simon Karlen <simi.albi@outlook.com>
 */
class ArtifactoryBowerAssetType extends BowerAssetType implements AssetTypeInterface
{
    /**
     * {@inheritDoc}
     */
    protected function createPackageConverter(): PackageConverterInterface
    {
        return new ArtifactoryBowerPackageConverter($this);
    }
}
