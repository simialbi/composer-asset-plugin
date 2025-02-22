<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Exception;

/**
 * The Invalid Create Asset Repository Exception.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class InvalidCreateRepositoryException extends \Exception implements ExceptionInterface
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * Set the data of asset package config defined by the registry.
     *
     * @param array $data The data
     *
     * @return self
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the data of asset package config defined by the registry.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
