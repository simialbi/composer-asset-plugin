<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Type;

use Fxp\Composer\AssetPlugin\Converter\NpmPackageConverter;
use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;

/**
 * NPM asset type.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class NpmAssetType extends AbstractAssetType
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'npm';
    }

    /**
     * {@inheritDoc}
     */
    public function getFilename(): string
    {
        return 'package.json';
    }

    /**
     * {@inheritDoc}
     */
    protected function createPackageConverter(): PackageConverterInterface
    {
        return new NpmPackageConverter($this);
    }
}
