<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Repository;

use Composer\DependencyResolver\Pool;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;

/**
 * Helper for Repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class Util
{
    /**
     * Add repository config.
     * The instance of repository is returned if the repository in't added in the pool.
     *
     * @param IOInterface $io The IO instance
     * @param RepositoryManager $rm The repository manager
     * @param array $repos The list of already repository added (passed by reference)
     * @param string $name The name of the new repository
     * @param array $repoConfig The config of the new repository
     *
     * @return null|RepositoryInterface
     */
    public static function addRepository(
        IOInterface       $io,
        RepositoryManager $rm,
        array             &$repos,
        string            $name,
        array             $repoConfig
    ): ?RepositoryInterface
    {
        $repoConfig['name'] = $name;
        $repo = $rm->createRepository($repoConfig['type'], $repoConfig);

        return static::addRepositoryInstance($io, $rm, $repos, $name, $repo);
    }

    /**
     * Add repository instance.
     * The instance of repository is returned if the repository in't added in the pool.
     *
     * @param IOInterface $io The IO instance
     * @param RepositoryManager $rm The repository mamanger
     * @param array $repos The list of already repository added (passed by reference)
     * @param string $name The name of the new repository
     * @param RepositoryInterface $repo The repository instance
     * @param null|Pool $pool The pool
     *
     * @return null|RepositoryInterface
     */
    public static function addRepositoryInstance(
        IOInterface         $io,
        RepositoryManager   $rm,
        array               &$repos,
        string              $name,
        RepositoryInterface $repo
    ): ?RepositoryInterface
    {
        $notAddedRepo = null;

        if (!isset($repos[$name])) {
            static::writeAddRepository($io, $name);
            $notAddedRepo = $repo;
            $repos[$name] = $repo;
            $rm->addRepository($repo);
        }

        return $notAddedRepo;
    }

    /**
     * Cleans the package name, removing the Composer prefix if present.
     *
     * @param string $name
     *
     * @return string
     */
    public static function cleanPackageName(string $name): string
    {
        if (preg_match('/^[a-z]+\-asset\//', $name, $matches)) {
            $name = substr($name, \strlen($matches[0]));
        }

        return $name;
    }

    /**
     * Converts the alias of asset package name by the real asset package name.
     *
     * @param string $name
     *
     * @return string
     */
    public static function convertAliasName(string $name): string
    {
        if (preg_match('/([\w0-9\/_-]+)-\d+(.\d+)?.[\dxX]+$/', $name, $matches)) {
            return $matches[1];
        }

        return $name;
    }

    /**
     * Get the array value.
     *
     * @param array $array The array
     * @param string $name The key name
     * @param mixed|null $default The default value
     *
     * @return mixed
     */
    public static function getArrayValue(array $array, string $name, mixed $default = null): mixed
    {
        return $array[$name] ?? $default;
    }

    /**
     * Write the vcs repository name in output console.
     *
     * @param IOInterface $io The IO instance
     * @param string $name The vcs repository name
     *
     * @return void
     */
    protected static function writeAddRepository(IOInterface $io, string $name): void
    {
        if ($io->isVerbose()) {
            $io->write('Adding VCS repository <info>' . $name . '</info>');
        }
    }
}
