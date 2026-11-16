<?php
// file generated with AI assistance: Claude Code - 2026-08-26

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Service;

use Dmstr\JedisonExtender\Library;

/**
 * Finds extensions across the configured directories.
 *
 * An extension is a subdirectory holding a `manifest.json`. Several search paths
 * exist so that the bundle can ship generic editors while the application adds
 * its own, without either having to know about the other.
 *
 * Order matters: the first path wins on a name clash. Since the bundle's own
 * directory comes first, an application cannot accidentally shadow a shipped
 * extension by picking the same name — it would have to be a deliberate change
 * to the configured order.
 *
 * ONE FILTER: in the bundle's own directory a library extension (a name from
 * `Library::ALL`) that is not enabled is skipped everywhere, as if it did not
 * exist. That includes the name-clash rule: with the bundle's `ace` disabled, an
 * application's own `ace` from a later path is found. Nothing else is filtered —
 * `core`, `_shared` and every extension from an application path stay as they are.
 */
final class ExtensionLocator
{
    private const MANIFEST = 'manifest.json';

    private readonly string $bundleDirectory;

    /**
     * Both parameters are optional so a consuming application can build the
     * locator by hand (`new ExtensionLocator()`): no library is enabled then.
     *
     * @param list<string> $enabledLibraries names from `Library::ALL`
     * @param string|null  $bundleDirectory  the directory the filter applies
     *                                       to; defaults to this bundle's `jx/`,
     *                                       injectable so tests can use fixtures
     */
    public function __construct(
        private readonly array $enabledLibraries = [],
        ?string $bundleDirectory = null,
    ) {
        $directory = $bundleDirectory ?? \dirname(__DIR__, 2).'/jx';
        $this->bundleDirectory = false !== realpath($directory) ? realpath($directory) : $directory;
    }

    /**
     * Every extension holding a readable manifest, keyed by name.
     *
     * A directory without a manifest is skipped rather than guessed at: the
     * manifest is what tells a consumer which Jedison version the code expects,
     * and shipping code without that claim is worse than not shipping it.
     *
     * @param list<string> $paths
     * @return array<string, array<string, mixed>>
     */
    public function manifests(array $paths): array
    {
        $manifests = [];

        foreach ($paths as $base) {
            foreach (glob(rtrim($base, '/').'/*/'.self::MANIFEST) ?: [] as $file) {
                $name = basename(\dirname($file));

                if ($this->isDisabled($base, $name)) {
                    continue;
                }

                if (isset($manifests[$name])) {
                    continue; // first path wins
                }

                $data = json_decode((string) file_get_contents($file), true);

                if (\is_array($data)) {
                    $manifests[$name] = $data;
                }
            }
        }

        ksort($manifests);

        return $manifests;
    }

    /**
     * Absolute path of a file inside an extension, or null if it does not exist
     * or would escape its directory.
     *
     * The realpath comparison is the belt to the route requirement's braces: it
     * guarantees the resolved file really sits below one of the search paths,
     * whatever a future change to those requirements might let through.
     *
     * @param list<string> $paths
     */
    public function resolve(array $paths, string $name, string $file): ?string
    {
        foreach ($paths as $base) {
            if ($this->isDisabled($base, $name)) {
                continue;
            }

            // The extension directory, not the search path, is the boundary: a
            // file may not reach into a sibling extension either.
            $root = realpath(rtrim($base, '/').'/'.$name);
            $path = realpath(rtrim($base, '/').'/'.$name.'/'.$file);

            if (false === $root || false === $path || !is_file($path)) {
                continue;
            }

            if (str_starts_with($path, $root.\DIRECTORY_SEPARATOR)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Whether $name in $base is a library extension that was not switched on.
     * Compared by realpath, so a symlinked or differently spelled path to the
     * bundle directory is still recognised.
     */
    private function isDisabled(string $base, string $name): bool
    {
        return \in_array($name, Library::ALL, true)
            && !\in_array($name, $this->enabledLibraries, true)
            && realpath($base) === $this->bundleDirectory;
    }
}
