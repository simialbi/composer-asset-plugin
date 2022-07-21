<?php
/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) Simon Karlen <simi.albi@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
        $rm->prependRepository($rm->createRepository('bower-artifactory', $repoConfig));
    }
}
