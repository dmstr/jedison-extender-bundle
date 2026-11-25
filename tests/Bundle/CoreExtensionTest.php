<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:50:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Bundle;

use Dmstr\JedisonExtender\Tests\Support\KernelTestCase;

/**
 * The real `jx/core`, served through a kernel with no configuration.
 */
final class CoreExtensionTest extends KernelTestCase
{
    private const INDEX = '/ui-extensions/core/index.js';

    public function testTheCatalogueEntryDeclaresItsLibraries(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__.'/../../jx/core/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('1.1.0', $manifest['version']);
        self::assertSame([['name' => 'awesomplete', 'version' => '1.1.7', 'license' => 'MIT']], $manifest['libraries']);
    }

    public function testTheEntryModuleLoadsAwesomplete(): void
    {
        $client = self::realClient();
        $client->request('GET', self::INDEX);

        self::assertMatchesRegularExpression(
            "/await\s+ensureGlobal\(\s*'Awesomplete'/",
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testAwesompleteIsServedWithTheRightTypes(): void
    {
        $client = self::realClient();

        foreach ([
            self::INDEX => 'text/javascript; charset=UTF-8',
            '/ui-extensions/core/vendor/awesomplete.min.js' => 'text/javascript; charset=UTF-8',
            '/ui-extensions/core/vendor/awesomplete.css' => 'text/css; charset=UTF-8',
        ] as $url => $type) {
            $client->request('GET', $url);

            self::assertSame(200, $client->getResponse()->getStatusCode(), $url);
            self::assertSame($type, $client->getResponse()->headers->get('Content-Type'), $url);
            self::assertNotSame('', (string) $client->getResponse()->getContent(), $url);
        }
    }

    public function testTheAwesompleteLicenseIsVendoredButNotServed(): void
    {
        self::assertFileExists(__DIR__.'/../../jx/core/vendor/LICENSE');

        $client = self::realClient();
        $client->request('GET', '/ui-extensions/core/vendor/LICENSE');
        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    /**
     * Every relative URL the entry module names has to resolve through the
     * kernel, the way a browser resolves it against the module URL. A moved or
     * misspelled file would otherwise surface as a broken form.
     */
    public function testEveryRelativeUrlInTheEntryModuleResolves(): void
    {
        $client = self::realClient();
        $client->request('GET', self::INDEX);
        $source = (string) $client->getResponse()->getContent();

        preg_match_all("/'(\.{1,2}\/[^']+)'/", $source, $matches);
        $literals = array_unique($matches[1]);

        self::assertNotSame([], $literals, 'the entry module names no relative URL — the pattern is stale');
        self::assertContains('./vendor/awesomplete.min.js', $literals);

        foreach ($literals as $literal) {
            $url = self::resolve(self::INDEX, $literal);
            $client->request('GET', $url);

            self::assertSame(200, $client->getResponse()->getStatusCode(), $literal.' -> '.$url);
        }
    }

    /** Resolves a relative reference against a module path, like a browser does. */
    private static function resolve(string $base, string $relative): string
    {
        $segments = explode('/', \dirname($base));

        foreach (explode('/', $relative) as $segment) {
            if ('..' === $segment) {
                array_pop($segments);
            } elseif ('.' !== $segment) {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }
}
