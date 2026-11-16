<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:57:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Service;

use Dmstr\JedisonExtender\Service\ExtensionLocator;
use Dmstr\JedisonExtender\Tests\Support\TestKernel;
use PHPUnit\Framework\TestCase;

final class ExtensionLocatorTest extends TestCase
{
    private const BUNDLE = TestKernel::FIXTURES.'/bundle';
    private const APP = TestKernel::FIXTURES.'/app-override';

    public function testItIsConstructibleWithoutArguments(): void
    {
        self::assertSame([], (new ExtensionLocator())->manifests([self::APP.'/../nonexistent']));
    }

    public function testTheFilterOnlyAppliesToTheBundleDirectory(): void
    {
        // Without a bundle override the real jx/ is the filtered directory, so
        // the fixture directory is an ordinary application path.
        $names = array_keys((new ExtensionLocator())->manifests([self::BUNDLE]));

        self::assertSame(['ace', 'choices', 'core', 'flatpickr', 'imask', 'jodit', 'quill', 'raty', 'tom-select'], $names);
    }

    public function testManifestsFiltersDisabledLibrariesOfTheBundleDirectory(): void
    {
        $locator = new ExtensionLocator(['ace'], self::BUNDLE);

        self::assertSame(['ace', 'core'], array_keys($locator->manifests([self::BUNDLE])));
    }

    public function testResolveFiltersTheSameWay(): void
    {
        $locator = new ExtensionLocator(['ace'], self::BUNDLE);

        self::assertNotNull($locator->resolve([self::BUNDLE], 'ace', 'index.js'));
        self::assertNotNull($locator->resolve([self::BUNDLE], 'core', 'index.js'));
        self::assertNull($locator->resolve([self::BUNDLE], 'flatpickr', 'index.js'));
    }

    public function testTheBundleDirectoryIsComparedByRealpath(): void
    {
        $locator = new ExtensionLocator([], self::BUNDLE);

        // Same directory, spelled differently: still the filtered one.
        self::assertSame(['core'], array_keys($locator->manifests([self::BUNDLE.'/../bundle/'])));
    }

    public function testASameNamedExtensionOfALaterPathIsFoundWhileTheBundleOneIsDisabled(): void
    {
        $locator = new ExtensionLocator([], self::BUNDLE);
        $manifests = $locator->manifests([self::BUNDLE, self::APP]);

        self::assertSame('9.0.0', $manifests['ace']['version']);
        self::assertStringEndsWith('/app-override/ace/index.js', (string) $locator->resolve([self::BUNDLE, self::APP], 'ace', 'index.js'));
    }

    public function testFirstPathStillWinsOtherwise(): void
    {
        $locator = new ExtensionLocator(['ace'], self::BUNDLE);

        self::assertSame('1.0.0', $locator->manifests([self::BUNDLE, self::APP])['ace']['version']);
        self::assertStringEndsWith('/bundle/ace/index.js', (string) $locator->resolve([self::BUNDLE, self::APP], 'ace', 'index.js'));
    }
}
