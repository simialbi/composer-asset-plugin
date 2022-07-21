<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Fixtures\IO;

use Composer\IO\BaseIO;

/**
 * Mock of IO.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockIO extends BaseIO
{
    /**
     * @var bool
     */
    protected bool $verbose;

    /**
     * @var array
     */
    protected array $traces;

    /**
     * Constructor.
     *
     * @param bool $verbose
     */
    public function __construct(bool $verbose)
    {
        $this->verbose = $verbose;
        $this->traces = [];
    }

    public function isInteractive(): bool
    {
        return false;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function isVeryVerbose(): bool
    {
        return $this->verbose;
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function isDecorated(): bool
    {
        return false;
    }

    public function write($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $pos = max(\count($this->traces) - 1, 0);
        if (isset($this->traces[$pos])) {
            $messages = $this->traces[$pos] . $messages;
        }
        $this->traces[$pos] = $messages;
        if ($newline) {
            $this->traces[] = '';
        }
    }

    public function writeError($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->write($messages, $newline, $verbosity);
    }

    public function overwrite($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
        $pos = max(\count($this->traces) - 1, 0);
        $this->traces[$pos] = $messages;
        if ($newline) {
            $this->traces[] = '';
        }
    }

    public function overwriteError($messages, $newline = true, $size = null, $verbosity = self::NORMAL)
    {
        $this->overwrite($messages, $newline, $size, $verbosity);
    }

    public function ask($question, $default = null)
    {
        return $default;
    }

    public function askConfirmation($question, $default = true)
    {
        return $default;
    }

    public function askAndValidate($question, $validator, $attempts = false, $default = null)
    {
        return $default;
    }

    public function askAndHideAnswer($question)
    {
    }

    public function select($question, $choices, $default, $attempts = false, $errorMessage = 'Value "%s" is invalid', $multiselect = false): array|bool|int|string
    {
        return $default;
    }

    /**
     * Gets the traces.
     *
     * @return array
     */
    public function getTraces(): array
    {
        return $this->traces;
    }
}
