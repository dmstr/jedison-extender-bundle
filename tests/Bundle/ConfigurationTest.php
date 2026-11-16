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

    /** @return list<string> */
    private static function enabled(KernelInterface $kernel): array
    {
        return $kernel->getContainer()->getParameter('dmstr_jedison_extender.enabled_libraries');
    }
}
