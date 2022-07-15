<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;
use Fxp\Composer\AssetPlugin\Util\AssetPlugin;

/**
 * Installer for asset packages.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class AssetInstaller extends LibraryInstaller
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * Constructor.
     *
     * @param Config $config
     * @param IOInterface $io
     * @param Composer $composer
     * @param AssetTypeInterface $assetType
     * @param Filesystem|null $filesystem
     */
    public function __construct(Config $config, IOInterface $io, Composer $composer, AssetTypeInterface $assetType, Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $assetType->getComposerType(), $filesystem);

        $this->config = $config;
        $paths = $this->config->getArray('installer-paths');

        if (!empty($paths[$this->type])) {
            $this->vendorDir = rtrim($paths[$this->type], '/');
        } else {
            $this->vendorDir = rtrim($this->vendorDir . '/' . $assetType->getComposerVendorName(), '/');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $packageType): bool
    {
        return $packageType === $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package): string
    {
        $this->initializeVendorDir();

        $targetDir = $package->getTargetDir();

        [, $name] = explode('/', $package->getPrettyName(), 2);

        return ($this->vendorDir ? $this->vendorDir . '/' : '') . $name . ($targetDir ? '/' . $targetDir : '');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPackageBasePath(PackageInterface $package): string
    {
        return $this->getInstallPath($package);
    }

    /**
     * {@inheritDoc}
     */
    protected function installCode(PackageInterface $package): void
    {
        $package = AssetPlugin::addMainFiles($this->config, $package);

        parent::installCode($package);

        $this->deleteIgnoredFiles($package);
    }

    /**
     * {@inheritDoc}
     */
    protected function updateCode(PackageInterface $initial, PackageInterface $target): void
    {
        $target = AssetPlugin::addMainFiles($this->config, $target);

        parent::updateCode($initial, $target);

        $this->deleteIgnoredFiles($target);
    }

    /**
     * Deletes files defined in bower.json in section "ignore".
     *
     * @param PackageInterface $package
     *
     * @return void
     */
    protected function deleteIgnoredFiles(PackageInterface $package): void
    {
        $manager = IgnoreFactory::create($this->config, $this->composer, $package, $this->getInstallPath($package));

        if ($manager->isEnabled() && !$manager->hasPattern()) {
            $this->addIgnorePatterns($manager, $package);
        }

        $manager->cleanup();
    }

    /**
     * Add ignore patterns in the manager.
     *
     * @param IgnoreManager $manager The ignore manager instance
     * @param PackageInterface $package The package instance
     *
     * @return void
     */
    protected function addIgnorePatterns(IgnoreManager $manager, PackageInterface $package): void
    {
        // override this method
    }
}
