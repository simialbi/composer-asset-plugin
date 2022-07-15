<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Converter;

/**
 * Converter for Semver syntax version to composer syntax version.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class SemverConverter implements VersionConverterInterface
{
    /**
     * {@inheritDoc}
     */
    public function convertVersion(string $version): string
    {
        if (\in_array($version, [null, '', 'latest'], true)) {
            return ('latest' === $version ? 'default || ' : '') . '*';
        }

        $version = str_replace('–', '-', $version);
        $prefix = preg_match('/^[a-z]/', $version) && !str_starts_with($version, 'dev-') ? substr($version, 0, 1) : '';
        $version = substr($version, \strlen($prefix));
        $version = SemverUtil::convertVersionMetadata($version);
        $version = SemverUtil::convertDateVersion($version);

        return $prefix . $version;
    }

    /**
     * {@inheritDoc}
     */
    public function convertRange(string $range): string
    {
        $range = $this->cleanRange(strtolower($range));

        return $this->matchRange($range);
    }

    /**
     * Clean the raw range.
     *
     * @param string $range
     *
     * @return string
     */
    protected function cleanRange(string $range): string
    {
        foreach (['<', '>', '=', '~', '^', '||', '&&'] as $character) {
            $range = str_replace($character . ' ', $character, $range);
        }

        $range = preg_replace('/(?:[vV])(\d+)/', '${1}', $range);
        $range = str_replace(' ||', '||', $range);

        return str_replace([' &&', '&&'], ',', $range);
    }

    /**
     * Match the range.
     *
     * @param string $range The range cleaned
     *
     * @return string The range
     */
    protected function matchRange(string $range): string
    {
        $pattern = '/(\ -\ )|(<)|(>)|(=)|(\|\|)|(\ )|(,)|(\~)|(\^)/';
        $matches = preg_split($pattern, $range, -1, PREG_SPLIT_DELIM_CAPTURE);
        $special = null;
        $replace = null;
        $first = true;

        foreach ($matches as $i => $match) {
            if ($first && '' !== $match) {
                $first = false;
                $match = '=' === $match ? 'EQUAL' : $match;
            }

            $this->matchRangeToken($i, $match, $matches, $special, $replace);
        }

        return implode('', $matches);
    }

    /**
     * Converts the token of the matched range.
     *
     * @param int $i
     * @param string $match
     * @param array $matches
     * @param string|null $special
     * @param string|null $replace
     */
    protected function matchRangeToken(int $i, string $match, array &$matches, ?string &$special, ?string &$replace)
    {
        if (' - ' === $match) {
            $matches[$i - 1] = '>=' . str_replace(['*', 'x', 'X'], '0', $matches[$i - 1]);

            if (str_contains($matches[$i + 1], '.') && !str_contains($matches[$i + 1], '*')
                && !str_contains($matches[$i + 1], 'x') && !str_contains($matches[$i + 1], 'X')) {
                $matches[$i] = ',<=';
            } else {
                $matches[$i] = ',<';
                $special = ',<~';
            }
        } else {
            $this->matchRangeTokenStep2($i, $match, $matches, $special, $replace);
        }
    }

    /**
     * Step2: Converts the token of the matched range.
     *
     * @param int $i
     * @param string $match
     * @param array $matches
     * @param string|null $special
     * @param string|null $replace
     */
    protected function matchRangeTokenStep2(int $i, string $match, array &$matches, ?string &$special, ?string &$replace)
    {
        if (\in_array($match, ['', '<', '>', '=', ','], true)) {
            $replace = \in_array($match, ['<', '>'], true) ? $match : $replace;
            $matches[$i] = '~' === $special && \in_array($replace, ['<', '>'], true) ? '' : $matches[$i];
        } elseif ('~' === $match) {
            $special = $match;
        } elseif (\in_array($match, ['EQUAL', '^'], true)) {
            $special = $match;
            $matches[$i] = '';
        } else {
            $this->matchRangeTokenStep3($i, $match, $matches, $special, $replace);
        }
    }

    /**
     * Step3: Converts the token of the matched range.
     *
     * @param int $i
     * @param string $match
     * @param array $matches
     * @param string|null $special
     * @param string|null $replace
     */
    protected function matchRangeTokenStep3(int $i, string $match, array &$matches, ?string &$special, ?string &$replace)
    {
        if (' ' === $match) {
            $matches[$i] = ',';
        } elseif ('||' === $match) {
            $matches[$i] = '|';
        } elseif (\in_array($special, ['^'], true)) {
            $matches[$i] = SemverRangeUtil::replaceSpecialRange($this, $match);
            $special = null;
        } else {
            $this->matchRangeTokenStep4($i, $match, $matches, $special, $replace);
        }
    }

    /**
     * Step4: Converts the token of the matched range.
     *
     * @param int $i
     * @param string $match
     * @param array $matches
     * @param string|null $special
     * @param string|null $replace
     */
    protected function matchRangeTokenStep4(int $i, string $match, array &$matches, ?string &$special, ?string &$replace)
    {
        if (',<~' === $special) {
            // Version range contains x in last place.
            $match .= (false === strpos($match, '.') ? '.x' : '');
            $version = explode('.', $match);
            $change = \count($version) - 2;
            $version[$change] = (int)($version[$change]) + 1;
            $match = str_replace(['*', 'x', 'X'], '0', implode('.', $version));
        } elseif (null === $special && 0 === $i && false === strpos($match, '.') && is_numeric($match)) {
            $match = isset($matches[$i + 1]) && (' - ' === $matches[$i + 1] || '-' === $matches[$i + 1])
                ? $match
                : '~' . $match;
        } else {
            $match = '~' === $special ? str_replace(['*', 'x', 'X'], '0', $match) : $match;
        }

        $this->matchRangeTokenStep5($i, $match, $matches, $special, $replace);
    }

    /**
     * Step5: Converts the token of the matched range.
     *
     * @param int $i
     * @param string $match
     * @param array $matches
     * @param string|null $special
     * @param string|null $replace
     */
    protected function matchRangeTokenStep5(int $i, string $match, array &$matches, ?string &$special, ?string &$replace)
    {
        $matches[$i] = $this->convertVersion($match);
        $matches[$i] = $replace
            ? SemverUtil::replaceAlias($matches[$i], $replace)
            : $matches[$i];
        $matches[$i] .= '~' === $special && \in_array($replace, ['<', '>'], true)
            ? ',' . $replace . $matches[$i]
            : '';
        $special = null;
        $replace = null;
    }
}
