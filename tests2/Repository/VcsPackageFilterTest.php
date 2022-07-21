<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Repository;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledFilesystemRepository;
use Fxp\Composer\AssetPlugin\Config\ConfigBuilder;
use Fxp\Composer\AssetPlugin\Package\Version\VersionParser;
use Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Tests of VCS Package Filter.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class VcsPackageFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Composer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|Composer $composer;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface
     */
    protected \PHPUnit\Framework\MockObject\MockObject|RootPackageInterface $package;

    /**
     * @var InstallationManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|InstallationManager $installationManager;

    /**
     * @var InstalledFilesystemRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|InstalledFilesystemRepository $installedRepository;

    /**
     * @var AssetTypeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected \PHPUnit\Framework\MockObject\MockObject|AssetTypeInterface $assetType;

    /**
     * @var VcsPackageFilter
     */
    protected VcsPackageFilter $filter;

    protected function setUp(): void
    {
        $this->composer = $this->getMockBuilder('Composer\Composer')->disableOriginalConstructor()->getMock();
        $this->package = $this->getMockBuilder('Composer\Package\RootPackageInterface')->getMock();
        $this->assetType = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Type\AssetTypeInterface')->getMock();

        $versionConverter = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface')->getMock();
        $versionConverter->expects(self::any())
            ->method('convertVersion')
            ->willReturnCallback(function ($value) {
                return $value;
            });
        $this->assetType->expects(self::any())
            ->method('getVersionConverter')
            ->willReturn($versionConverter);

        $this->installationManager = $this->getMockBuilder('Composer\Installer\InstallationManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->installationManager->expects(self::any())
            ->method('isPackageInstalled')
            ->willReturn(true);

        $this->composer->expects(self::any())
            ->method('getPackage')
            ->willReturn($this->package);
    }

    protected function tearDown(): void
    {
        unset($this->package, $this->installedRepository, $this->assetType, $this->filter);
    }

    public function getDataProvider(): array
    {
        $configSkipPattern = ['pattern-skip-version' => false];
        $configSkipPatternPath = ['pattern-skip-version' => '(-patch)'];

        return [
            ['acme/foobar', 'v1.0.0', 'stable', [], false],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '>=1.0'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '>=1.0'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '>=1.0'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'dev', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'dev', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'dev', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'dev', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'dev', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'dev', ['acme/foobar' => '>=1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'dev', ['acme/foobar' => '>=1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '>=1.0@dev'], false],

            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '>=1.0@dev'], false],

            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '>=1.0@dev'], false],

            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0@beta'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '>=1.0@beta'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '>=1.0@beta'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '>=1.0@stable'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '>=1.0@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '>=1.0@beta'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '>=1.0@dev'], false],

            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@stable'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@stable'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@RC'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@beta'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@beta'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@alpha'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@alpha'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@dev'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0@dev'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@stable'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@stable'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@RC'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@beta'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@beta'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@alpha'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@alpha'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@dev'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '>=1.0@dev'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@stable'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@stable'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@RC'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@beta'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@beta'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@alpha'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@alpha'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@dev'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '>=1.0@dev'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@stable'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@stable'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@stable'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@RC'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@beta'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@beta'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@alpha'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@alpha'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@dev'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '>=1.0@dev'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '~1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '~1.0'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '~1.0'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '~1.0'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '~1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '~1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '~1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '@stable'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '@RC'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '@beta'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '@alpha'], false],
            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '@dev'], false],

            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '@stable'], true],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '@beta'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '@alpha'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '@dev'], false],

            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '@stable'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '@RC'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '@beta'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '@alpha'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '@dev'], false],

            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '@stable'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '@beta'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '@alpha'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '@dev'], false],

            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@stable'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@stable'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@stable'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@RC'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@beta'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@beta'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@beta'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@alpha'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@alpha'], true, $configSkipPatternPath],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@dev'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '@dev'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0-RC1'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0-beta1'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0-alpha1'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'RC', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'RC', ['acme/foobar' => '1.0-RC1'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'RC', ['acme/foobar' => '1.0-beta1'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'RC', ['acme/foobar' => '1.0-alpha1'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '1.0-patch1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '1.0-patch1'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'RC', ['acme/foobar' => '1.0-patch1'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'beta', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'beta', ['acme/foobar' => '1.0-RC1'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'beta', ['acme/foobar' => '1.0-beta1'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'beta', ['acme/foobar' => '1.0-alpha1'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '1.0-patch1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '1.0-patch1'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'beta', ['acme/foobar' => '1.0-patch1'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'alpha', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'alpha', ['acme/foobar' => '1.0-RC1'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'alpha', ['acme/foobar' => '1.0-beta1'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'alpha', ['acme/foobar' => '1.0-alpha1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '1.0-patch1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '1.0-patch1'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'alpha', ['acme/foobar' => '1.0-patch1'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'dev', ['acme/foobar' => '1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'dev', ['acme/foobar' => '1.0-RC1'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'dev', ['acme/foobar' => '1.0-beta1'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'dev', ['acme/foobar' => '1.0-alpha1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'dev', ['acme/foobar' => '1.0-patch1'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'dev', ['acme/foobar' => '1.0-patch1'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'dev', ['acme/foobar' => '1.0-patch1'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@stable'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0-RC1@stable'], true],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0-beta1@stable'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0-alpha1@stable'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@stable'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@stable'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@stable'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0-RC1@RC'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0-beta1@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0-alpha1@RC'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@RC'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@beta'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0-RC1@beta'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0-beta1@beta'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0-alpha1@beta'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@beta'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@beta'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@beta'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@alpha'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0-RC1@alpha'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0-beta1@alpha'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0-alpha1@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@alpha'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@alpha'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@alpha'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@dev'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0-RC1@dev'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0-beta1@dev'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0-alpha1@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@dev'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@dev'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0-patch1@dev'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*@RC'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], true],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], true],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0 | 1.0.*@RC'], true, $configSkipPatternPath],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '1.0@dev|1.0.*@RC'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*'], false, $configSkipPattern],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '1.0@dev | 1.0.*'], true, $configSkipPatternPath],

            ['acme/foobar', 'standard/1.0.0', 'stable', ['acme/foobar' => '>=1.0'], true]
        ];
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param string $packageName
     * @param string $version
     * @param string $minimumStability
     * @param array $rootRequires
     * @param bool $validSkip
     * @param array $rootConfig
     */
    public function testSkipVersion(string $packageName, string $version, string $minimumStability, array $rootRequires, bool $validSkip, array $rootConfig = [])
    {
        $this->init($rootRequires, $minimumStability, $rootConfig);

        self::assertSame($validSkip, $this->filter->skip($this->assetType, $packageName, $version));
    }

    public function getDataProviderForDisableTest(): array
    {
        return [
            ['acme/foobar', 'v1.0.0', 'stable', [], false],

            ['acme/foobar', 'v1.0.0', 'stable', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-RC1', 'stable', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-beta1', 'stable', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-alpha1', 'stable', ['acme/foobar' => '>=1.0'], false],
            ['acme/foobar', 'v1.0.0-patch1', 'stable', ['acme/foobar' => '>=1.0'], false]
        ];
    }

    /**
     * @dataProvider getDataProviderForDisableTest
     *
     * @param string $packageName
     * @param string $version
     * @param string $minimumStability
     * @param array $rootRequires
     * @param bool $validSkip
     */
    public function testDisabledFilterWithInstalledPackage(string $packageName, string $version, string $minimumStability, array $rootRequires, bool $validSkip)
    {
        $this->init($rootRequires, $minimumStability);
        $this->filter->setEnabled(false);

        self::assertSame($validSkip, $this->filter->skip($this->assetType, $packageName, $version));
    }

    public function getDataForInstalledTests(): array
    {
        $optn = 'optimize-with-installed-packages';
        $optn2 = 'optimize-with-conjunctive';

        $opt1 = [];
        $opt2 = [$optn => true, $optn2 => true];
        $opt3 = [$optn => false, $optn2 => true];
        $opt4 = [$optn => true, $optn2 => false];

        return [
            [$opt1, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', '1.0.0', true],
            [$opt2, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', '1.0.0', true],
            [$opt3, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', '1.0.0', false],
            [$opt4, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', '1.0.0', false],
            [$opt1, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', '1.0.0', true],
            [$opt2, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', '1.0.0', true],
            [$opt3, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', '1.0.0', false],
            [$opt4, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', '1.0.0', false],

            [$opt1, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', null, false],
            [$opt2, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', null, false],
            [$opt3, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', null, false],
            [$opt4, 'acme/foobar', 'v1.0.0', 'stable', '>=0.9', null, false],
            [$opt1, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', null, false],
            [$opt2, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', null, false],
            [$opt3, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', null, false],
            [$opt4, 'acme/foobar', 'v0.9.0', 'stable', '>=0.9', null, false],

            [$opt1, 'acme/foobar', 'v1.0.0', 'stable', null, '1.0.0', true],
            [$opt2, 'acme/foobar', 'v1.0.0', 'stable', null, '1.0.0', true],
            [$opt3, 'acme/foobar', 'v1.0.0', 'stable', null, '1.0.0', false],
            [$opt4, 'acme/foobar', 'v1.0.0', 'stable', null, '1.0.0', true],
            [$opt1, 'acme/foobar', 'v0.9.0', 'stable', null, '1.0.0', true],
            [$opt2, 'acme/foobar', 'v0.9.0', 'stable', null, '1.0.0', true],
            [$opt3, 'acme/foobar', 'v0.9.0', 'stable', null, '1.0.0', false],
            [$opt4, 'acme/foobar', 'v0.9.0', 'stable', null, '1.0.0', true],

            [$opt1, 'acme/foobar', 'v1.0.0', 'stable', null, null, false],
            [$opt2, 'acme/foobar', 'v1.0.0', 'stable', null, null, false],
            [$opt3, 'acme/foobar', 'v1.0.0', 'stable', null, null, false],
            [$opt4, 'acme/foobar', 'v1.0.0', 'stable', null, null, false],
            [$opt1, 'acme/foobar', 'v0.9.0', 'stable', null, null, false],
            [$opt2, 'acme/foobar', 'v0.9.0', 'stable', null, null, false],
            [$opt3, 'acme/foobar', 'v0.9.0', 'stable', null, null, false],
            [$opt4, 'acme/foobar', 'v0.9.0', 'stable', null, null, false],

            [$opt1, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', '1.0.0', true],
            [$opt2, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', '1.0.0', true],
            [$opt3, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', '1.0.0', false],
            [$opt4, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', '1.0.0', false],
            [$opt1, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', '1.0.0', true],
            [$opt2, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', '1.0.0', true],
            [$opt3, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', '1.0.0', false],
            [$opt4, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', '1.0.0', false],

            [$opt1, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', null, false],
            [$opt2, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', null, false],
            [$opt3, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', null, false],
            [$opt4, 'acme/foobar', 'v1.0.0', 'dev', '>=0.9@stable', null, false],
            [$opt1, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', null, false],
            [$opt2, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', null, false],
            [$opt3, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', null, false],
            [$opt4, 'acme/foobar', 'v0.9.0', 'dev', '>=0.9@stable', null, false]
        ];
    }

    /**
     * @dataProvider getDataForInstalledTests
     *
     * @param array $config
     * @param string $packageName
     * @param string $version
     * @param string $minimumStability
     * @param string|null $rootRequireVersion
     * @param string|null $installedVersion
     * @param bool $validSkip
     */
    public function testFilterWithInstalledPackage(array $config, string $packageName, string $version, string $minimumStability, ?string $rootRequireVersion, ?string $installedVersion, bool $validSkip)
    {
        $installed = null === $installedVersion
            ? []
            : [$packageName => $installedVersion];

        $require = null === $rootRequireVersion
            ? []
            : [$packageName => $rootRequireVersion];

        $this->installedRepository = $this->getMockBuilder('Composer\Repository\InstalledFilesystemRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->installedRepository->expects(self::any())
            ->method('getPackages')
            ->willReturn($this->convertInstalled($installed));

        $this->init($require, $minimumStability, $config);

        self::assertSame($validSkip, $this->filter->skip($this->assetType, $packageName, $version));
    }

    /**
     * Init test.
     *
     * @param array $requires
     * @param string $minimumStability
     * @param array $config
     */
    protected function init(array $requires = [], string $minimumStability = 'stable', array $config = [])
    {
        $parser = new ArrayLoader();
        $linkRequires = $parser->parseLinks('__ROOT__', '1.0.0', 'requires', $requires);

        $stabilityFlags = $this->findStabilityFlags($requires);

        $this->package->expects(self::any())
            ->method('getRequires')
            ->willReturn($linkRequires);
        $this->package->expects(self::any())
            ->method('getDevRequires')
            ->willReturn([]);
        $this->package->expects(self::any())
            ->method('getMinimumStability')
            ->willReturn($minimumStability);
        $this->package->expects(self::any())
            ->method('getStabilityFlags')
            ->willReturn($stabilityFlags);
        $this->package->expects(self::any())
            ->method('getConfig')
            ->willReturn([
                'fxp-asset' => $config,
            ]);

        /** @var RootPackageInterface $package */
        $package = $this->package;
        $config = ConfigBuilder::build($this->composer);

        $this->filter = new VcsPackageFilter($config, $package, $this->installationManager, $this->installedRepository);
    }

    /**
     * Convert the installed package data tests to mock package instance.
     *
     * @param array $installed The config of installed packages
     *
     * @return array The package instance of installed packages
     */
    protected function convertInstalled(array $installed): array
    {
        $packages = [];
        $parser = new VersionParser();

        foreach ($installed as $name => $version) {
            $package = $this->getMockBuilder('Composer\Package\PackageInterface')->getMock();

            $package->expects(self::any())
                ->method('getName')
                ->willReturn($name);

            $package->expects(self::any())
                ->method('getVersion')
                ->willReturn($parser->normalize($version));

            $package->expects(self::any())
                ->method('getPrettyVersion')
                ->willReturn($version);

            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * Find the stability flag of requires.
     *
     * @param array $requires The require dependencies
     *
     * @return array
     */
    protected function findStabilityFlags(array $requires): array
    {
        $flags = [];
        $stability = Package::$stabilities;

        foreach ($requires as $require => $prettyConstraint) {
            if (preg_match_all('/@(' . implode('|', array_keys($stability)) . ')/', $prettyConstraint, $matches)) {
                $flags[$require] = $stability[$matches[1][0]];
            }
        }

        return $flags;
    }
}
