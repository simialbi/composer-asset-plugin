<?php
/**
 * @package composer-asset-plugin
 * @author Simon Karlen <simi.albi@outlook.com>
 */

namespace Fxp\Composer\AssetPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Fxp\Composer\AssetPlugin\Installer\BowerInstaller;
use Fxp\Composer\AssetPlugin\Installer\NpmInstaller;
use Fxp\Composer\AssetPlugin\Repository\BowerArtifactoryRepository;
use Fxp\Composer\AssetPlugin\Repository\BowerRepository;
use Fxp\Composer\AssetPlugin\Repository\NpmRepository;

class FxpAssetPlugin implements PluginInterface
{
    protected Composer $composer;
    protected IOInterface $io;

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $config = $composer->getConfig()->get('fxp-asset');

        if (!isset($config['enabled']) || $config['enabled']) {
            $this->composer = $composer;
            $this->io = $io;
            if (!isset($config['registry-options']['npm-enabled']) || $config['registry-options']['npm-enabled']) {
                $composer->getInstallationManager()->addInstaller(new NpmInstaller($this->io, $this->composer, 'npm-asset'));
                $composer->getRepositoryManager()->addRepository(new NpmRepository(
                    [],
                    $composer,
                    $io,
                    $composer->getConfig(),
                    $composer->getLoop()->getHttpDownloader(),
                    $composer->getEventDispatcher()
                ));
            }

            if (!isset($config['registry-options']['bower-enabled']) || $config['registry-options']['bower-enabled']) {
                $composer->getInstallationManager()->addInstaller(new BowerInstaller($this->io, $this->composer, 'bower-asset'));
                $composer->getRepositoryManager()->setRepositoryClass('bower+github', '\Fxp\Composer\AssetPlugin\Repository\VcsRepository');
                $composer->getRepositoryManager()->addRepository(new BowerRepository(
                    [],
                    $composer,
                    $io,
                    $composer->getConfig(),
                    $composer->getLoop()->getHttpDownloader(),
                    $composer->getEventDispatcher()
                ));
            }

            if (isset($config['repositories']) && is_array($config['repositories']))
            foreach ($config['repositories'] as $repository) {
                $type = $repository['type'];
                unset($repository['type']);
                switch ($type) {
                    case 'npm':
                        $repo = new NpmRepository(
                            $repository,
                            $composer,
                            $io,
                            $composer->getConfig(),
                            $composer->getLoop()->getHttpDownloader(),
                            $composer->getEventDispatcher()
                        );
                        break;
                    case 'bower':
                        $repo = new BowerRepository(
                            $repository,
                            $composer,
                            $io,
                            $composer->getConfig(),
                            $composer->getLoop()->getHttpDownloader(),
                            $composer->getEventDispatcher()
                        );
                        break;
                    case 'bower-artifactory':
                        $repo = new BowerArtifactoryRepository(
                            $repository,
                            $composer,
                            $io,
                            $composer->getConfig(),
                            $composer->getLoop()->getHttpDownloader(),
                            $composer->getEventDispatcher()
                        );
                        break;
                    default:
                        continue 2;
                }
                $composer->getRepositoryManager()->addRepository($repo);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
