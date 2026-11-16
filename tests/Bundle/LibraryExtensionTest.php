<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:06:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Bundle;

use Dmstr\JedisonExtender\Library;
use Dmstr\JedisonExtender\Tests\Support\KernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The shipped library extensions, checked against the REAL `jx/` directory.
 *
 * What these tests catch is a library that is listed but cannot load: a URL in
 * an `index.js` that points at a file which is missing, mistyped or of a type
 * the file route does not serve. The browser would report that as a failed
 * import in a host; here it fails before a release.
 */
final class LibraryExtensionTest extends KernelTestCase
{
    private const JX = __DIR__.'/../../jx';

    /** The file route's allow-list, restated: a change there must be a conscious one. */
    private const CONTENT_TYPES = [
        'js' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'png' => 'image/png',
    ];

    /** @return iterable<string, array{string}> */
    public static function libraries(): iterable
    {
        foreach (Library::ALL as $name) {
            yield $name => [$name];
        }
    }

    // ---- enabled ----------------------------------------------------------

    #[DataProvider('libraries')]
    public function testEnabledLibraryIsListedWithItsManifest(string $name): void
    {
        $manifest = self::manifest($name);
        $client = self::client([$name]);

        $entries = array_filter(
            self::catalogue($client)['extensions'],
            static fn (array $entry): bool => $name === $entry['name'],
        );

        self::assertCount(1, $entries, $name.' is not listed once');
        $entry = array_values($entries)[0];
        self::assertSame($manifest['version'], $entry['version']);
        self::assertSame('^1.22.1', $entry['jedison']);
        self::assertStringEndsWith('/ui-extensions/'.$name.'/index.js', $entry['module']);
    }

    #[DataProvider('libraries')]
    public function testManifestDeclaresTheLibraryItShips(string $name): void
    {
        $libraries = self::manifest($name)['libraries'] ?? null;

        self::assertIsArray($libraries);
        self::assertNotSame([], $libraries);

        foreach ($libraries as $library) {
            foreach (['name', 'version', 'license'] as $key) {
                self::assertArrayHasKey($key, $library);
                self::assertIsString($library[$key]);
                self::assertNotSame('', $library[$key], $name.': library '.$key.' is empty');
            }
        }
    }

    #[DataProvider('libraries')]
    public function testEnabledModuleIsServedAsJavaScript(string $name): void
    {
        $client = self::client([$name]);

        self::assertServed($client, $name.'/index.js');
    }

    /** Every relative URL in the module resolves through the file route. */
    #[DataProvider('libraries')]
    public function testEveryUrlTheModuleReferencesIsServed(string $name): void
    {
        $client = self::client([$name]);
        $references = self::references($name);

        self::assertNotSame([], $references);

        foreach ($references as $path) {
            self::assertServed($client, $path);
        }
    }

    #[DataProvider('libraries')]
    public function testModuleReferencesAScriptAndEveryStylesheetItShips(string $name): void
    {
        $references = self::references($name);
        $scripts = array_filter($references, static fn (string $path): bool => 'js' === pathinfo($path, \PATHINFO_EXTENSION) && str_contains($path, '/vendor/'));

        self::assertNotSame([], $scripts, $name.' references no vendored script');

        foreach (self::vendorFiles($name) as $file) {
            if ('css' === pathinfo($file, \PATHINFO_EXTENSION)) {
                self::assertContains($name.'/'.$file, $references, $name.' ships '.$file.' but index.js never loads it');
            }
        }
    }

