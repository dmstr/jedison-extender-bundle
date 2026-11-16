<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:30:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Bundle;

use Dmstr\JedisonExtender\Library;
use Dmstr\JedisonExtender\Tests\Support\KernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * `core.editors` is validated when the configuration is loaded, so a typo
 * fails the container build instead of silently serving nothing.
 */
final class ConfigurationTest extends KernelTestCase
{
    public function testTheLibraryListIsTheDocumentedOne(): void
    {
        self::assertSame(
            ['ace', 'choices', 'flatpickr', 'imask', 'jodit', 'quill', 'raty', 'tom-select'],
            Library::ALL,
        );
    }

    public function testNoLibraryIsEnabledByDefault(): void
    {
        self::assertSame([], self::enabled(self::kernel()));
    }

    public function testAValidListIsAccepted(): void
    {
        self::assertSame(['flatpickr', 'imask'], self::enabled(self::kernel(['flatpickr', 'imask'])));
    }

    public function testEveryKnownLibraryIsAccepted(): void
    {
        self::assertSame(Library::ALL, self::enabled(self::kernel(Library::ALL)));
    }

    #[DataProvider('invalidNames')]
    public function testAnUnknownNameFailsWhenTheConfigurationIsLoaded(string $name, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches($message);

        self::kernel([$name]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidNames(): iterable
    {
        yield 'unknown' => ['datepicker', '/Unknown library "datepicker"/'];
        yield 'wrong case' => ['Flatpickr', '/Unknown library "Flatpickr"/'];
        yield 'awesomplete is part of core' => ['awesomplete', '/part of core and always enabled/'];
    }

    public function testDuplicateNamesAreEnabledOnce(): void
    {
        self::assertSame(['ace'], self::enabled(self::kernel(['ace', 'ace'])));
    }

    public function testNoLocalesAreConfiguredByDefault(): void
    {
        self::assertSame([], self::locales(self::kernel(['flatpickr'])));
        self::assertSame([], self::locales(self::kernel(['flatpickr'], locales: ['flatpickr' => []])));
    }

    /** @param array<string, list<string>> $expected */
    #[DataProvider('validLocales')]
    public function testValidLocalesAreExposedAsParameter(array $configured, array $expected): void
    {
        self::assertSame($expected, self::locales(self::kernel(['flatpickr'], locales: $configured)));
    }

    /** @return iterable<string, array{array<string, list<string>>, array<string, list<string>>}> */
    public static function validLocales(): iterable
    {
        yield 'one' => [['flatpickr' => ['de']], ['flatpickr' => ['de']]];
        yield 'order kept' => [['flatpickr' => ['de', 'fr']], ['flatpickr' => ['de', 'fr']]];
        yield 'duplicate once' => [['flatpickr' => ['de', 'de']], ['flatpickr' => ['de']]];
    }

    /** @param array<string, list<string>> $locales */
    #[DataProvider('invalidLocales')]
    public function testInvalidLocalesFailWhenTheConfigurationIsLoaded(array $editors, array $locales, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches($message);

        self::kernel($editors, locales: $locales);
    }

    /** @return iterable<string, array{list<string>, array<string, list<string>>, string}> */
    public static function invalidLocales(): iterable
    {
        $fp = ['flatpickr'];
        yield 'no such file' => [$fp, ['flatpickr' => ['xx']], '/unknown locale "xx"/'];
        yield 'upper case' => [$fp, ['flatpickr' => ['De']], '/"De" is not a locale name/'];
        yield 'path traversal' => [$fp, ['flatpickr' => ['../de']], '/"\.\.\/de" is not a locale name/'];
        yield 'extension' => [$fp, ['flatpickr' => ['de.js']], '/"de\.js" is not a locale name/'];
        yield 'collection file' => [$fp, ['flatpickr' => ['index']], '/"index" is not a locale/'];
        yield 'english default' => [$fp, ['flatpickr' => ['default']], '/"default" is not a locale/'];
        yield 'registers nothing' => [$fp, ['flatpickr' => ['ms']], '/"ms\.js" does not register under its own name/'];
        yield 'registers sr' => [$fp, ['flatpickr' => ['sr-cyr']], '/"sr-cyr\.js" does not register/'];
        yield 'registers hr' => [$fp, ['flatpickr' => ['ga']], '/"ga\.js" does not register/'];
        yield 'registers ar' => [$fp, ['flatpickr' => ['ar-dz']], '/"ar-dz\.js" does not register/'];
        yield 'registers zh_tw' => [$fp, ['flatpickr' => ['zh-tw']], '/"zh-tw\.js" does not register/'];
        yield 'library without l10n' => [['imask'], ['imask' => ['de']], '/library "imask" ships no locale files/'];
        yield 'library not enabled' => [[], ['flatpickr' => ['de']], '/"flatpickr", which is not enabled/'];
        yield 'unknown library' => [$fp, ['nope' => ['de']], '/unknown library "nope"/'];
        yield 'dashed key as written' => [['tom-select'], ['tom-select' => ['de']], '/library "tom-select" ships no locale files/'];
    }

    public function testTwoKernelsWithDifferentLocalesDoNotShareAContainer(): void
    {
        $de = self::kernel(['flatpickr'], locales: ['flatpickr' => ['de']]);
        $fr = self::kernel(['flatpickr'], locales: ['flatpickr' => ['fr']]);

        self::assertSame(['flatpickr' => ['de']], self::locales($de));
        self::assertSame(['flatpickr' => ['fr']], self::locales($fr));
    }

    /** @return array<string, list<string>> */
    private static function locales(KernelInterface $kernel): array
    {
        return $kernel->getContainer()->getParameter('dmstr_jedison_extender.library_locales');
    }

    /** @return list<string> */
    private static function enabled(KernelInterface $kernel): array
    {
        return $kernel->getContainer()->getParameter('dmstr_jedison_extender.enabled_libraries');
    }
}
