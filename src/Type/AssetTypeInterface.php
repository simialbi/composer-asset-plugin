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

use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;
use Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface;

/**
 * Asset type interface.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
interface AssetTypeInterface
{
    /**
     * Gets the name of asset package mapping.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Gets the composer vendor name.
     *
     * @return string
     */
    public function getComposerVendorName(): string;

    /**
     * Gets the type of the composer package.
     *
     * @return string
     */
    public function getComposerType(): string;

    /**
     * Gets the filename of asset package.
     *
     * @return string
     */
    public function getFilename(): string;

    /**
     * Gets the version converter.
     *
     * @return VersionConverterInterface
     */
    public function getVersionConverter(): VersionConverterInterface;

    /**
     * Gets the package converter.
     *
     * @return PackageConverterInterface
     */
    public function getPackageConverter(): PackageConverterInterface;

    /**
     * Formats the package name with composer vendor if the name is not an URL.
     *
     * @param string $name The package name
     *
     * @return string
     */
    public function formatComposerName(string $name): string;
}
