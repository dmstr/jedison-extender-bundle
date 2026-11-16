<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:30:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Controller;

use Dmstr\JedisonExtender\Library;
use Dmstr\JedisonExtender\Tests\Support\KernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Catalogue and library filter, against fixture directories: a test asserting
 * on the real editors would break whenever one is added.
 */
final class CatalogueTest extends KernelTestCase
{
    public function testCoreIsListedWithoutAnyConfiguration(): void
    {
        self::assertContains('core', self::listedNames(self::realClient()));
    }

    public function testEntriesCarryTheDocumentedShape(): void
    {
        $client = self::fixtureClient();
        $entries = self::catalogue($client)['extensions'];
        $core = array_values(array_filter($entries, static fn (array $e): bool => 'core' === $e['name']))[0];

        self::assertSame(['name', 'version', 'jedison', 'description', 'module'], array_keys($core));
        self::assertSame('http://localhost/ui-extensions/core/index.js', $core['module']);
    }

    #[DataProvider('libraries')]
    public function testADisabledLibraryIsAbsent(string $library): void
    {
        self::assertNotContains($library, self::listedNames(self::fixtureClient()));
    }

    #[DataProvider('libraries')]
    public function testAnEnabledLibraryIsPresent(string $library): void
    {
        self::assertContains($library, self::listedNames(self::fixtureClient([$library])));
    }

    #[DataProvider('libraries')]
    public function testEnablingOneLibraryEnablesNoOther(string $library): void
    {
        $others = array_diff(Library::ALL, [$library]);

        self::assertSame([], array_values(array_intersect($others, self::listedNames(self::fixtureClient([$library])))));
    }

    #[DataProvider('libraries')]
    public function testFilesOfADisabledLibraryAre404(string $library): void
    {
        $client = self::fixtureClient();

        foreach (['index.js', 'vendor/library.js'] as $file) {
            $client->request('GET', "/ui-extensions/$library/$file");
            self::assertSame(404, $client->getResponse()->getStatusCode(), "$library/$file");
        }
    }

    #[DataProvider('libraries')]
    public function testFilesOfAnEnabledLibraryAreServed(string $library): void
    {
        $client = self::fixtureClient([$library]);

        foreach (['index.js', 'vendor/library.js'] as $file) {
            $client->request('GET', "/ui-extensions/$library/$file");
            self::assertSame(200, $client->getResponse()->getStatusCode(), "$library/$file");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function libraries(): iterable
    {
        foreach (Library::ALL as $library) {
            yield $library => [$library];
        }
    }

    public function testAnApplicationExtensionWithALibraryNameWinsWhileTheBundleOneIsDisabled(): void
    {
        $client = self::fixtureClient([], [self::APP_FIXTURE, self::OVERRIDE_FIXTURE]);

        $ace = array_values(array_filter(
            self::catalogue($client)['extensions'],
            static fn (array $e): bool => 'ace' === $e['name'],
        ));
        self::assertCount(1, $ace);
        self::assertSame('9.0.0', $ace[0]['version']);

        $client->request('GET', '/ui-extensions/ace/vendor/library.js');
        self::assertStringContainsString('"ace"', (string) $client->getResponse()->getContent());
    }

    public function testTheBundleExtensionWinsAgainOnceEnabled(): void
    {
        $client = self::fixtureClient(['ace'], [self::APP_FIXTURE, self::OVERRIDE_FIXTURE]);

        $ace = array_values(array_filter(
            self::catalogue($client)['extensions'],
            static fn (array $e): bool => 'ace' === $e['name'],
        ));
        self::assertSame('1.0.0', $ace[0]['version']);
    }

    public function testApplicationExtensionsAreUnaffectedByTheLibrarySwitch(): void
    {
        foreach ([[], Library::ALL] as $editors) {
            self::assertContains('local', self::listedNames(self::fixtureClient($editors)));
        }
    }

    public function testSharedCodeIsServedButNotListed(): void
    {
        $client = self::fixtureClient();

        self::assertNotContains('_shared', self::listedNames($client));
        foreach (['_shared/shared.js', '_shared/app-shared.js'] as $file) {
            $client->request('GET', "/ui-extensions/$file");
            self::assertSame(200, $client->getResponse()->getStatusCode(), $file);
        }
    }

    public function testADirectoryWithoutManifestIsServedButNotListed(): void
    {
        $client = self::fixtureClient();

        self::assertNotContains('nomanifest', self::listedNames($client));
        $client->request('GET', '/ui-extensions/nomanifest/bare.js');
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testCoreAndApplicationExtensionsAreNeverFiltered(): void
    {
        self::assertSame(['core', 'local'], array_values(array_diff(self::listedNames(self::fixtureClient()), ['ace'])));
    }
}
