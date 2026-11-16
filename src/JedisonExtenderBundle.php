<?php
// file generated with AI assistance: Claude Code - 2026-08-26

declare(strict_types=1);

namespace Dmstr\JedisonExtender;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Serves Jedison form editors over HTTP so that every front end talking to this
 * API renders its forms with the same widgets, from one source.
 *
 * WHY AN API SHIPS EDITOR CODE: the form schemas live in the backend, and the
 * widgets able to render them belong with them. Without this, a shared editor
 * ends up copied into every front end — and the copies drift. That is not
 * hypothetical: it is exactly what happened to the IRI-reference editor across
 * two applications before this bundle existed.
 *
 * THE CONTRACT — a default factory, not an import:
 *
 *     const register = (await import(entry.module)).default
 *     const { editors, constraints } = register(Jedison, { fetchCollection })
 *
 * `Jedison` must be the HOST's instance: a module importing the library itself
 * would extend a second copy and get a different `Editor` base class than the
 * one the host's `ui-resolver` checks against, so none of its editors would ever
 * resolve. `host.fetchCollection` is the host's AUTHENTICATED client — editors
 * read protected collections, which answer 401 without a token, and only the
 * host has one.
 *
 * ROUTES ARE PUBLIC AND SIT OUTSIDE ANY API FIREWALL, by necessity: a dynamic
 * `import()` cannot carry an `Authorization` header, so behind a JWT firewall
 * the module would be unreachable for every front end. Consequently a bundled
 * module must never contain anything tenant- or user-dependent; that stays
 * behind the authenticated endpoints the editors then call WITH a token.
 *
 * WHAT THIS BUNDLE SHIPS — and what it deliberately does not: `jx/core` holds
 * editors any API can use, `jx/_shared` the parts they are built from. Editors
 * that only make sense for one domain belong in that application, as its own
 * extension. The line matters because everything here is loaded by front ends
 * the bundle knows nothing about.
 *
 * `jx/_shared` is public API, not an internal: an application's editors import
 * from it over HTTP (`'../_shared/collection-source.js'`, resolved by the
 * browser against the module URL). Renaming or moving anything in there breaks
 * consumers that never mentioned this bundle in their own code.
 *
 * Self-wiring: the bundle ships its own service definitions and routes. A
 * consuming application adds the routing import and, if it wants to contribute
 * its own editors, a `paths` entry:
 *
 *     dmstr_jedison_extender:
 *         paths:
 *             - '%kernel.project_dir%/jx'
 *         core:
 *             editors: [flatpickr, imask]
 *             locales:
 *                 flatpickr: [de]
 *
 * `core.editors` switches on the third-party libraries the Jedison plugin
 * editors need (see `Library::ALL`). Each is its own extension under `jx/` and
 * only the enabled ones are listed in the catalogue or served; Awesomplete
 * belongs to `core` itself and is always on. `core.locales` lists, per enabled
 * library that ships translations (today flatpickr), the locales its module
 * loads; they travel in the catalogue's `module` URL as `?locales=de,fr`, and
 * invalid ones fail when the container is built.
 */
