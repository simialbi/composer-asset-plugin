<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Config;

use Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException;

/**
 * Helper of package config.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
final class Config
{
    /**
     * @var array
     */
    private array $config;

    /**
     * @var array
     */
    private array $cacheEnv = [];

    /**
     * Constructor.
     *
     * @param array $config The config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get the array config value.
     *
     * @param string $key The config key
     * @param array $default The default value
     *
     * @return array
     */
    public function getArray(string $key, array $default = []): array
    {
        return $this->get($key, $default);
    }

    /**
     * Get the config value.
     *
     * @param string $key The config key
     * @param mixed|null $default The default value
     *
     * @return null|mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $this->cacheEnv)) {
            return $this->cacheEnv[$key];
        }
        $envKey = $this->convertEnvKey($key);
        $envValue = getenv($envKey);

        if (false !== $envValue) {
            return $this->cacheEnv[$key] = $this->convertEnvValue($envValue, $envKey);
        }

        return \array_key_exists($key, $this->config)
            ? $this->config[$key]
            : $default;
    }

    /**
     * Convert the config key into environment variable.
     *
     * @param string $key The config key
     *
     * @return string
     */
    private function convertEnvKey(string $key): string
    {
        return 'FXP_ASSET__' . strtoupper(str_replace('-', '_', $key));
    }

    /**
     * Convert the value of environment variable into php variable.
     *
     * @param string $value The value of environment variable
     * @param string $environmentVariable The environment variable name
     *
     * @return array|bool|int|string
     */
    private function convertEnvValue(string $value, string $environmentVariable): array|bool|int|string
    {
        $value = trim(trim(trim($value, '\''), '"'));

        if ($this->isBoolean($value)) {
            $value = $this->convertBoolean($value);
        } elseif ($this->isInteger($value)) {
            $value = $this->convertInteger($value);
        } elseif ($this->isJson($value)) {
            $value = $this->convertJson($value, $environmentVariable);
        }

        return $value;
    }

    /**
     * Check if the value of environment variable is a boolean.
     *
     * @param string $value The value of environment variable
     *
     * @return bool
     */
    private function isBoolean(string $value): bool
    {
        $value = strtolower($value);

        return \in_array($value, ['true', 'false', '1', '0', 'yes', 'no', 'y', 'n'], true);
    }

    /**
     * Convert the value of environment variable into a boolean.
     *
     * @param string $value The value of environment variable
     *
     * @return bool
     */
    private function convertBoolean(string $value): bool
    {
        return \in_array($value, ['true', '1', 'yes', 'y'], true);
    }

    /**
     * Check if the value of environment variable is a integer.
     *
     * @param string $value The value of environment variable
     *
     * @return bool
     */
    private function isInteger(string $value): bool
    {
        return ctype_digit(trim($value, '-'));
    }

    /**
     * Convert the value of environment variable into a integer.
     *
     * @param string|int|float $value The value of environment variable
     *
     * @return integer
     */
    private function convertInteger(string|int|float $value): int
    {
        return (int)$value;
    }

    /**
     * Check if the value of environment variable is a string JSON.
     *
     * @param string $value The value of environment variable
     *
     * @return bool
     */
    private function isJson(string $value): bool
    {
        return str_starts_with($value, '{') || str_starts_with($value, '[');
    }

    /**
     * Convert the value of environment variable into a json array.
     *
     * @param string $value The value of environment variable
     * @param string $environmentVariable The environment variable name
     *
     * @return array
     */
    private function convertJson(string $value, string $environmentVariable): array
    {
        $value = json_decode($value, true);

        if (json_last_error()) {
            throw new InvalidArgumentException(sprintf('The "%s" environment variable isn\'t a valid JSON', $environmentVariable));
        }

        return $value;
    }
}
