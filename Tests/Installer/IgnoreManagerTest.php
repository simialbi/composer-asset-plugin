<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Installer;

use Composer\Util\Filesystem;
use Fxp\Composer\AssetPlugin\Installer\IgnoreManager;

/**
 * Tests of manager of ignore patterns.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 *
 * @internal
 */
final class IgnoreManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string
     */
    private string $target;

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $this->target = sys_get_temp_dir() . '/composer-foo';

        foreach ($this->getFixtureFiles() as $filename) {
            $path = $this->target . '/' . $filename;
            $fs->ensureDirectoryExists(\dirname($path));
            @file_put_contents($path, '');
        }
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->target);
    }

    public function testDeleteIgnoredFiles()
    {
        $ignorer = new IgnoreManager($this->target);
        $ignorer->addPattern('.*');
        $ignorer->addPattern('**/.*');
        $ignorer->addPattern('README');
        $ignorer->addPattern('**/*.md');
        $ignorer->addPattern('lib');
        $ignorer->addPattern('tests');
        $ignorer->addPattern('**/doc');
        $ignorer->addPattern('src/foo/*.txt');
        $ignorer->addPattern('!src/foo/small.txt');

        $ignorer->cleanup();

        self::assertFileDoesNotExist($this->target . '/.hidden');
        self::assertFileExists($this->target . '/CHANGELOG');
        self::assertFileDoesNotExist($this->target . '/README');

        self::assertFileDoesNotExist($this->target . '/lib/autoload.php');
        self::assertFileDoesNotExist($this->target . '/lib');

        self::assertFileDoesNotExist($this->target . '/src/.hidden');
        self::assertFileDoesNotExist($this->target . '/src/doc');
        self::assertFileExists($this->target . '/src');

        self::assertFileDoesNotExist($this->target . '/src/foo/.hidden');
        self::assertFileExists($this->target . '/src/foo/empty.html');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.md');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.txt');
        self::assertFileExists($this->target . '/src/foo/small.txt');
        self::assertFileExists($this->target . '/src/foo');

        self::assertFileExists($this->target . '/src/lib/empty.txt');
        self::assertFileExists($this->target . '/src/lib');

        self::assertFileExists($this->target . '/src/lib/foo/empty.txt');
        self::assertFileExists($this->target . '/src/lib/foo/small.txt');
        self::assertFileExists($this->target . '/src/lib/foo');

        self::assertFileExists($this->target . '/src/tests/empty.html');
        self::assertFileExists($this->target . '/src/tests');

        self::assertFileDoesNotExist($this->target . '/tests/bootstrap.php');
        self::assertFileDoesNotExist($this->target . '/tests');
    }

    public function testDeleteIgnoredFilesWithDisabledManager()
    {
        $ignorer = new IgnoreManager($this->target);
        $ignorer->setEnabled(false);
        $ignorer->addPattern('.*');
        $ignorer->addPattern('**/.*');
        $ignorer->addPattern('README');
        $ignorer->addPattern('**/*.md');
        $ignorer->addPattern('lib');
        $ignorer->addPattern('tests');
        $ignorer->addPattern('**/doc');
        $ignorer->addPattern('src/foo/*.txt');
        $ignorer->addPattern('!src/foo/small.txt');

        $ignorer->cleanup();

        self::assertFileExists($this->target . '/.hidden');
        self::assertFileExists($this->target . '/CHANGELOG');
        self::assertFileExists($this->target . '/README');

        self::assertFileExists($this->target . '/lib/autoload.php');
        self::assertFileExists($this->target . '/lib');

        self::assertFileExists($this->target . '/src/.hidden');
        self::assertFileExists($this->target . '/src/doc');
        self::assertFileExists($this->target . '/src');

        self::assertFileExists($this->target . '/src/foo/.hidden');
        self::assertFileExists($this->target . '/src/foo/empty.html');
        self::assertFileExists($this->target . '/src/foo/empty.md');
        self::assertFileExists($this->target . '/src/foo/empty.txt');
        self::assertFileExists($this->target . '/src/foo/small.txt');
        self::assertFileExists($this->target . '/src/foo');

        self::assertFileExists($this->target . '/src/lib/empty.txt');
        self::assertFileExists($this->target . '/src/lib');

        self::assertFileExists($this->target . '/src/lib/foo/empty.txt');
        self::assertFileExists($this->target . '/src/lib/foo/small.txt');
        self::assertFileExists($this->target . '/src/lib/foo');

        self::assertFileExists($this->target . '/src/tests/empty.html');
        self::assertFileExists($this->target . '/src/tests');

        self::assertFileExists($this->target . '/tests/bootstrap.php');
        self::assertFileExists($this->target . '/tests');
    }

    public function testIgnoreAllFilesExceptAFew()
    {
        $ignorer = new IgnoreManager($this->target);
        $ignorer->addPattern('*');
        $ignorer->addPattern('**/.*');
        $ignorer->addPattern('!README');
        $ignorer->addPattern('!lib/*');
        $ignorer->addPattern('!tests');

        $ignorer->cleanup();

        self::assertFileDoesNotExist($this->target . '/.hidden');
        self::assertFileDoesNotExist($this->target . '/CHANGELOG');
        self::assertFileExists($this->target . '/README');

        self::assertFileExists($this->target . '/lib/autoload.php');
        self::assertFileExists($this->target . '/lib');

        self::assertFileDoesNotExist($this->target . '/src/.hidden');
        self::assertFileDoesNotExist($this->target . '/src/doc');
        self::assertFileDoesNotExist($this->target . '/src');

        self::assertFileDoesNotExist($this->target . '/src/foo/.hidden');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.html');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.md');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.txt');
        self::assertFileDoesNotExist($this->target . '/src/foo/small.txt');
        self::assertFileDoesNotExist($this->target . '/src/foo');

        self::assertFileDoesNotExist($this->target . '/src/lib/empty.txt');
        self::assertFileDoesNotExist($this->target . '/src/lib');

        self::assertFileDoesNotExist($this->target . '/src/lib/foo/empty.txt');
        self::assertFileDoesNotExist($this->target . '/src/lib/foo/small.txt');
        self::assertFileDoesNotExist($this->target . '/src/lib/foo');

        self::assertFileDoesNotExist($this->target . '/src/tests/empty.html');
        self::assertFileDoesNotExist($this->target . '/src/tests');

        self::assertFileExists($this->target . '/tests/bootstrap.php');
        self::assertFileExists($this->target . '/tests');
    }

    public function testIgnoreAllFilesExceptAFewWithDoubleAsterisks()
    {
        $ignorer = new IgnoreManager($this->target);

        $ignorer->addPattern('**');
        $ignorer->addPattern('!/src/foo/*.txt');

        $ignorer->cleanup();

        self::assertFileExists($this->target . '/.hidden');
        self::assertFileDoesNotExist($this->target . '/CHANGELOG');
        self::assertFileDoesNotExist($this->target . '/README');

        self::assertFileDoesNotExist($this->target . '/lib/autoload.php');
        self::assertFileDoesNotExist($this->target . '/lib');

        self::assertFileDoesNotExist($this->target . '/src/.hidden');
        self::assertFileDoesNotExist($this->target . '/src/doc');
        self::assertFileExists($this->target . '/src');

        self::assertFileDoesNotExist($this->target . '/src/foo/.hidden');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.html');
        self::assertFileDoesNotExist($this->target . '/src/foo/empty.md');
        self::assertFileExists($this->target . '/src/foo/empty.txt');
        self::assertFileExists($this->target . '/src/foo/small.txt');
        self::assertFileExists($this->target . '/src/foo');

        self::assertFileDoesNotExist($this->target . '/src/lib/empty.txt');
        self::assertFileDoesNotExist($this->target . '/src/lib');

        self::assertFileDoesNotExist($this->target . '/src/lib/foo/empty.txt');
        self::assertFileDoesNotExist($this->target . '/src/lib/foo/small.txt');
        self::assertFileDoesNotExist($this->target . '/src/lib/foo');

        self::assertFileDoesNotExist($this->target . '/src/tests/empty.html');
        self::assertFileDoesNotExist($this->target . '/src/tests');

        self::assertFileDoesNotExist($this->target . '/tests/bootstrap.php');
        self::assertFileDoesNotExist($this->target . '/tests');
    }

    /**
     * @return array
     */
    protected function getFixtureFiles(): array
    {
        return [
            '.hidden',
            'CHANGELOG',
            'README',
            'lib/autoload.php',
            'src/.hidden',
            'src/doc',
            'src/foo/.hidden',
            'src/foo/empty.html',
            'src/foo/empty.md',
            'src/foo/empty.txt',
            'src/foo/small.txt',
            'src/lib/empty.txt',
            'src/lib/foo/empty.txt',
            'src/lib/foo/small.txt',
            'src/tests/empty.html',
            'tests/bootstrap.php',
        ];
    }
}