final class JedisonExtenderBundle extends AbstractBundle
{
    protected string $extensionAlias = 'dmstr_jedison_extender';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('paths')
                    ->info('Additional directories holding extensions; each subdirectory with a manifest.json is one extension.')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('core')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('editors')
                            ->info('Libraries to activate, each delivered as its own extension: '.implode(', ', Library::ALL).'. Awesomplete is part of core and always enabled.')
                            ->defaultValue([])
                            ->scalarPrototype()
                                ->validate()
                                    ->ifTrue(static fn (mixed $name): bool => 'awesomplete' === $name)
                                    ->thenInvalid('Awesomplete is part of core and always enabled; it must not be listed.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || !\in_array($name, Library::ALL, true))
                                    ->thenInvalid('Unknown library %s. Available: '.implode(', ', Library::ALL).'.')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('locales')
                            ->info('Locale files to load per enabled library, e.g. `flatpickr: [de]`. Only libraries shipping `vendor/l10n/` qualify.')
                            ->useAttributeAsKey('library')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                    ->validate()
                        ->always(static function (array $core): array {
                            self::validateLocales($core['editors'] ?? [], $core['locales'] ?? []);

                            return $core;
                        })
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Rejects locales that would fail at runtime: unknown or not enabled
     * libraries, names that are no locale file, and files that register their
     * translation under another name than their own (flatpickr's `ms.js`
     * registers nothing, `ga.js` registers `hr`), which the library could
     * never find.
     *
     * @param list<string>                $editors
     * @param array<string, list<mixed>> $locales
     */
    private static function validateLocales(array $editors, array $locales): void
    {
        foreach ($locales as $library => $names) {
            $library = (string) $library;
            if (!\in_array($library, Library::ALL, true)) {
                throw new InvalidConfigurationException(\sprintf('Invalid "core.locales": unknown library "%s". Available: %s.', $library, implode(', ', Library::ALL)));
            }
            $directory = \dirname(__DIR__).'/jx/'.$library.'/vendor/l10n';
            if (!is_dir($directory)) {
                throw new InvalidConfigurationException(\sprintf('Invalid "core.locales": library "%s" ships no locale files (no vendor/l10n/ directory).', $library));
            }
            if ([] !== $names && !\in_array($library, $editors, true)) {
                throw new InvalidConfigurationException(\sprintf('Invalid "core.locales": locales are configured for library "%s", which is not enabled in "core.editors".', $library));
            }
            foreach ($names as $locale) {
                if (!\is_string($locale) || 1 !== preg_match('/^[a-z][a-z0-9_-]*$/', $locale)) {
                    throw new InvalidConfigurationException(\sprintf('Invalid "core.locales.%s": %s is not a locale name (lowercase letters, digits, "-" and "_", starting with a letter).', $library, json_encode($locale, \JSON_UNESCAPED_SLASHES)));
                }
                if (\in_array($locale, ['index', 'default'], true)) {
                    throw new InvalidConfigurationException(\sprintf('Invalid "core.locales.%s": "%s" is not a locale (it is a collection file or the English default, which registers nothing).', $library, $locale));
                }
                $file = $directory.'/'.$locale.'.js';
                if (!is_file($file)) {
                    throw new InvalidConfigurationException(\sprintf('Invalid "core.locales.%s": unknown locale "%s", there is no vendor/l10n/%s.js.', $library, $locale, $locale));
                }
                $quoted = preg_quote($locale, '/');
                $registers = '/\bfp\.l10ns(?:\.'.$quoted.'|\[\s*["\']'.$quoted.'["\']\s*\])\s*=(?!=)/';
                if (1 !== preg_match($registers, (string) file_get_contents($file))) {
                    throw new InvalidConfigurationException(\sprintf('Invalid "core.locales.%s": locale file "%s.js" does not register under its own name "%s", so the library could not find it.', $library, $locale, $locale));
                }
            }
        }
    }

    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // The bundle always contributes its own editors. Its absolute directory
        // has to be resolved here rather than assumed: the bundle may sit in
        // vendor/ or in a composer path repository, so %kernel.project_dir% is
        // no help.
        $paths = array_merge(
            [\dirname(__DIR__).'/jx'],
            $config['paths'] ?? [],
        );

        $builder->setParameter('dmstr_jedison_extender.paths', $paths);
        $builder->setParameter(
            'dmstr_jedison_extender.enabled_libraries',
            array_values(array_unique($config['core']['editors'] ?? [])),
        );
        $locales = [];
        foreach ($config['core']['locales'] ?? [] as $library => $names) {
            if ([] !== $names) {
                $locales[(string) $library] = array_values(array_unique($names));
            }
        }
        $builder->setParameter('dmstr_jedison_extender.library_locales', $locales);
        $container->import(\dirname(__DIR__).'/config/services.yaml');
    }
}
