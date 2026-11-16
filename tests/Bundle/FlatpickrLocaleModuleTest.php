<?php
// file generated with AI assistance: Claude Code - 2026-11-16 17:10:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Bundle;

use PHPUnit\Framework\TestCase;

/**
 * Source-pattern guards for the locale loading of `jx/flatpickr/index.js`.
 *
 * The module runs in a browser, so these tests pin the order and the shape of
 * the code instead of executing it: the locales may only be loaded after the
 * library global exists, a registered locale is skipped, and a locale that
 * did not register is an error.
 */
final class FlatpickrLocaleModuleTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__.'/../../jx/flatpickr/index.js');
    }

    public function testLocaleBaseIsALiteralUrl(): void
    {
        self::assertStringContainsString("new URL('./vendor/l10n/', import.meta.url)", $this->source);
    }

    public function testParameterIsReadFromTheModuleUrl(): void
    {
        self::assertMatchesRegularExpression(
            "/new URL\\(import\\.meta\\.url\\)\\.searchParams\\.get\\('locales'\\)/",
            $this->source,
        );
    }

    public function testNamePatternIsChecked(): void
    {
        self::assertStringContainsString('^[a-z][a-z0-9_-]*$', $this->source);
    }

    public function testLoadComesAfterTheLibraryGlobal(): void
    {
        self::assertSame(1, preg_match("/await ensureGlobal\\(\\s*'flatpickr'/", $this->source, $match, PREG_OFFSET_CAPTURE));
        $ensure = $match[0][1];
        $load = strpos($this->source, 'loadScript(');

        self::assertNotFalse($load);
        self::assertGreaterThan($ensure, $load);
    }

    public function testRegisteredLocaleIsSkippedBeforeTheLoad(): void
    {
        $skip = strpos($this->source, 'l10ns');
        $load = strpos($this->source, 'await loadScript(');

        self::assertNotFalse($skip);
        self::assertNotFalse($load);
        self::assertLessThan($load, $skip);
        self::assertMatchesRegularExpression('/if \\(globalThis\\.flatpickr\\.l10ns[^\\n]*\\{\\s*continue/s', $this->source);
    }

    public function testFailedRegistrationThrowsAfterTheLoad(): void
    {
        $load = strpos($this->source, 'await loadScript(');

        self::assertNotFalse($load);
        self::assertStringContainsString('throw', substr($this->source, $load));
    }
}
