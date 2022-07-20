<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Util\AssetPlugin;

class ArtifactoryBowerRegistryFactory implements RegistryFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public static function create(AssetRepositoryManager $arm, VcsPackageFilter $filter, Config $config): void
    {
        if (!($url = $config->get('artifactory-url', false))) {
            return;
        }

        $rm = $arm->getRepositoryManager();

        $repoConfig = AssetPlugin::createRepositoryConfig($arm, $filter, $config, 'bower-artifactory');
        $repoConfig['registry-url'] = $url;

        $rm->setRepositoryClass('bower-artifactory', 'Fxp\Composer\AssetPlugin\Repository\ArtifactoryBowerRepository');
        $rm->addRepository($rm->createRepository('bower-artifactory', $repoConfig));
    }
}
