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

use Composer\Package\CompletePackage;
use Fxp\Composer\AssetPlugin\Package\Loader\LazyLoaderInterface;

/**
 * Abstract class for the lazy loading complete package.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
abstract class AbstractLazyCompletePackage extends CompletePackage implements LazyPackageInterface
{
    /**
     * @var LazyLoaderInterface|null
     */
    protected ?LazyLoaderInterface $lazyLoader;

    /**
     * {@inheritDoc}
     */
    public function getAutoload(): array
    {
        $this->initialize();

        return parent::getAutoload();
    }

    /**
     * {@inheritDoc}
     */
    public function getDevAutoload(): array
    {
        $this->initialize();

        return parent::getDevAutoload();
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludePaths(): array
    {
        $this->initialize();

        return parent::getIncludePaths();
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationUrl(): ?string
    {
        $this->initialize();

        return parent::getNotificationUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function getArchiveExcludes(): array
    {
        $this->initialize();

        return parent::getArchiveExcludes();
    }

    /**
     * {@inheritDoc}
     */
    public function getScripts(): array
    {
        $this->initialize();

        return parent::getScripts();
    }

    /**
     * {@inheritDoc}
     */
    public function getRepositories(): array
    {
        $this->initialize();

        return parent::getRepositories();
    }

    /**
     * {@inheritDoc}
     */
    public function getLicense(): array
    {
        $this->initialize();

        return parent::getLicense();
    }

    /**
     * {@inheritDoc}
     */
    public function getKeywords(): array
    {
        $this->initialize();

        return parent::getKeywords();
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthors(): array
    {
        $this->initialize();

        return parent::getAuthors();
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): ?string
    {
        $this->initialize();

        return parent::getDescription();
    }

    /**
     * {@inheritDoc}
     */
    public function getHomepage(): ?string
    {
        $this->initialize();

        return parent::getHomepage();
    }

    /**
     * {@inheritDoc}
     */
    public function getSupport(): array
    {
        $this->initialize();

        return parent::getSupport();
    }

    /**
     * {@inheritDoc}
     */
    public function setLoader(LazyLoaderInterface $lazyLoader): void
    {
        $this->lazyLoader = $lazyLoader;
    }

    /**
     * Initialize the package.
     *
     * @return void
     */
    protected function initialize(): void
    {
        if (!$this->lazyLoader) {
            return;
        }

        $real = $this->lazyLoader->load($this);
        $this->lazyLoader = null;

        if (false === $real) {
            $this->version = '-9999999.9999999.9999999.9999999';

            return;
        }

        $this->type = $real->getType();
        $this->transportOptions = $real->getTransportOptions();
        $this->targetDir = $real->getTargetDir();
        $this->extra = $real->getExtra();
        $this->binaries = $real->getBinaries();
        $this->installationSource = $real->getInstallationSource();
        $this->sourceType = $real->getSourceType();
        $this->sourceUrl = $real->getSourceUrl();
        $this->sourceReference = $real->getSourceReference();
        $this->sourceMirrors = $real->getSourceMirrors();
        $this->distType = $real->getDistType();
        $this->distUrl = $real->getDistUrl();
        $this->distReference = $real->getDistReference();
        $this->distSha1Checksum = $real->getDistSha1Checksum();
        $this->distMirrors = $real->getDistMirrors();
        $this->releaseDate = $real->getReleaseDate();
        $this->requires = $real->getRequires();
        $this->conflicts = $real->getConflicts();
        $this->provides = $real->getProvides();
        $this->replaces = $real->getReplaces();
        $this->devRequires = $real->getDevRequires();
        $this->suggests = $real->getSuggests();
        $this->autoload = $real->getAutoload();
        $this->devAutoload = $real->getDevAutoload();
        $this->includePaths = $real->getIncludePaths();
        $this->notificationUrl = $real->getNotificationUrl();
        $this->archiveExcludes = $real->getArchiveExcludes();
        $this->scripts = $real->getScripts();
        $this->repositories = $real->getRepositories();
        $this->license = $real->getLicense();
        $this->keywords = $real->getKeywords();
        $this->authors = $real->getAuthors();
        $this->description = $real->getDescription();
        $this->homepage = $real->getHomepage();
        $this->support = $real->getSupport();
    }
}
