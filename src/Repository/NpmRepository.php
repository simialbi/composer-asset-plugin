<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Semver\VersionParser;

class NpmRepository extends AbstractAssetRepository
{
    protected string $url = 'https://registry.npmjs.org';
    protected string|null $lazyLoadUrl = 'https://registry.npmjs.org/%package%';
    protected string|null $searchUrl = 'https://www.npmjs.com/search/suggestions?q=%query%';


    /**
     * {@inheritDoc}
     */
    public function getRepoName(): string
    {
        return 'npmjs.org (' . $this->getUrl() . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getRepoType(): string
    {
        return 'npm';
    }

    /**
     * {@inheritDoc}
     */
    protected function convertResultItem(array $item): array
    {
        return [
            'name' => 'npm-asset/' . $item['name'],
            'description' => $item['description'] ?? null,
            'abandoned' => false
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function convertPackage(array $item): array
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
