<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\InvalidPackageException;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Version\VersionParser;
use Composer\Pcre\Preg;
use Composer\Repository\InvalidRepositoryException;
use Composer\Repository\Vcs\VcsDriverInterface;
use Composer\Repository\VersionCacheInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;

class VcsRepository extends \Composer\Repository\VcsRepository
{
    /**
     * @var string The package name to map
     */
    public string $assetPackageName = '';

    /**
     * @var VersionCacheInterface|null Version cache
     */
    protected ?VersionCacheInterface $versionCache = null;

    /**
     * @param array $repoConfig
     * @param IOInterface $io
     * @param Config $config
     * @param HttpDownloader $httpDownloader
     * @param EventDispatcher|null $dispatcher
     * @param ProcessExecutor|null $process
     * @param array|null $drivers
     * @param VersionCacheInterface|null $versionCache
     */
    public function __construct(array $repoConfig, IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $dispatcher = null, ?ProcessExecutor $process = null, ?array $drivers = null, ?VersionCacheInterface $versionCache = null)
    {
        if (isset($repoConfig['asset-package-name'])) {
            $this->assetPackageName = $repoConfig['asset-package-name'];
        }
        parent::__construct($repoConfig, $io, $config, $httpDownloader, $dispatcher, $process, $drivers, $versionCache);
    }