    #[DataProvider('libraries')]
    public function testEveryServableVendorFileIsServedAndTheRestIsNot(string $name): void
    {
        $client = self::client([$name]);
        $files = self::vendorFiles($name);

        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $path = $name.'/'.$file;

            if (isset(self::CONTENT_TYPES[pathinfo($file, \PATHINFO_EXTENSION)])) {
                self::assertServed($client, $path);
            } else {
                $client->request('GET', '/ui-extensions/'.$path);
                self::assertSame(404, $client->getResponse()->getStatusCode(), $path.' is not on the allow-list');
            }
        }
    }

    // ---- not enabled ------------------------------------------------------

    #[DataProvider('libraries')]
    public function testDisabledLibraryIsNeitherListedNorServed(string $name): void
    {
        $client = self::client([]);

        self::assertNotContains($name, self::listedNames($client));

        $paths = [$name.'/index.js'];
        foreach (self::vendorFiles($name) as $file) {
            if (isset(self::CONTENT_TYPES[pathinfo($file, \PATHINFO_EXTENSION)])) {
                $paths[] = $name.'/'.$file;
            }
        }

        foreach ($paths as $path) {
            $client->request('GET', '/ui-extensions/'.$path);
            self::assertSame(404, $client->getResponse()->getStatusCode(), $path.' is served although '.$name.' is not enabled');
        }
    }

    // ---- completeness -----------------------------------------------------

    /** A directory without an entry in `Library::ALL` could never be enabled, and the other way round. */
    public function testLibraryListMatchesTheDirectories(): void
    {
        $directories = array_map('basename', glob(self::JX.'/*', \GLOB_ONLYDIR) ?: []);
        $directories = array_values(array_diff($directories, ['core', '_shared']));
        sort($directories);

        $expected = Library::ALL;
        sort($expected);

        self::assertSame($expected, $directories);
    }

    // ---- helpers ----------------------------------------------------------

    /** @param list<string> $editors */
    private static function client(array $editors): KernelBrowser
    {
        $client = new KernelBrowser(self::kernel($editors));
        $client->disableReboot();

        return $client;
    }

    /** @return array<string, mixed> */
    private static function manifest(string $name): array
    {
        return json_decode((string) file_get_contents(self::JX.'/'.$name.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Files under `vendor/`, relative to the extension directory.
     *
     * @return list<string>
     */
    private static function vendorFiles(string $name): array
    {
        $root = realpath(self::JX.'/'.$name.'/vendor');
        self::assertNotFalse($root, $name.' has no vendor/ directory');

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $files[] = 'vendor/'.substr($file->getPathname(), \strlen($root) + 1);
            }
        }
        sort($files);

        return $files;
    }

    /**
     * The files the module refers to by a relative literal, as paths below
     * `/ui-extensions/` (`ace/vendor/ace.js`, `_shared/load-library.js`).
     *
     * Two forms count: `new URL('./x', import.meta.url)` and the specifier of an
     * `import`. A directory (trailing slash) is a base for later URLs, not a
     * file, and is left out.
     *
     * @return list<string>
     */
    private static function references(string $name): array
    {
        $source = (string) file_get_contents(self::JX.'/'.$name.'/index.js');
        preg_match_all('/new URL\(\s*\'(\.{1,2}\/[^\']+)\'\s*,\s*import\.meta\.url/', $source, $urls);
        preg_match_all('/\bfrom\s+\'(\.{1,2}\/[^\']+)\'/', $source, $imports);

        $paths = [];
        foreach ([...$urls[1], ...$imports[1]] as $relative) {
            if (str_ends_with($relative, '/')) {
                continue;
            }

            $segments = [$name];
            foreach (explode('/', $relative) as $segment) {
                if ('..' === $segment) {
                    array_pop($segments);
                } elseif ('.' !== $segment) {
                    $segments[] = $segment;
                }
            }
            $paths[] = implode('/', $segments);
        }

        return array_values(array_unique($paths));
    }

    private static function assertServed(KernelBrowser $client, string $path): void
    {
        $client->request('GET', '/ui-extensions/'.$path);
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode(), $path.' is not served');
        self::assertSame(
            self::CONTENT_TYPES[pathinfo($path, \PATHINFO_EXTENSION)],
            $response->headers->get('Content-Type'),
            $path.' has the wrong content type',
        );
    }
}
