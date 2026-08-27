<?php
// file generated with AI assistance: Claude Code - 2026-08-26

declare(strict_types=1);

namespace Dmstr\JedisonExtender;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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
            ->end();
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
        $container->import(\dirname(__DIR__).'/config/services.yaml');
    }
}
