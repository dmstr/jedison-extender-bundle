<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:20:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Support;

use Dmstr\JedisonExtender\JedisonExtenderBundle;
use Dmstr\JedisonExtender\Service\ExtensionLocator;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The smallest application that can host the bundle: FrameworkBundle and this
 * bundle, nothing else.
 *
 * Configurable per test. Every distinct configuration gets its own cache
 * directory AND its own container class name — two kernels of one class in one
 * PHP process would otherwise share the first container class and silently
 * ignore the second configuration.
 */
final class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public const FIXTURES = __DIR__.'/../Fixtures';

    /**
     * @param list<string> $editors        value of `core.editors`
     * @param list<string> $paths          value of `paths`
     * @param string|null  $bundleFixture  a directory standing in for the
     *                                     bundle's own `jx/`; null keeps the real one
     */
    public function __construct(
        private readonly array $editors = [],
        private readonly array $paths = [],
        private readonly ?string $bundleFixture = null,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new JedisonExtenderBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/jedison-extender-tests/'.$this->fingerprint();
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }

    protected function getContainerClass(): string
    {
        return parent::getContainerClass().'_'.$this->fingerprint();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('dmstr_jedison_extender', [
            'paths' => $this->paths,
            'core' => ['editors' => $this->editors],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/config/routes.yaml');
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass($this);
    }

    /**
     * Points the bundle's own directory at a fixture. The bundle always puts its
     * real `jx/` first in the search paths and tells the locator which
     * directory the library filter applies to; both are swapped here, after the
     * bundle has loaded and before parameters are resolved.
     */
    public function process(ContainerBuilder $container): void
    {
        if (null === $this->bundleFixture) {
            return;
        }

        $paths = $container->getParameter('dmstr_jedison_extender.paths');
        $paths[0] = $this->bundleFixture;
        $container->setParameter('dmstr_jedison_extender.paths', $paths);
        $container->getDefinition(ExtensionLocator::class)->setArgument('$bundleDirectory', $this->bundleFixture);
    }

    private function fingerprint(): string
    {
        return md5(serialize([$this->editors, $this->paths, $this->bundleFixture]));
    }
}
