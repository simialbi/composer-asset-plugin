<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Pcre\Preg;
use Composer\Semver\VersionParser;
use JetBrains\PhpStorm\ArrayShape;

class NpmRepository extends AbstractAssetRepository
{
    /**
     * @inheritDoc
     */
    #[ArrayShape([
        'namesFound' => 'array',
        'packages' => 'array'
    ])] public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []): array
    {
        $namesFound = [];
        $packages = [];
        foreach ($packageNameMap as $name => $constraint) {
            if (!Preg::match('#^npm-asset/#', $name)) {
                continue;
            }
            try {
                if (empty($this->packages)) {
                    $cleanName = str_replace('npm-asset/', '', $name);
                    $url = str_replace('%package%', $cleanName, $this->getLazyLoadUrl());
                    if ($cachedData = $this->cache->read('npm-' . $cleanName . '.json')) {
                        $cachedData = json_decode($cachedData, true);
                        if (($age = $this->cache->getAge('npm-' . $cleanName . '.json')) && $age <= 900) {
                            $data = $cachedData;
                        } elseif (isset($cachedData['last-modified'])) {
                            $response = $this->fetchFileIfLastModified($url, 'npm-' . $cleanName . '.json', $cachedData['last-modified']);
                            $data = true === $response ? $cachedData : $response;
                        }
                    }

                    if (!isset($data)) {
                        $data = $this->fetchFile($url, 'npm-' . $cleanName . '.json', true);
                    }
                    unset($data['last-modified']);

                    $namesFound[$name] = true;
                    foreach ($this->convertNpmPackage($data) as $item) {
                        $this->addPackage($this->loader->load($item));
                    }
                }
                foreach ($this->packages as $package) {
                    /** @var \Composer\Package\CompletePackage $package */
                    if ($this->isVersionAcceptable(
                        $constraint,
                        $name,
                        ['version' => $package->getVersion(), 'version_normalized' => $package->getPrettyVersion()],
                        $acceptableStabilities,
                        $stabilityFlags)) {
                        $packages[] = $package;
                    }
                }
            } catch (\ErrorException|\Seld\JsonLint\ParsingException) {
                return ['namesFound' => [], 'packages' => []];
            }
        }

        return [
            'namesFound' => $namesFound,
            'packages' => $packages
        ];
    }

    /**
     * @inheritDoc
     */
    public function getRepoName(): string
    {
        return 'npmjs.org (' . $this->getUrl() . ')';
    }

    /**
     * @inheritDoc
     */
    public function getRepoType(): string
    {
        return 'npm';
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return 'https://registry.npmjs.org';
    }

    /**
     * @inheritDoc
     */
    public function getLazyLoadUrl(): ?string
    {
        return 'https://registry.npmjs.org/%package%';
    }

    /**
     * {@inheritDoc}
     */
    public function getSearchUrl(): ?string
    {
        return 'https://www.npmjs.com/search/suggestions?q=%query%';
    }

    /**
     * @inheritDoc
     */
    protected function convertResultItem(array $item): array
    {
        return [
            'name' => 'npm-asset/' . $item['name'],
            'description' => $item['description'] ?? null,
            'abandoned' => false
        ];
    }

    final protected function convertNpmPackage(array $item): array
    {
        $results = [];
        $versionParser = new VersionParser();
        foreach ($item['versions'] as $version => $data) {
            $results[] = [
                'name' => 'npm-asset/' . $item['name'],
                'type' => 'npm-asset-library',
                'version' => $version,
                'version_normalized' => $versionParser->normalize($version),
                'description' => $data['description'] ?? $item['description'] ?? null,
                'keywords' => $data['keywords'] ?? $item['keywords'] ?? [],
                'homepage' => $item['homepage'] ?? null,
                'license' => $item['license'] ?? null,
                'time' => $item['time'][$version] ?? null,
                'author' => $version['author'] ?? $item['author'] ?? [],
                'contributors' => $data['contributors'] ?? $item['contributors'] ?? $item['maintainers'] ?? [],
                'bin' => $data['bin'] ?? null,
                'dist' => [
                    'shasum' => $data['dist']['shasum'] ?? '',
                    'type' => isset($data['dist']['tarball']) ? 'tar' : '',
                    'url' => $data['dist']['tarball'] ?? ''
                ],
//                'source' => isset($data['repository'])
//                    ? [
//                        'type' => 'git',
//                        'url' => preg_replace('#^git://#', 'https://', $data['repository']['url'])
//                    ]
//                    : null
            ];
        }

        return $results;
    }
}
