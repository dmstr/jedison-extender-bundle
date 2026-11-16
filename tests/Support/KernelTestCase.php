<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:20:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Builds a browser against a TestKernel with the given configuration.
 */
abstract class KernelTestCase extends TestCase
{
    /** The fixture standing in for the bundle's own `jx/`. */
    protected const BUNDLE_FIXTURE = TestKernel::FIXTURES.'/bundle';

    /** An application directory that ships an `ace` of its own, shadowing the disabled bundle one. */
    protected const OVERRIDE_FIXTURE = TestKernel::FIXTURES.'/app-override';

    /** An application directory with its own extensions. */
    protected const APP_FIXTURE = TestKernel::FIXTURES.'/app';

    /**
     * @param list<string> $editors
     * @param list<string> $paths
     */
    protected static function kernel(array $editors = [], array $paths = [], ?string $bundleFixture = null): TestKernel
    {
        $kernel = new TestKernel($editors, $paths, $bundleFixture);
        $kernel->boot();

        return $kernel;
    }

    /**
     * A client against the fixture bundle directory plus the application fixture.
     *
     * @param list<string> $editors
     * @param list<string> $paths   application paths, after the bundle's own
     */
    protected static function fixtureClient(array $editors = [], array $paths = [self::APP_FIXTURE]): KernelBrowser
    {
        return new KernelBrowser(self::kernel($editors, $paths, self::BUNDLE_FIXTURE));
    }

    /** A client against the real bundle directory, no configuration. */
    protected static function realClient(): KernelBrowser
    {
        return new KernelBrowser(self::kernel());
    }

    /** @return array<string, mixed> decoded catalogue */
    protected static function catalogue(KernelBrowser $client): array
    {
        $client->request('GET', '/ui-extensions');

        return json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    protected static function listedNames(KernelBrowser $client): array
    {
        return array_column(self::catalogue($client)['extensions'], 'name');
    }
}