    /**
     * {@inheritDoc}
     * @throws InvalidRepositoryException|InvalidPackageException
     */
    protected function initialize()
    {
        $this->packages = [];
        $this->versionParser = new VersionParser;
        if (!$this->loader) {
            $this->loader = new ArrayLoader($this->versionParser);
        }

        foreach ($this->getDriver()->getTags() as $tag => $identifier) {
            $tag = str_replace('release-', '', $tag);

            $cachedPackage = $this->getCachedPackageVersion($tag, $identifier, $this->isVerbose, $this->isVeryVerbose);
            if ($cachedPackage) {
                $this->addPackage($cachedPackage);

                continue;
            }
            if ($cachedPackage === false) {
                continue;
            }

            if (!$parsedTag = $this->validateTag($tag)) {
                if ($this->isVeryVerbose) {
                    $this->io->writeError('<warning>Skipped tag ' . $tag . ', invalid tag name</warning>');
                }
                continue;
            }

            try {
//                $data = $this->getDriver()->getComposerInformation($identifier);
//                if (null === $data) {
//                    if ($this->isVeryVerbose) {
//                        $this->io->writeError('<warning>No composer.json file found in tag ' . $tag . ', generating package from git information</warning>');
//                    }
                    $data = [
                        'name' => $this->assetPackageName ?: $this->packageName ?? '',
                        'version' => $tag,
                        'version_normalized' => $parsedTag,
                        'dist' => $this->getDriver()->getDist($tag),
                        'source' => $this->getDriver()->getSource($tag)
                    ];
//                }
                // make sure tag packages have no -dev flag
                $data['version'] = Preg::replace('{[.-]?dev$}i', '', $data['version']);
                $data['version_normalized'] = Preg::replace('{(^dev-|[.-]?dev$)}i', '', $data['version_normalized']);

                // make sure tag do not contain the default-branch marker
                unset($data['default-branch']);

                // broken package, version doesn't match tag
                if ($data['version_normalized'] !== $parsedTag) {
                    if ($this->isVeryVerbose) {
                        if (Preg::isMatch('{(^dev-|[.-]?dev$)}i', $parsedTag)) {
                            $this->io->writeError('<warning>Skipped tag ' . $tag . ', invalid tag name, tags can not use dev prefixes or suffixes</warning>');
                        } else {
                            $this->io->writeError('<warning>Skipped tag ' . $tag . ', tag (' . $parsedTag . ') does not match version (' . $data['version_normalized'] . ') in composer.json</warning>');
                        }
                    }
                    continue;
                }

                if ($this->isVeryVerbose) {
                    $this->io->writeError('Importing tag ' . $tag . ' (' . $data['version_normalized'] . ')');
                }

                $this->addPackage($this->loader->load($data));
            } catch (\Exception) {
//                if ($this->isVeryVerbose) {
//                    $this->io->writeError('<warning>No composer.json file found in tag ' . $tag . ', generating package from git information</warning>');
//                }
                $data = [
                    'name' => $this->assetPackageName ?: $this->packageName ?? '',
                    'version' => $tag,
                    'version_normalized' => $parsedTag,
                    'dist' => $this->getDriver()->getDist($tag),
                    'source' => $this->getDriver()->getSource($tag)
                ];
                // make sure tag packages have no -dev flag
                $data['version'] = Preg::replace('{[.-]?dev$}i', '', $data['version']);
                $data['version_normalized'] = Preg::replace('{(^dev-|[.-]?dev$)}i', '', $data['version_normalized']);

                // make sure tag do not contain the default-branch marker
                unset($data['default-branch']);

                // broken package, version doesn't match tag
                if ($data['version_normalized'] !== $parsedTag) {
                    if ($this->isVeryVerbose) {
                        if (Preg::isMatch('{(^dev-|[.-]?dev$)}i', $parsedTag)) {
                            $this->io->writeError('<warning>Skipped tag ' . $tag . ', invalid tag name, tags can not use dev prefixes or suffixes</warning>');
                        } else {
                            $this->io->writeError('<warning>Skipped tag ' . $tag . ', tag (' . $parsedTag . ') does not match version (' . $data['version_normalized'] . ') in composer.json</warning>');
                        }
                    }
                    continue;
                }

                if ($this->isVeryVerbose) {
                    $this->io->writeError('Importing tag ' . $tag . ' (' . $data['version_normalized'] . ')');
                }

                $this->addPackage($this->loader->load($data));
                continue;
            }
        }

        if (!$this->isVeryVerbose) {
            $this->io->overwriteError('', false);
        }

        foreach ($this->getDriver()->getBranches() as $branch => $identifier) {
            $branch = (string)$branch;
            $msg = 'Reading composer.json of <info>' . ($this->assetPackageName ?: $this->packageName ?: $this->url) . '</info> (<comment>' . $branch . '</comment>)';
            if ($this->isVeryVerbose) {
                $this->io->writeError($msg);
            } elseif ($this->isVerbose) {
                $this->io->overwriteError($msg, false);
            }

            if (!$parsedBranch = $this->validateBranch($branch)) {
                if ($this->isVeryVerbose) {
                    $this->io->writeError('<warning>Skipped branch ' . $branch . ', invalid name</warning>');
                }
                continue;
            }

            // make sure branch packages have a dev flag
            if (str_starts_with($parsedBranch, 'dev-') || VersionParser::DEFAULT_BRANCH_ALIAS === $parsedBranch) {
                $version = 'dev-' . $branch;
            } else {
                $prefix = str_starts_with($branch, 'v') ? 'v' : '';
                $version = $prefix . Preg::replace('{(\.9{7})+}', '.x', $parsedBranch);
            }

            $cachedPackage = $this->getCachedPackageVersion($version, $identifier, $this->isVerbose, $this->isVeryVerbose, $this->getDriver()->getRootIdentifier() === $branch);
            if ($cachedPackage) {
                $this->addPackage($cachedPackage);

                continue;
            }
            if ($cachedPackage === false) {
                continue;
            }

            try {
//                $data = $this->getDriver()->getComposerInformation($identifier);
//                if (null === $data) {
//                    if ($this->isVeryVerbose) {
//                        $this->io->writeError('<warning>No composer.json file found in tag ' . $branch . ', generating package from git information</warning>');
//                    }

                    $data = [
                        'name' => $this->assetPackageName ?: $this->packageName,
                        'version' => $version,
                        'version_normalized' => $parsedBranch,
                        'dist' => $this->getDriver()->getDist($branch),
                        'source' => $this->getDriver()->getSource($branch)
                    ];
//                }

                unset($data['default-branch']);
                if ($this->getDriver()->getRootIdentifier() === $branch) {
                    $data['default-branch'] = true;
                }

                if ($this->isVeryVerbose) {
                    $this->io->writeError('Importing branch ' . $branch . ' (' . $data['version'] . ')');
                }

                $packageData = $this->preProcess($this->getDriver(), $data, $identifier);
                $package = $this->loader->load($packageData);
                if ($this->loader instanceof ValidatingArrayLoader && $this->loader->getWarnings()) {
                    throw new InvalidPackageException($this->loader->getErrors(), $this->loader->getWarnings(), $packageData);
                }
                $this->addPackage($package);
            } catch (TransportException $e) {
//                if ($this->isVeryVerbose) {
//                    $this->io->writeError('<warning>No composer.json file found in tag ' . $branch . ', generating package from git information</warning>');
//                }

                $data = [
                    'name' => $this->assetPackageName ?: $this->packageName,
                    'version' => $version,
                    'version_normalized' => $parsedBranch,
                    'dist' => $this->getDriver()->getDist($branch),
                    'source' => $this->getDriver()->getSource($branch)
                ];

                unset($data['default-branch']);
                if ($this->getDriver()->getRootIdentifier() === $branch) {
                    $data['default-branch'] = true;
                }

                if ($this->isVeryVerbose) {
                    $this->io->writeError('Importing branch ' . $branch . ' (' . $data['version'] . ')');
                }

                $packageData = $this->preProcess($this->getDriver(), $data, $identifier);
                $package = $this->loader->load($packageData);
                if ($this->loader instanceof ValidatingArrayLoader && $this->loader->getWarnings()) {
                    throw new InvalidPackageException($this->loader->getErrors(), $this->loader->getWarnings(), $packageData);
                }
                $this->addPackage($package);
                continue;
            } catch (\Exception $e) {
                if (!$this->isVeryVerbose) {
                    $this->io->writeError('');
                }
                $this->branchErrorOccurred = true;
                $this->io->writeError('<error>Skipped branch ' . $branch . ', ' . $e->getMessage() . '</error>');
                $this->io->writeError('');
                continue;
            }
        }
        $this->getDriver()->cleanup();

        if (!$this->isVeryVerbose) {
            $this->io->overwriteError('', false);
        }

        if (!$this->getPackages()) {
            throw new InvalidRepositoryException('No valid composer.json was found in any branch or tag of ' . $this->url . ', could not load a package from it.');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function preProcess(VcsDriverInterface $driver, array $data, string $identifier): array
    {
        if (isset($this->assetPackageName)) {
            $this->packageName = $this->assetPackageName;
        }

        return parent::preProcess($driver, $data, $identifier);
    }

    /**
     * Validates a branch
     * @return string|false
     */
    protected function validateBranch(string $branch): bool|string
    {
        try {
            $normalizedBranch = $this->versionParser->normalizeBranch($branch);

            // validate that the branch name has no weird characters conflicting with constraints
            $this->versionParser->parseConstraints($normalizedBranch);

            return $normalizedBranch;
        } catch (\Exception $e) {
        }

        return false;
    }


    /**
     * Validate a git tag
     * @return string|false
     */
    protected function validateTag(string $version): string|bool
    {
        try {
            return $this->versionParser->normalize($version);
        } catch (\Exception $e) {
        }

        return false;
    }


    /**
     * Get cached package version if exists.
     *
     * @param string $version The requested version
     * @param string $identifier Either a tag, branch or commit name
     * @param bool $isVerbose Is in verbose mode?
     * @param bool $isVeryVerbose Is in very verbode mode?
     * @param bool $isDefaultBranch Is it the default branch?
     *
     * @return \Composer\Package\CompletePackage|\Composer\Package\CompleteAliasPackage|null|false null if no cache present, false if the absence of a version was cached
     */
    private function getCachedPackageVersion(string $version, string $identifier, bool $isVerbose, bool $isVeryVerbose, bool $isDefaultBranch = false): bool|\Composer\Package\CompletePackage|\Composer\Package\CompleteAliasPackage|null
    {
        if (!$this->versionCache) {
            return null;
        }

        $cachedPackage = $this->versionCache->getVersionPackage($version, $identifier);
        if ($cachedPackage === false) {
            if ($isVeryVerbose) {
                $this->io->writeError('<warning>Skipped ' . $version . ', no composer file (cached from ref ' . $identifier . ')</warning>');
            }

            return false;
        }

        if ($cachedPackage) {
            $msg = 'Found cached composer.json of <info>' . ($this->packageName ?: $this->url) . '</info> (<comment>' . $version . '</comment>)';
            if ($isVeryVerbose) {
                $this->io->writeError($msg);
            } elseif ($isVerbose) {
                $this->io->overwriteError($msg, false);
            }

            unset($cachedPackage['default-branch']);
            if ($isDefaultBranch) {
                $cachedPackage['default-branch'] = true;
            }

            if ($existingPackage = $this->findPackage($cachedPackage['name'], new Constraint('=', $cachedPackage['version_normalized']))) {
                if ($isVeryVerbose) {
                    $this->io->writeError('<warning>Skipped cached version ' . $version . ', it conflicts with an another tag (' . $existingPackage->getPrettyVersion() . ') as both resolve to ' . $cachedPackage['version_normalized'] . ' internally</warning>');
                }
                $cachedPackage = null;
            }
        }

        if ($cachedPackage) {
            return $this->loader->load($cachedPackage);
        }

        return null;
    }
}
