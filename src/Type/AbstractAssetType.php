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
use Fxp\Composer\AssetPlugin\Converter\SemverConverter;
use Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface;

/**
 * Abstract asset type.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractAssetType implements AssetTypeInterface
{
    /**
     * @var PackageConverterInterface
     */
    protected PackageConverterInterface $packageConverter;

    /**
     * @var VersionConverterInterface
     */
    protected VersionConverterInterface $versionConverter;

    /**
     * Constructor.
     *
     * @param PackageConverterInterface|null $packageConverter
     * @param VersionConverterInterface|null $versionConverter
     */
    public function __construct(PackageConverterInterface $packageConverter = null, VersionConverterInterface $versionConverter = null)
    {
        $this->packageConverter = $packageConverter ?? $this->createPackageConverter();
        $this->versionConverter = $versionConverter ?? new SemverConverter();
    }

    /**
     * {@inheritDoc}
     */
    public function getComposerVendorName(): string
    {
        return $this->getName() . '-asset';
    }

    /**
     * {@inheritDoc}
     */
    public function getComposerType(): string
    {
        return $this->getName() . '-asset-library';
    }

    /**
     * {@inheritDoc}
     */
    public function getFilename(): string
    {
        return $this->getName() . '.json';
    }

    /**
     * {@inheritDoc}
     */
    public function getPackageConverter(): PackageConverterInterface
    {
        return $this->packageConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersionConverter(): VersionConverterInterface
    {
        return $this->versionConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function formatComposerName(string $name): string
    {
        $prefix = $this->getComposerVendorName() . '/';

        if (preg_match('/(:\/\/)|@/', $name) || str_starts_with($name, $prefix)) {
            return $name;
        }

        return $prefix . $name;
    }

    /**
     * Create package converter.
     *
     * @return PackageConverterInterface
     */
    abstract protected function createPackageConverter(): PackageConverterInterface;
}
