<?php
// file generated with AI assistance: Claude Code - 2026-08-26

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Service;

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
 */
final class ExtensionLocator
{
    private const MANIFEST = 'manifest.json';

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
            $root = realpath($base);
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
}
