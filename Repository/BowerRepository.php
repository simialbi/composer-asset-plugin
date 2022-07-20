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

use Composer\Semver\Constraint\ConstraintInterface;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Bower repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class BowerRepository extends AbstractAssetsRepository
{
    /**
     * {@inheritDoc}
     */
    protected function getType(): string
    {
        return 'bower';
    }

    /**
     * {@inheritDoc}
     */
    protected function getUrl(): string
    {
        return 'https://registry.bower.io/packages';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPackageUrl(): string
    {
        return $this->canonicalizeUrl($this->baseUrl . '/%package%');
    }

    /**
     * {@inheritDoc}
     */
    protected function getSearchUrl(): string
    {
        return $this->canonicalizeUrl($this->baseUrl . '/search/%query%');
    }

    protected function whatProvides(string $name, ConstraintInterface $constraint, ?array $acceptableStability = null, ?array $stabilityFlags = null, array $alreadyLoaded = []): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    #[ArrayShape([
        'type' => 'string',
        'url' => 'string',
        'name' => 'string'
    ])]
    protected function createVcsRepositoryConfig(array $data, string $registryName = null): array
    {
        return [
            'type' => $this->assetType->getName() . '-vcs',
            'url' => $data['url'],
            'name' => $registryName
        ];
    }
}
