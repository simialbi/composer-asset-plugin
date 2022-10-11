<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Pcre\Preg;
use JetBrains\PhpStorm\ArrayShape;

class BowerRepository extends AbstractAssetRepository
{
    protected ?array $rootData = null;
    protected string $url = 'https://registry.bower.io/packages';
    protected string|null $lazyLoadUrl = 'https://registry.bower.io/packages/%package%';

    /**
     * {@inheritDoc}
     *
     * @throws \ErrorException|\Seld\JsonLint\ParsingException
     */
    #[ArrayShape([
        'namesFound' => 'array',
        'packages' => 'array'
    ])] public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []): array
    {
        $this->loadRootServerFile(600);

        $packages = [
            'namesFound' => [],
            'packages' => []
        ];
        foreach ($packageNameMap as $name => $constraint) {
            if (!Preg::match('#^bower-asset/#', $name)) {
                continue;
            }
            if (isset($this->packageMap[$name])) {
                $cleanName = str_replace('bower-asset/', '', $name);
                /** @var VcsRepository $repo */
                $repo = $this->composer->getRepositoryManager()->createRepository('bower+github', [
                    'asset-package-name' => $name,
                    'url' => $this->packageMap[$name]
                ], $cleanName);
                foreach ($this->composer->getRepositoryManager()->getRepositories() as $repository) {
                    if ($repo->getRepoName() === $repository->getRepoName()) {
                        foreach ($repository->getPackages() as $package) {
                            if ($this->isVersionAcceptable($constraint, $package->getName(), [
                                'version' => $package->getVersion(),
                                'version_normalized' => $package->getPrettyVersion()
                            ], $acceptableStabilities, $stabilityFlags)) {
//                                $this->io->write('Added already added package <info>' . $package->getName() . '</info> (<warning>' . $package->getPrettyVersion() . '</warning>)');
                                $packages['namesFound'][$name] = true;
                                $packages['packages'][] = $package;
                            }
                        }
                        if ($this->io->isVerbose()) {
                            $this->io->write('Repository <info>' . $cleanName . '</info> already added. Skipping...');
                            continue 2;
                        }
                    }
                }
                if ($this->io->isVerbose()) {
                    $this->io->write('Adding Vcs repository <info>' . $cleanName . '</info>');
                }
                $this->composer->getRepositoryManager()->addRepository($repo);
                $packages = array_merge_recursive($repo->loadPackages($packageNameMap, $acceptableStabilities, $stabilityFlags, $alreadyLoaded));
            }
        }

//        foreach ($packages['packages'] as $p) {
//            /** @var \Composer\Package\BasePackage $p */
//            var_dump($p->getId(), $p->getName(), $p->getPrettyVersion());
//        }
//        exit();

        return $packages;
    }

    /**
     * {@inheritDoc}
     */
    public function getRepoName(): string
    {
        return 'bower.io repo (' . $this->getUrl() . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getRepoType(): string
    {
        return 'bower';
    }

    /**
     * Load packages file
     *
     * @param int|null $rootMaxAge
     * @return array
     *
     * @throws \ErrorException|\Seld\JsonLint\ParsingException
     */
    protected function loadRootServerFile(?int $rootMaxAge = null): array
    {
        if (null !== $this->rootData) {
            return $this->rootData;
        }

        if (!extension_loaded('openssl') && str_starts_with($this->getUrl(), 'https')) {
            throw new \RuntimeException('You must enable the openssl extension in your php.ini to load information from ' . $this->getUrl());
        }

        if ($cachedData = $this->cache->read('bower-packages.json')) {
            $cachedData = json_decode($cachedData, true);
            if ($rootMaxAge !== null && ($age = $this->cache->getAge('bower-packages.json')) !== false && $age <= $rootMaxAge) {
                $data = $cachedData;
            } elseif (isset($cachedData['last-modified'])) {
                $response = $this->fetchFileIfLastModified($this->getUrl(), 'bower-packages.json', $cachedData['last-modified']);
                $data = true === $response ? $cachedData : $response;
            }
        }

        if (!isset($data)) {
            $data = $this->fetchFile($this->getUrl(), 'bower-packages.json', true);
        }

        unset($data['last-modified']);

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $this->packageMap['bower-asset/' . $item['name']] = $item['url'];
        }

        return $this->rootData = $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function convertResultItem(array $item): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function convertPackage(array $item): array
    {
        return [];
    }
}
