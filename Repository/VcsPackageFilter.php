<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Installer\InstallationManager;
use Composer\Package\Link;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Semver\Constraint\MultiConstraint;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Filters the asset packages imported into VCS repository to optimize
 * performance when getting the informations of packages.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class VcsPackageFilter
{
    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var RootPackageInterface
     */
    protected RootPackageInterface $package;

    /**
     * @var InstallationManager
     */
    protected InstallationManager $installationManager;

    /**
     * @var InstalledFilesystemRepository|null
     */
    protected ?InstalledFilesystemRepository $installedRepository;

    /**
     * @var VersionParser
     */
    protected VersionParser $versionParser;

    /**
     * @var ArrayLoader
     */
    protected ArrayLoader $arrayLoader;

    /**
     * @var bool
     */
    protected bool $enabled;

    /**
     * @var array
     */
    protected array $requires;

    /**
     * Constructor.
     *
     * @param Config $config The plugin config
     * @param RootPackageInterface $package The root package
     * @param InstallationManager $installationManager The installation manager
     * @param null|InstalledFilesystemRepository $installedRepository The installed repository
     */
    public function __construct(
        Config                        $config,
        RootPackageInterface          $package,
        InstallationManager           $installationManager,
        InstalledFilesystemRepository $installedRepository = null
    )
    {
        $this->config = $config;
        $this->package = $package;
        $this->installationManager = $installationManager;
        $this->installedRepository = $installedRepository;
        $this->versionParser = new VersionParser();
        $this->arrayLoader = new ArrayLoader();
        $this->enabled = true;

        $this->initialize();
    }

    /**
     * @param bool $enabled
     *
     * @return self
     */
    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Check if the filter is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if the version must be skipped.
     *
     * @param AssetTypeInterface $assetType The asset type
     * @param string $name The composer package name
     * @param string $version The version
     *
     * @return bool
     */
    public function skip(AssetTypeInterface $assetType, string $name, string $version): bool
    {
        try {
            $cVersion = $assetType->getVersionConverter()->convertVersion($version);
            $normalizedVersion = $this->versionParser->normalize($cVersion);
        } catch (\Exception $ex) {
            return true;
        }

        if (false !== $this->skipByPattern() && $this->forceSkipVersion($normalizedVersion)) {
            return true;
        }

        return $this->doSkip($name, $normalizedVersion);
    }

    /**
     * Do check if the version must be skipped.
     *
     * @param string $name The composer package name
     * @param string $normalizedVersion The normalized version
     *
     * @return bool
     */
    protected function doSkip(string $name, string $normalizedVersion): bool
    {
        if (!isset($this->requires[$name]) || str_contains($normalizedVersion, '-p')) {
            return false;
        }

        /** @var Link $require */
        $require = $this->requires[$name];

        return !$this->satisfy($require, $normalizedVersion) && $this->isEnabled();
    }

    /**
     * Check if the require dependency has a satisfactory version and stability.
     *
     * @param Link $require The require link defined in root package
     * @param string $normalizedVersion The normalized version
     *
     * @return bool
     */
    protected function satisfy(Link $require, string $normalizedVersion): bool
    {
        return $this->satisfyVersion($require, $normalizedVersion)
            && $this->satisfyStability($require, $normalizedVersion);
    }

    /**
     * Check if the filter must be skipped the version by pattern or not.
     *
     * @return false|string Return the pattern or FALSE for disable the feature
     */
    protected function skipByPattern(): string|false
    {
        $skip = $this->config->get('pattern-skip-version', false);

        return \is_string($skip)
            ? trim($skip, '/')
            : false;
    }

    /**
     * Check if the require package version must be skipped or not.
     *
     * @param string $normalizedVersion The normalized version
     *
     * @return bool
     */
    protected function forceSkipVersion(string $normalizedVersion): bool
    {
        return (bool)preg_match('/' . $this->skipByPattern() . '/', $normalizedVersion);
    }

    /**
     * Check if the require dependency has a satisfactory version.
     *
     * @param Link $require The require link defined in root package
     * @param string $normalizedVersion The normalized version
     *
     * @return bool
     */
    protected function satisfyVersion(Link $require, string $normalizedVersion): bool
    {
        $constraintSame = $this->versionParser->parseConstraints($normalizedVersion);
        $sameVersion = (bool)$require->getConstraint()->matches($constraintSame);

        $consNormalizedVersion = FilterUtil::getVersionConstraint($normalizedVersion, $this->versionParser);
        $constraint = FilterUtil::getVersionConstraint($consNormalizedVersion->getPrettyString(), $this->versionParser);

        return $require->getConstraint()->matches($constraint) || $sameVersion;
    }

    /**
     * Check if the require dependency has a satisfactory stability.
     *
     * @param Link $require The require link defined in root package
     * @param string $normalizedVersion The normalized version
     *
     * @return bool
     */
    protected function satisfyStability(Link $require, string $normalizedVersion): bool
    {
        $requireStability = $this->getRequireStability($require);
        $stability = $this->versionParser->parseStability($normalizedVersion);

        return Package::$stabilities[$stability] <= Package::$stabilities[$requireStability];
    }

    /**
     * Get the minimum stability for the require dependency defined in root package.
     *
     * @param Link $require The require link defined in root package
     *
     * @return string The minimum stability
     */
    protected function getRequireStability(Link $require): string
    {
        $prettyConstraint = $require->getPrettyConstraint();
        $stability = Package::$stabilities;

        if (preg_match_all('/@(' . implode('|', array_keys($stability)) . ')/', $prettyConstraint, $matches)) {
            return FilterUtil::findInlineStability($matches[1], $this->versionParser);
        }

        return FilterUtil::getMinimumStabilityFlag($this->package, $require);
    }

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->requires = array_merge(
            $this->package->getRequires(),
            $this->package->getDevRequires()
        );

        if (null !== $this->installedRepository
            && FilterUtil::checkConfigOption($this->config, 'optimize-with-installed-packages')) {
            $this->initInstalledPackages();
        }
    }

    /**
     * Initialize the installed package.
     */
    private function initInstalledPackages(): void
    {
        foreach ($this->installedRepository->getPackages() as $package) {
            $operator = $this->getFilterOperator($package);
            $link = current($this->arrayLoader->parseLinks($this->package->getName(), $this->package->getVersion(), 'installed', [$package->getName() => $operator . $package->getPrettyVersion()]));
            $link = $this->includeRootConstraint($package, $link);

            $this->requires[$package->getName()] = $link;
        }
    }

    /**
     * Include the constraint of root dependency version in the constraint
     * of installed package.
     *
     * @param PackageInterface $package The installed package
     * @param Link $link The link contained installed constraint
     *
     * @return Link The link with root and installed version constraint
     */
    private function includeRootConstraint(PackageInterface $package, Link $link): Link
    {
        if (isset($this->requires[$package->getName()])) {
            /** @var Link $rLink */
            $rLink = $this->requires[$package->getName()];
            $useConjunctive = FilterUtil::checkConfigOption($this->config, 'optimize-with-conjunctive');
            $constraint = new MultiConstraint([$rLink->getConstraint(), $link->getConstraint()], $useConjunctive);
            $link = new Link($rLink->getSource(), $rLink->getTarget(), $constraint, 'installed', $constraint->getPrettyString());
        }

        return $link;
    }

    /**
     * Get the filter root constraint operator.
     *
     * @param PackageInterface $package The installed package
     *
     * @return string
     */
    private function getFilterOperator(PackageInterface $package): string
    {
        return $this->installationManager->isPackageInstalled($this->installedRepository, $package)
            ? '>'
            : '>=';
    }
}
