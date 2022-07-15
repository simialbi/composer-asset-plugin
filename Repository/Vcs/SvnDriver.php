<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository\Vcs;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Repository\Vcs\SvnDriver as BaseSvnDriver;

/**
 * SVN vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class SvnDriver extends BaseSvnDriver
{
    /**
     * {@inheritDoc}
     */
    public function initialize(): void
    {
        $this->url = 0 === strpos($this->url, 'svn+http')
            ? substr($this->url, 4)
            : $this->url;

        parent::initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function getComposerInformation(string $identifier): ?array
    {
        $identifier = '/' . trim($identifier, '/') . '/';
        $this->infoCache[$identifier] = Util::readCache($this->infoCache, $this->cache, $this->repoConfig['asset-type'], trim($identifier, '/'), true);

        if (!isset($this->infoCache[$identifier])) {
            [$path, $rev] = $this->getPathRev($identifier);
            $resource = $path . $this->repoConfig['filename'];
            $output = $this->getComposerContent($resource, $rev);
            $composer = $this->parseComposerContent($output, $resource, $path, $rev);

            Util::writeCache($this->cache, $this->repoConfig['asset-type'], trim($identifier, '/'), $composer, true);
            $this->infoCache[$identifier] = $composer;
        }

        return $this->infoCache[$identifier];
    }

    /**
     * {@inheritDoc}
     */
    public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool
    {
        if (str_starts_with($url, 'http') && preg_match('/\/svn|svn\//i', $url)) {
            $url = 'svn' . substr($url, strpos($url, '://'));
        }

        return parent::supports($io, $config, $url, $deep);
    }

    /**
     * Get path and rev.
     *
     * @param string $identifier The identifier
     *
     * @return string[]
     */
    protected function getPathRev(string $identifier): array
    {
        $path = $identifier;
        $rev = '';

        preg_match('{^(.+?)(@\d+)?/$}', $identifier, $match);

        if (!empty($match[2])) {
            $path = $match[1];
            $rev = $match[2];
        }

        return [$path, $rev];
    }

    /**
     * Get the composer content.
     *
     * @param string $resource The resource
     * @param string $rev The rev
     *
     * @return null|string The composer content
     * @throws TransportException
     *
     */
    protected function getComposerContent(string $resource, string $rev): ?string
    {
        $output = null;

        try {
            $output = $this->execute($this->getSvnCredetials('svn cat'), $this->baseUrl . $resource . $rev);
        } catch (\RuntimeException $e) {
            throw new TransportException($e->getMessage());
        }

        return $output;
    }

    /**
     * Parse the content of composer.
     *
     * @param string|null $output The output of process executor
     * @param string $resource The resource
     * @param string $path The path
     * @param string $rev The rev
     *
     * @return array The composer
     * @throws \Seld\JsonLint\ParsingException
     */
    protected function parseComposerContent(?string $output, string $resource, string $path, string $rev): array
    {
        if (!trim($output)) {
            return ['_nonexistent_package' => true];
        }

        $composer = (array)JsonFile::parseJson($output, $this->baseUrl . $resource . $rev);

        return $this->addComposerTime($composer, $path, $rev);
    }

    /**
     * Add time in composer.
     *
     * @param array $composer The composer
     * @param string $path The path
     * @param string $rev The rev
     *
     * @return array The composer
     * @throws
     *
     */
    protected function addComposerTime(array $composer, string $path, string $rev)
    {
        if (!isset($composer['time'])) {
            $output = $this->execute($this->getSvnCredetials('svn info'), $this->baseUrl . $path . $rev);

            foreach ($this->process->splitLines($output) as $line) {
                if ($line && preg_match('{^Last Changed Date: ([^(]+)}', $line, $match)) {
                    $date = new \DateTime($match[1], new \DateTimeZone('UTC'));
                    $composer['time'] = $date->format('Y-m-d H:i:s');

                    break;
                }
            }
        }

        return $composer;
    }

    /**
     * Get the credentials of SVN.
     *
     * @param string $command The command
     *
     * @return string
     */
    protected function getSvnCredetials(string $command): string
    {
        $httpBasic = $this->config->get('http-basic');
        $parsedUrl = parse_url($this->baseUrl);
        $svnCommand = $command;

        if ($parsedUrl && isset($httpBasic[$parsedUrl['host']])) {
            if ($httpBasic[$parsedUrl['host']]['username'] && $httpBasic[$parsedUrl['host']]['password']) {
                $uname = $httpBasic[$parsedUrl['host']]['username'];
                $pw = $httpBasic[$parsedUrl['host']]['password'];

                $svnCommand = $command . sprintf(' --username %s --password %s --no-auth-cache', $uname, $pw);
            }
        }

        return $svnCommand;
    }
}
