<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Package\Loader;

use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Loader\LoaderInterface;
use Composer\Repository\Vcs\VcsDriverInterface;
use Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException;
use Fxp\Composer\AssetPlugin\Package\LazyPackageInterface;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Lazy loader for asset package.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class LazyAssetPackageLoader implements LazyLoaderInterface
{
    /**
     * @var string
     */
    protected string $type;

    /**
     * @var string
     */
    protected string $identifier;

    /**
     * @var array
     */
    protected array $packageData;

    /**
     * @var AssetTypeInterface
     */
    protected AssetTypeInterface $assetType;

    /**
     * @var LoaderInterface
     */
    protected LoaderInterface $loader;

    /**
     * @var VcsDriverInterface
     */
    protected VcsDriverInterface $driver;

    /**
     * @var IOInterface
     */
    protected IOInterface $io;

    /**
     * @var AssetRepositoryManager
     */
    protected AssetRepositoryManager $assetRepositoryManager;

    /**
     * @var bool
     */
    protected bool $verbose;

    /**
     * @var array
     */
    protected array $cache;

    /**
     * Constructor.
     *
     * @param string $type
     * @param string $identifier
     * @param array $packageData
     */
    public function __construct(string $type, string $identifier, array $packageData)
    {
        $this->identifier = $identifier;
        $this->type = $type;
        $this->packageData = $packageData;
        $this->verbose = false;
        $this->cache = [];
    }

    /**
     * Sets the asset type.
     *
     * @param AssetTypeInterface $assetType
     *
     * @return void
     */
    public function setAssetType(AssetTypeInterface $assetType): void
    {
        $this->assetType = $assetType;
    }

    /**
     * Sets the loader.
     *
     * @param LoaderInterface $loader
     *
     * @return void
     */
    public function setLoader(LoaderInterface $loader): void
    {
        $this->loader = $loader;
    }

    /**
     * Sets the driver.
     *
     * @param VcsDriverInterface $driver
     *
     * @return void
     */
    public function setDriver(VcsDriverInterface $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * Sets the IO.
     *
     * @param IOInterface $io
     *
     * @return void
     */
    public function setIO(IOInterface $io): void
    {
        $this->io = $io;
        $this->verbose = $io->isVerbose();
    }

    /**
     * Sets the asset repository manager.
     *
     * @param AssetRepositoryManager $assetRepositoryManager The asset repository manager
     *
     * @return void
     */
    public function setAssetRepositoryManager(AssetRepositoryManager $assetRepositoryManager): void
    {
        $this->assetRepositoryManager = $assetRepositoryManager;
    }

    /**
     * {@inheritDoc}
     */
    public function load(LazyPackageInterface $package): LazyPackageInterface|false
    {
        if (isset($this->cache[$package->getUniqueName()])) {
            return $this->cache[$package->getUniqueName()];
        }
        $this->validateConfig();

        $filename = $this->assetType->getFilename();
        $msg = 'Reading ' . $filename . ' of <info>' . $package->getName() . '</info> (<comment>' . $package->getPrettyVersion() . '</comment>)';
        if ($this->verbose) {
            $this->io->write($msg);
        } else {
            $this->io->overwrite($msg, false);
        }

        $realPackage = $this->loadRealPackage($package);
        $this->cache[$package->getUniqueName()] = $realPackage;

        if (!$this->verbose) {
            $this->io->overwrite('', false);
        }

        return $realPackage;
    }

    /**
     * Validates the class config.
     *
     * @return void
     * @throws InvalidArgumentException When the property of this class is not defined
     */
    protected function validateConfig(): void
    {
        foreach (['assetType', 'loader', 'driver', 'io'] as $property) {
            if (null === $this->{$property}) {
                throw new InvalidArgumentException(sprintf('The "%s" property must be defined', $property));
            }
        }
    }

    /**
     * Loads the real package.
     *
     * @param LazyPackageInterface $package
     *
     * @return CompletePackageInterface|false
     */
    protected function loadRealPackage(LazyPackageInterface $package): CompletePackageInterface|false
    {
        $realPackage = false;

        try {
            $data = $this->driver->getComposerInformation($this->identifier);
            $valid = \is_array($data);
            $data = $this->preProcess($this->driver, $this->validateData($data), $this->identifier);

            if ($this->verbose) {
                $this->io->write('Importing ' . ($valid ? '' : 'empty ') . $this->type . ' ' . $data['version'] . ' (' . $data['version_normalized'] . ')');
            }

            /** @var CompletePackageInterface $realPackage */
            $realPackage = $this->loader->load($data);
        } catch (\Exception $e) {
            if ($this->verbose) {
                $filename = $this->assetType->getFilename();
                $this->io->write('<' . $this->getIoTag() . '>Skipped ' . $this->type . ' ' . $package->getPrettyVersion() . ', ' . ($e instanceof TransportException ? 'no ' . $filename . ' file was found' : $e->getMessage()) . '</' . $this->getIoTag() . '>');
            }
        }
        $this->driver->cleanup();

        return $realPackage;
    }

    /**
     * @param bool|array $data
     *
     * @return array
     */
    protected function validateData(bool|array $data): array
    {
        return \is_array($data) ? $data : [];
    }

    /**
     * Gets the tag name for IO.
     *
     * @return string
     */
    protected function getIoTag(): string
    {
        return 'branch' === $this->type ? 'error' : 'warning';
    }

    /**
     * Pre process the data of package before the conversion to Package instance.
     *
     * @param VcsDriverInterface $driver
     * @param array $data
     * @param string $identifier
     *
     * @return array
     */
    protected function preProcess(VcsDriverInterface $driver, array $data, string $identifier): array
    {
        $vcsRepos = [];
        $data = array_merge($data, $this->packageData);
        $data = $this->assetType->getPackageConverter()->convert($data, $vcsRepos);

        $this->addRepositories($vcsRepos);

        if (!isset($data['dist'])) {
            $data['dist'] = $driver->getDist($identifier);
        }
        if (!isset($data['source'])) {
            $data['source'] = $driver->getSource($identifier);
        }

        return $this->assetRepositoryManager->solveResolutions($data);
    }

    /**
     * Dispatches the vcs repositories event.
     *
     * @param array $vcsRepositories
     *
     * @return void
     */
    protected function addRepositories(array $vcsRepositories): void
    {
        $this->assetRepositoryManager->addRepositories($vcsRepositories);
    }
}
