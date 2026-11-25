<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:55:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Bundle;

use Dmstr\JedisonExtender\Library;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards over the modules the bundle ships itself: `jx/<extension>/*.js` and
 * `jx/_shared/*.js`. Vendored third-party code (`vendor/` subdirectories) is
 * not ours to police and is out of reach of the glob by construction.
 *
 * Discovered, not listed: enumerating by hand would leave the next editor
 * unguarded on the day it is added, which is the day its wording is written.
 */
final class ModuleSourceGuardTest extends TestCase
{
    /** German words that survive the umlaut check by being written ae/oe/ue. */
    private const TRANSLITERATIONS = [
        'waehlen', 'gewaehlt', 'auswahl', 'zuerst', 'eintraege', 'gekuerzt',
        'uebersprungen', 'zurueck', 'bitte', 'einschraenken',
    ];

    /** Names of applications and their front ends; the bundle is generic. */
    private const CONSUMERS = ['fogu', 'mlr/', 'za7', 'hrzg', 'vue-'];

    /**
     * Nothing shipped may carry a word of any language but English.
     *
     * A guard against a silent failure: a hardcoded foreign string works
     * perfectly in the application it was written for and stays invisible until
     * a second consumer renders the form. Wording belongs to the host, which
     * passes it in as labels.
     *
     * Checked in both directions, because a hardcoded string can dodge either
     * one: non-ASCII LETTERS, and the ASCII transliterations a language falls
     * back to when someone avoids umlauts.
     */
    #[DataProvider('bundleModules')]
    public function testModulesCarryNoLanguageButEnglish(string $path): void
    {
        $source = (string) file_get_contents($path);

        // Letters, not characters: typography (em dash, ellipsis, angle quotes,
        // carets, the multiplication sign) is legitimate and is not a letter.
        // Umlauts and accents are, so the check needs no whitelist to maintain.
        preg_match_all('/[^\x00-\x7F]/u', $source, $matches);
        $letters = array_values(array_unique(array_filter(
            $matches[0],
            static fn (string $character): bool => 1 === preg_match('/\p{L}/u', $character),
        )));

        self::assertSame([], $letters, basename($path).' contains non-ASCII letters: '.implode(' ', $letters));

        foreach (self::TRANSLITERATIONS as $word) {
            self::assertStringNotContainsStringIgnoringCase(
                $word,
                $source,
                basename($path).' reads like German ("'.$word.'") - wording comes from the host',
            );
        }
    }

    /**
     * A comment mentioning a consumer looks harmless and is the first step of
     * the coupling the bundle exists to prevent: the next reader treats the
     * example as the contract. Describe the behaviour instead of the owner.
     */
    #[DataProvider('bundleModules')]
    public function testModulesNameNoConsumer(string $path): void
    {
        $source = (string) file_get_contents($path);

        foreach (self::CONSUMERS as $needle) {
            self::assertStringNotContainsStringIgnoringCase(
                $needle,
                $source,
                basename($path).' names a consumer ("'.$needle.'") - the bundle must not know who uses it',
            );
        }
    }

    /** The guard itself must see the shipped modules, or it passes by checking nothing. */
    public function testTheProviderFindsTheShippedModules(): void
    {
        $names = array_map(static fn (array $row): string => basename(\dirname($row[0])).'/'.basename($row[0]), [...self::bundleModules()]);

        self::assertContains('core/index.js', $names);
        self::assertContains('_shared/load-library.js', $names);
        self::assertSame([], array_filter($names, static fn (string $name): bool => str_contains($name, 'vendor')));
    }

    /**
     * The entry module of every extension is a factory and nothing else: the
     * host calls its default `register()`, and the catalogue takes name,
     * version and Jedison range from `manifest.json`. A second copy of those in
     * the module would drift from the manifest without anyone noticing.
     */
    #[DataProvider('entryModules')]
    public function testEntryModuleExportsADefaultRegisterAndNoManifest(string $path): void
    {
        $source = (string) file_get_contents($path);
        $name = basename(\dirname($path));

        self::assertMatchesRegularExpression(
            '/export\s+default\s+function\s+register\s*\(/',
            $source,
            $name.'/index.js has no default export of register()',
        );
        self::assertDoesNotMatchRegularExpression(
            '/export\s+(const|let|var)\s+(name|version|jedison)\b/',
            $source,
            $name.'/index.js exports manifest data - it belongs in manifest.json',
        );
    }

    /** The contract test must see every shipped extension, or it passes by checking nothing. */
    public function testTheEntryModuleProviderFindsEveryShippedExtension(): void
    {
        $names = array_keys([...self::entryModules()]);
        sort($names);

        $expected = ['core', ...Library::ALL];
        sort($expected);

        self::assertSame($expected, $names);
    }

    /** @return iterable<string, array{string}> */
    public static function entryModules(): iterable
    {
        foreach (glob(__DIR__.'/../../jx/*/manifest.json') ?: [] as $manifest) {
            yield basename(\dirname($manifest)) => [\dirname($manifest).'/index.js'];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function bundleModules(): iterable
    {
        foreach (glob(__DIR__.'/../../jx/*/*.js') ?: [] as $path) {
            yield basename(\dirname($path)).'/'.basename($path) => [$path];
        }
    }
}
