<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\PartialComposer;
use Composer\Pcre\Preg;
use Composer\Repository\InvalidRepositoryException;
use Composer\Semver\VersionParser;
use Composer\Util\HttpDownloader;
use Composer\Util\Url;

class BowerArtifactoryRepository extends AbstractAssetRepository
{
    /**
     * {@inheritDoc}
     *
     * @throws InvalidRepositoryException
     */
    public function __construct(array $repoConfig, PartialComposer $composer, IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $eventDispatcher)
    {
        if (!isset($repoConfig['url'])) {
            throw new InvalidRepositoryException('You need to set the `url` in the composer.json config.');
        }
        parent::__construct($repoConfig, $composer, $io, $config, $httpDownloader, $eventDispatcher);

        $this->lazyLoadUrl = $this->getUrl() . '/packages/%package%';
    }


    /**
     * {@inheritDoc}
     */
    public function getRepoName(): string
    {
        return 'artifactory repo (' . Url::sanitize($this->getUrl()) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getRepoType(): string
    {
        return 'bower';
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
        $cleanedUrl = str_replace('art://', '', $item['url']);
        $url = $this->getUrl() . '/refs/' . $cleanedUrl;
        $results = [];

        $response = $this->httpDownloader->get($url, $this->options);
        $refs = Preg::split('#[\r\n]+#', $response->getBody());

        $versionParser = new VersionParser();

        foreach ($refs as $ref) {
            if (Preg::match('#^([a-f\d]{44})\s+refs/tags/(\S+)#', $ref, $matches)) {
                try {
                    $v = $versionParser->normalize($matches[2]);
                } catch (\UnexpectedValueException) {
                    continue;
                }
                $results[] = [
                    'name' => 'bower-asset/' . $item['name'],
                    'version' => $matches[2],
                    'version_normalized' => $v,
                    'dist' => [
                        'type' => 'tar',
                        'url' => $this->getUrl() . '/binaries/' . $cleanedUrl . '.git/' . $matches[2]
                    ],
                    'source' => [
                        'type' => 'git',
                        'url' => $this->getUrl() . '/binaries/' . $cleanedUrl . '.git/' . $matches[2],
                        'reference' => $matches[1]
                    ]
                ];
            }
        }

        return $results;
    }
}
