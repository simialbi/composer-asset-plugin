<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Package;

/**
 * The lazy loading complete package.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class LazyCompletePackage extends AbstractLazyCompletePackage implements LazyPackageInterface
{
    /**
     * {@inheritDoc}
     */
    public function getTransportOptions(): array
    {
        $this->initialize();

        return parent::getTransportOptions();
    }

    /**
     * {@inheritDoc}
     */
    public function getTargetDir(): ?string
    {
        $this->initialize();

        return parent::getTargetDir();
    }

    /**
     * {@inheritDoc}
     */
    public function getExtra(): array
    {
        $this->initialize();

        return parent::getExtra();
    }

    /**
     * {@inheritDoc}
     */
    public function getBinaries(): array
    {
        $this->initialize();

        return parent::getBinaries();
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallationSource(): ?string
    {
        $this->initialize();

        return parent::getInstallationSource();
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceType(): ?string
    {
        $this->initialize();

        return parent::getSourceType();
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceUrl(): ?string
    {
        $this->initialize();

        return parent::getSourceUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceReference(): ?string
    {
        $this->initialize();

        return parent::getSourceReference();
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceMirrors(): ?array
    {
        $this->initialize();

        return parent::getSourceMirrors();
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceUrls(): array
    {
        $this->initialize();

        return parent::getSourceUrls();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistType(): ?string
    {
        $this->initialize();

        return parent::getDistType();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistUrl(): ?string
    {
        $this->initialize();

        return parent::getDistUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistReference(): ?string
    {
        $this->initialize();

        return parent::getDistReference();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistSha1Checksum(): ?string
    {
        $this->initialize();

        return parent::getDistSha1Checksum();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistMirrors(): ?array
    {
        $this->initialize();

        return parent::getDistMirrors();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistUrls(): array
    {
        $this->initialize();

        return parent::getDistUrls();
    }

    /**
     * {@inheritDoc}
     */
    public function getReleaseDate(): ?\DateTimeInterface
    {
        $this->initialize();

        return parent::getReleaseDate();
    }

    /**
     * {@inheritDoc}
     */
    public function getRequires(): array
    {
        $this->initialize();

        return parent::getRequires();
    }

    /**
     * {@inheritDoc}
     */
    public function getConflicts(): array
    {
        $this->initialize();

        return parent::getConflicts();
    }

    /**
     * {@inheritDoc}
     */
    public function getDevRequires(): array
    {
        $this->initialize();

        return parent::getDevRequires();
    }

    /**
     * {@inheritDoc}
     */
    public function getSuggests(): array
    {
        $this->initialize();

        return parent::getSuggests();
    }
}
