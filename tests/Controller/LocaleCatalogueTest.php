<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:45:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Controller;

use Dmstr\JedisonExtender\Tests\Support\KernelTestCase;

/**
 * The catalogue advertises configured locales as a query on the module URL of
 * the library concerned, and nothing else changes.
 */
final class LocaleCatalogueTest extends KernelTestCase
{
    public function testASingleLocaleIsAppendedAsQuery(): void
    {
        $module = self::moduleOf('flatpickr', ['flatpickr'], ['flatpickr' => ['de']]);

        self::assertStringEndsWith('/flatpickr/index.js?locales=de', $module);
        self::assertSame('de', self::localesOf($module));
    }

    public function testLocalesKeepTheirConfiguredOrder(): void
    {
        $module = self::moduleOf('flatpickr', ['flatpickr'], ['flatpickr' => ['de', 'fr']]);

        self::assertStringEndsWith('index.js?locales=de,fr', $module);
    }

    public function testDuplicateLocalesCollapse(): void
    {
        $module = self::moduleOf('flatpickr', ['flatpickr'], ['flatpickr' => ['de', 'de']]);

        self::assertSame('de', self::localesOf($module));
    }

    public function testOnlyTheConfiguredLibraryCarriesAQuery(): void
    {
        $client = self::realClient(['flatpickr', 'imask'], ['flatpickr' => ['de']]);
        $modules = [];
        foreach (self::catalogue($client)['extensions'] as $entry) {
            $modules[$entry['name']] = $entry['module'];
        }

        self::assertArrayHasKey('imask', $modules);
        self::assertStringContainsString('?locales=de', $modules['flatpickr']);
        self::assertStringNotContainsString('?', $modules['core']);
        self::assertStringNotContainsString('?', $modules['imask']);
    }

    public function testWithoutLocalesTheEntryIsUnchanged(): void
    {
        $plain = self::entryOf('flatpickr', ['flatpickr'], []);

        self::assertStringNotContainsString('?', $plain['module']);
        self::assertSame($plain, self::entryOf('flatpickr', ['flatpickr'], ['flatpickr' => []]));
    }

    public function testEveryAdvertisedLocaleIsDelivered(): void
    {
        $client = self::realClient(['flatpickr'], ['flatpickr' => ['de', 'fr']]);
        $module = self::entryFrom($client, 'flatpickr')['module'];

        foreach (explode(',', self::localesOf($module)) as $locale) {
            $client->request('GET', "/ui-extensions/flatpickr/vendor/l10n/$locale.js");
            self::assertSame(200, $client->getResponse()->getStatusCode(), $locale);
            self::assertSame('text/javascript; charset=UTF-8', $client->getResponse()->headers->get('Content-Type'), $locale);
        }
    }

    public function testTheQueryDoesNotChangeContentOrEtagAndRevalidates(): void
    {
        $client = self::realClient(['flatpickr'], ['flatpickr' => ['de']]);

        $client->request('GET', '/ui-extensions/flatpickr/index.js');
        $plain = $client->getResponse();
        $etag = $plain->headers->get('ETag');
        self::assertNotNull($etag);

        $client->request('GET', '/ui-extensions/flatpickr/index.js?locales=de');
        $withQuery = $client->getResponse();
        self::assertSame(200, $withQuery->getStatusCode());
        self::assertSame($plain->getContent(), $withQuery->getContent());
        self::assertSame($etag, $withQuery->headers->get('ETag'));

        $client->request('GET', '/ui-extensions/flatpickr/index.js?locales=de', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(304, $client->getResponse()->getStatusCode());
    }

    public function testDifferentLocalesNeverShareACachedKernel(): void
    {
        $de = self::moduleOf('flatpickr', ['flatpickr'], ['flatpickr' => ['de']]);
        $fr = self::moduleOf('flatpickr', ['flatpickr'], ['flatpickr' => ['fr']]);

        self::assertNotSame($de, $fr);
        self::assertSame('fr', self::localesOf($fr));
    }

    /**
     * @param list<string>                $editors
     * @param array<string, list<string>> $locales
     */
    private static function moduleOf(string $name, array $editors, array $locales): string
    {
        return self::entryOf($name, $editors, $locales)['module'];
    }

    /**
     * @param list<string>                $editors
     * @param array<string, list<string>> $locales
     *
     * @return array<string, mixed>
     */
    private static function entryOf(string $name, array $editors, array $locales): array
    {
        return self::entryFrom(self::realClient($editors, $locales), $name);
    }

    /** @return array<string, mixed> */
    private static function entryFrom(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $name): array
    {
        foreach (self::catalogue($client)['extensions'] as $entry) {
            if ($name === $entry['name']) {
                return $entry;
            }
        }

        self::fail("No catalogue entry $name");
    }

    private static function localesOf(string $module): string
    {
        parse_str((string) parse_url($module, \PHP_URL_QUERY), $query);

        return (string) ($query['locales'] ?? '');
    }
}
