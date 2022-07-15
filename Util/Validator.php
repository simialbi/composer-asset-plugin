<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Util;

use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Helper for validate branches and tags of the VCS repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class Validator
{
    /**
     * Validates the branch.
     *
     * @param string $branch
     * @param VersionParser|null $parser
     *
     * @return false|string
     */
    public static function validateBranch(string $branch, VersionParser $parser = null): string|false
    {
        if (null === $parser) {
            $parser = new VersionParser();
        }

        $normalize = $parser->normalizeBranch($branch);

        if (str_contains($normalize, '.9999999-dev')) {
            return false;
        }

        return $normalize;
    }

    /**
     * Validates the tag.
     *
     * @param string $tag
     * @param AssetTypeInterface $assetType
     * @param VersionParser|null $parser
     *
     * @return false|string
     */
    public static function validateTag(string $tag, AssetTypeInterface $assetType, VersionParser $parser = null): string|false
    {
        if (\in_array($tag, ['master', 'trunk', 'default'], true)) {
            return false;
        }

        if (null === $parser) {
            $parser = new VersionParser();
        }

        try {
            $tag = $assetType->getVersionConverter()->convertVersion($tag);
            $tag = $parser->normalize($tag);
        } catch (\Exception $e) {
            return false;
        }

        return $tag;
    }
}
