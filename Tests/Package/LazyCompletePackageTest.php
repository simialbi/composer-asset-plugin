<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Package;

use Composer\Package\CompletePackage;
use Fxp\Composer\AssetPlugin\Package\LazyCompletePackage;
use Fxp\Composer\AssetPlugin\Package\LazyPackageInterface;
use Fxp\Composer\AssetPlugin\Package\Loader\LazyLoaderInterface;

/**
 * Tests of lazy asset package loader.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class LazyCompletePackageTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var LazyPackageInterface
     */
    protected LazyPackageInterface $package;

    protected function setUp(): void
    {
        $this->package = new LazyCompletePackage('foo', '1.0.0.0', '1.0');
    }

    protected function tearDown(): void
    {
        unset($this->package);
    }

    public function getConfigLazyLoader(): array
    {
        return [
            [null],
            ['lazy'],
            ['lazy-exception'],
        ];
    }

    /**
     * @param string|null $lazyType
     *
     * @dataProvider getConfigLazyLoader
     */
    public function testMissingAssetType(?string $lazyType)
    {
        if (null !== $lazyType) {
            $lp = 'lazy' === $lazyType
                ? new CompletePackage(
                    $this->package->getName(),
                    $this->package->getVersion(),
                    $this->package->getPrettyVersion()
                )
                : false;

            $loader = $this->getMockBuilder('Fxp\Composer\AssetPlugin\Package\Loader\LazyLoaderInterface')->getMock();
            $loader
                ->expects(self::any())
                ->method('load')
                ->willReturn($lp);

            /* @var LazyLoaderInterface $loader */
            $this->package->setLoader($loader);
        }

        self::assertSame('library', $this->package->getType());
        self::assertSame([], $this->package->getTransportOptions());
        self::assertNull($this->package->getTargetDir());
        self::assertSame([], $this->package->getExtra());
        self::assertSame([], $this->package->getBinaries());
        self::assertNull($this->package->getInstallationSource());
        self::assertNull($this->package->getSourceType());
        self::assertNull($this->package->getSourceUrl());
        self::assertNull($this->package->getSourceReference());
        self::assertNull($this->package->getSourceMirrors());
        self::assertSame([], $this->package->getSourceUrls());
        self::assertNull($this->package->getDistType());
        self::assertNull($this->package->getDistUrl());
        self::assertNull($this->package->getDistReference());
        self::assertNull($this->package->getDistSha1Checksum());
        self::assertNull($this->package->getDistMirrors());
        self::assertSame([], $this->package->getDistUrls());
        self::assertNull($this->package->getReleaseDate());
        self::assertSame([], $this->package->getRequires());
        self::assertSame([], $this->package->getConflicts());
        self::assertSame([], $this->package->getProvides());
        self::assertSame([], $this->package->getReplaces());
        self::assertSame([], $this->package->getDevRequires());
        self::assertSame([], $this->package->getSuggests());
        self::assertSame([], $this->package->getAutoload());
        self::assertSame([], $this->package->getDevAutoload());
        self::assertSame([], $this->package->getIncludePaths());
        self::assertNull($this->package->getNotificationUrl());
        self::assertSame([], $this->package->getArchiveExcludes());
        self::assertSame([], $this->package->getScripts());
        self::assertSame([], $this->package->getRepositories());
        self::assertSame([], $this->package->getLicense());
        self::assertSame([], $this->package->getKeywords());
        self::assertSame([], $this->package->getAuthors());
        self::assertNull($this->package->getDescription());
        self::assertNull($this->package->getHomepage());
        self::assertSame([], $this->package->getSupport());
    }
}
