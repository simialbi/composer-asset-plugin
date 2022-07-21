<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs;

/**
 * Mock vcs driver for packages test.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockVcsDriverWithPackages extends MockVcsDriver
{
    protected array $composer = [
        'branch:master' => [
            'name' => 'foobar',
            'version' => '2.0',
        ],
        'branch:1.x' => [
            'name' => 'foobar',
            'version' => '1.1',
        ],
        'tag:v1.0.0' => [
            'name' => 'foobar',
            'version' => '1.0',
        ],
        'tag:v1.0.1' => [
            'name' => 'foobar',
        ],
        'tag:invalid' => [
            'name' => 'foobar',
            'description' => 'invalid tag name',
        ],
    ];

    public function getRootIdentifier(): string
    {
        return 'master';
    }

    public function hasComposerFile($identifier): bool
    {
        return isset($this->composer['branch:' . $identifier])
            || isset($this->composer['tag:' . $identifier]);
    }

    public function getComposerInformation($identifier): ?array
    {
        $composer = null;

        if ($this->hasComposerFile($identifier)) {
            if (isset($this->composer['branch:' . $identifier])) {
                $composer = $this->composer['branch:' . $identifier];
            } elseif (isset($this->composer['tag:' . $identifier])) {
                $composer = $this->composer['tag:' . $identifier];
            }
        }

        return $composer;
    }

    public function getBranches(): array
    {
        return $this->getDataPackages('branch');
    }

    public function getTags(): array
    {
        return $this->getDataPackages('tag');
    }

    /**
     * @param string $type
     *
     * @return array
     */
    protected function getDataPackages(string $type): array
    {
        $packages = [];

        foreach ($this->composer as $name => $data) {
            if (str_starts_with($name, $type . ':')) {
                $name = substr($name, strpos($name, ':') + 1);
                $packages[$name] = $data;
            }
        }

        return $packages;
    }
}
