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

use Fxp\Composer\AssetPlugin\Converter\BowerPackageConverter;
use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;

/**
 * Bower asset type.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class BowerAssetType extends AbstractAssetType
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'bower';
    }

    /**
     * {@inheritDoc}
     */
    protected function createPackageConverter(): PackageConverterInterface
    {
        return new BowerPackageConverter($this);
    }
}
