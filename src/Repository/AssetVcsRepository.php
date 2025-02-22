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

use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Repository\InvalidRepositoryException;
use Composer\Repository\Vcs\VcsDriverInterface;
use Fxp\Composer\AssetPlugin\Package\LazyCompletePackage;
use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;
use Fxp\Composer\AssetPlugin\Util\Validator;

/**
 * Asset VCS repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class AssetVcsRepository extends AbstractAssetVcsRepository
{
    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    protected function initialize(): void
    {
        $this->packages = [];
        $this->packageName = isset($this->repoConfig['name']) ? Util::cleanPackageName($this->repoConfig['name']) : null;
        $this->initLoader();
        $this->initRegistryVersions();
        $this->initFullDriver();

        if (!$this->getPackages()) {
            throw new InvalidRepositoryException('No valid ' . $this->assetType->getFilename() . ' was found in any branch or tag of ' . $this->url . ', could not load a package from it.');
        }
    }

    /**
     * Init the driver with branches and tags.
     *
     * @return void
     */
    protected function initFullDriver(): void
    {
        try {
            $driver = $this->initDriver();
            $this->initRootIdentifier($driver);
            $this->initTags($driver);
            $this->initBranches($driver);
            $driver->cleanup();
        } catch (\Exception) {
        }
    }

    /**
     * Initializes all tags.
     *
     * @param VcsDriverInterface $driver
     *
     * @return void
     */
    protected function initTags(VcsDriverInterface $driver): void
    {
        foreach ($driver->getTags() as $tag => $identifier) {
            $packageName = $this->createPackageName();
            // strip the release- prefix from tags if present
            $tag = str_replace(['release-', 'version/'], '', $tag);

            $this->initTag($driver, $packageName, $tag, $identifier);
        }

        if (!$this->io->isVerbose()) {
            $this->io->overwrite('', false);
        }
    }

    /**
     * Initializes the tag: check if tag must be skipped and validate the tag.
     *
     * @param VcsDriverInterface $driver
     * @param string $packageName
     * @param string $tag
     * @param string $identifier
     *
     * @return void
     */
    protected function initTag(VcsDriverInterface $driver, string $packageName, string $tag, string $identifier): void
    {
        if (null !== $this->filter && $this->filter->skip($this->assetType, $packageName, $tag)) {
            return;
        }

        if (!$parsedTag = Validator::validateTag($tag, $this->assetType, $this->versionParser)) {
            if ($this->io->isVerbose()) {
                $this->io->write('<warning>Skipped tag ' . $tag . ', invalid tag name</warning>');
            }

            return;
        }

        $this->initTagAddPackage($driver, $packageName, $tag, $identifier, $parsedTag);
    }

    /**
     * Initializes the tag: convert data and create package.
     *
     * @param VcsDriverInterface $driver
     * @param string $packageName
     * @param string $tag
     * @param string $identifier
     * @param string $parsedTag
     *
     * @return void
     */
    protected function initTagAddPackage(VcsDriverInterface $driver, string $packageName, string $tag, string $identifier, string $parsedTag): void
    {
        $packageClass = 'Fxp\Composer\AssetPlugin\Package\LazyCompletePackage';
        $data = $this->createMockOfPackageConfig($packageName, $tag);
        $data['version'] = $this->assetType->getVersionConverter()->convertVersion($tag);
        $data['version_normalized'] = $parsedTag;

        // make sure tag packages have no -dev flag
        $data['version'] = preg_replace('{[.-]?dev$}i', '', $data['version']);
        $data['version_normalized'] = preg_replace('{(^dev-|[.-]?dev$)}i', '', $data['version_normalized']);

        $packageData = $this->preProcessAsset($data);
        $package = $this->loader->load($packageData, $packageClass);
        $lazyLoader = $this->createLazyLoader('tag', $identifier, $packageData, $driver);
        /* @var LazyCompletePackage $package */
        $package->setLoader($lazyLoader);

        if (!$this->hasPackage($package)) {
            $this->addPackage($package);
        }
    }

    /**
     * Initializes all branches.
     *
     * @param VcsDriverInterface $driver
     *
     * @return void
     */
    protected function initBranches(VcsDriverInterface $driver): void
    {
        foreach ($driver->getBranches() as $branch => $identifier) {
            if (\is_array($this->rootData) && $branch === $driver->getRootIdentifier()) {
                $this->preInitBranchPackage($driver, $this->rootData, $branch, $identifier);

                continue;
            }

            $this->preInitBranchLazyPackage($driver, $branch, $identifier);
        }

        if (!$this->io->isVerbose()) {
            $this->io->overwrite('', false);
        }
    }

    /**
     * Pre inits the branch of complete package.
     *
     * @param VcsDriverInterface $driver The vcs driver
     * @param array $data The asset package data
     * @param string $branch The branch name
     * @param string $identifier The branch identifier
     *
     * @return void
     */
    protected function preInitBranchPackage(VcsDriverInterface $driver, array $data, string $branch, string $identifier): void
    {
        $packageName = $this->createPackageName();
        $data = array_merge($this->createMockOfPackageConfig($packageName, $branch), $data);
        $data = $this->preProcessAsset($data);
        $data['version'] = $branch;
        $data = $this->configureBranchPackage($branch, $data);

        if (!isset($data['dist'])) {
            $data['dist'] = $driver->getDist($identifier);
        }
        if (!isset($data['source'])) {
            $data['source'] = $driver->getSource($identifier);
        }

        $loader = new ArrayLoader();
        $package = $loader->load($data);
        $package = $this->includeBranchAlias($driver, $package, $branch);
        $this->addPackage($package);
    }

    /**
     * Pre inits the branch of lazy package.
     *
     * @param VcsDriverInterface $driver The vcs driver
     * @param string $branch The branch name
     * @param string $identifier The branch identifier
     *
     * @return void
     */
    protected function preInitBranchLazyPackage(VcsDriverInterface $driver, string $branch, string $identifier): void
    {
        $packageName = $this->createPackageName();
        $data = $this->createMockOfPackageConfig($packageName, $branch);
        $data = $this->configureBranchPackage($branch, $data);

        $this->initBranchLazyPackage($driver, $data, $branch, $identifier);
    }

    /**
     * Configures the package of branch.
     *
     * @param string $branch The branch name
     * @param array $data The data
     *
     * @return array
     */
    protected function configureBranchPackage(string $branch, array $data): array
    {
        $parsedBranch = $this->versionParser->normalizeBranch($branch);
        $data['version_normalized'] = $parsedBranch;

        // make sure branch packages have a dev flag
        if ('dev-' === substr((string)$parsedBranch, 0, 4) || '9999999-dev' === $parsedBranch) {
            $data['version'] = 'dev-' . $data['version'];
        } else {
            $data['version'] = preg_replace('{(\.9{7})+}', '.x', (string)$parsedBranch);
        }

        return $data;
    }

    /**
     * Inits the branch of lazy package.
     *
     * @param VcsDriverInterface $driver The vcs driver
     * @param array $data The package data
     * @param string $branch The branch name
     * @param string $identifier The branch identifier
     */
    protected function initBranchLazyPackage(VcsDriverInterface $driver, array $data, string $branch, string $identifier)
    {
        $packageClass = 'Fxp\Composer\AssetPlugin\Package\LazyCompletePackage';
        $packageData = $this->preProcessAsset($data);
        /** @var LazyCompletePackage $package */
        $package = $this->loader->load($packageData, $packageClass);
        $lazyLoader = $this->createLazyLoader('branch', $identifier, $packageData, $driver);
        $package->setLoader($lazyLoader);
        $package = $this->includeBranchAlias($driver, $package, $branch);

        $this->addPackage($package);
    }

    /**
     * Include the package in the alias package if the branch is a root branch
     * identifier and having a package version.
     *
     * @param VcsDriverInterface $driver The vcs driver
     * @param PackageInterface $package The package instance
     * @param string $branch The branch name
     *
     * @return PackageInterface
     */
    protected function includeBranchAlias(VcsDriverInterface $driver, PackageInterface $package, string $branch): PackageInterface
    {
        if (null !== $this->rootPackageVersion && $branch === $driver->getRootIdentifier()) {
            $aliasNormalized = $this->normalizeBranchAlias($package);
            $package = $package instanceof AliasPackage ? $package->getAliasOf() : $package;
            $package = $this->overrideBranchAliasConfig($package, $aliasNormalized, $branch);
            $package = $this->addPackageAliases($package, $aliasNormalized);
        }

        return $package;
    }

    /**
     * Normalize the alias of branch.
     *
     * @param PackageInterface $package The package instance
     *
     * @return string The alias branch name
     */
    protected function normalizeBranchAlias(PackageInterface $package): string
    {
        $stability = VersionParser::parseStability($this->versionParser->normalize($this->rootPackageVersion));
        $aliasNormalized = 'dev-' . $this->rootPackageVersion;

        if (BasePackage::STABILITY_STABLE === BasePackage::$stabilities[$stability]
            && null === $this->findPackage($package->getName(), $this->rootPackageVersion)) {
            $aliasNormalized = $this->versionParser->normalize($this->rootPackageVersion);
        }

        return $aliasNormalized;
    }

    /**
     * Init the package versions added directly in the Asset Registry.
     *
     * @return void
     */
    protected function initRegistryVersions(): void
    {
        if (isset($this->repoConfig['registry-versions'])) {
            /** @var CompletePackageInterface $package */
            foreach ($this->repoConfig['registry-versions'] as $package) {
                $this->addPackage($package);
            }
        }
    }
}
