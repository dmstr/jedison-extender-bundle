<?php
// file generated with AI assistance: Claude Code - 2026-11-16 16:40:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Tests\Controller;

use Dmstr\JedisonExtender\Controller\UiExtensionController;
use Dmstr\JedisonExtender\Service\ExtensionLocator;
use Dmstr\JedisonExtender\Tests\Support\KernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * The file route: what is served, with which type, and what never is.
 *
 * Traversal is asserted against the CONTROLLER and the locator rather than over
 * HTTP: a client normalises dot segments before sending (RFC 3986), so such a
 * request never arrives and a functional test would pass without exercising the
 * defence.
 */
final class FileRouteTest extends KernelTestCase
{
    private string $tmp = '';

    protected function tearDown(): void
    {
        if ('' !== $this->tmp) {
            exec('rm -rf '.escapeshellarg($this->tmp));
        }
    }

    // ---- what is served -------------------------------------------------

    #[DataProvider('servedTypes')]
    public function testAllowedTypesAreServedWithTheirContentType(string $file, string $contentType): void
    {
        $client = self::fixtureClient();
        $client->request('GET', '/ui-extensions/local/'.$file);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame($contentType, $client->getResponse()->headers->get('Content-Type'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function servedTypes(): iterable
    {
        yield 'js' => ['index.js', 'text/javascript; charset=UTF-8'];
        yield 'css' => ['style.css', 'text/css; charset=UTF-8'];
        yield 'png' => ['pixel.png', 'image/png'];
    }

    #[DataProvider('refusedFiles')]
    public function testOtherTypesAre404(string $file): void
    {
        $client = self::fixtureClient();
        $client->request('GET', '/ui-extensions/local/'.$file);

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function refusedFiles(): iterable
    {
        yield 'json' => ['data.json'];
        yield 'manifest' => ['manifest.json'];
        yield 'php' => ['secret.php'];
        yield 'yaml' => ['config.yaml'];
        yield 'env' => ['settings.env'];
    }

    public function testASubdirectoryIsServed(): void
    {
        $client = self::fixtureClient();
        $client->request('GET', '/ui-extensions/local/sub/deep.js');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('deep', (string) $client->getResponse()->getContent());
    }

    public function testTheModuleCarriesAnETagAndAnswers304(): void
    {
        $client = self::fixtureClient();
        $client->request('GET', '/ui-extensions/local/index.js');
        $etag = $client->getResponse()->headers->get('ETag');

        self::assertNotEmpty($etag);

        $client->request('GET', '/ui-extensions/local/index.js', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(304, $client->getResponse()->getStatusCode());
    }

    public function testPngCarriesAnETagToo(): void
    {
        $client = self::fixtureClient();
        $client->request('GET', '/ui-extensions/local/pixel.png');

        self::assertNotEmpty($client->getResponse()->headers->get('ETag'));
    }

    public function testSmallModulesStayPlainResponsesAndLargeFilesAreStreamed(): void
    {
        $this->tmp = sys_get_temp_dir().'/jx-large-'.bin2hex(random_bytes(4));
        mkdir($this->tmp.'/big', 0777, true);
        file_put_contents($this->tmp.'/big/small.js', '// small');
        file_put_contents($this->tmp.'/big/large.js', str_repeat('// padding'.\PHP_EOL, 150_000));

        $controller = new UiExtensionController(new ExtensionLocator(), [$this->tmp]);

        $small = $controller->module('big', 'small.js', new Request());
        $large = $controller->module('big', 'large.js', new Request());

        self::assertNotInstanceOf(BinaryFileResponse::class, $small);
        self::assertSame('// small', $small->getContent());
        self::assertInstanceOf(BinaryFileResponse::class, $large);
        self::assertSame('text/javascript; charset=UTF-8', $large->headers->get('Content-Type'));
        self::assertNotEmpty($large->headers->get('ETag'));
    }

    // ---- what never is --------------------------------------------------

    /** The route requirement is the first defence: these never reach the controller. */
    #[DataProvider('unroutablePaths')]
    public function testTheRouteRequirementRejects(string $path): void
    {
        $router = self::kernel()->getContainer()->get('router');

        $this->expectException(ResourceNotFoundException::class);
        $router->match($path);
    }

    /** @return iterable<string, array{string}> */
    public static function unroutablePaths(): iterable
    {
        yield 'dot dot segment' => ['/ui-extensions/core/../x.js'];
        yield 'dot dot first' => ['/ui-extensions/core/../../x.js'];
        yield 'single dot segment' => ['/ui-extensions/core/./index.js'];
        yield 'hidden file' => ['/ui-extensions/core/.hidden.js'];
        yield 'hidden directory' => ['/ui-extensions/core/.git/config.js'];
        yield 'empty segment' => ['/ui-extensions/core/sub//deep.js'];
        yield 'leading slash' => ['/ui-extensions/core//index.js'];
        yield 'backslash' => ['/ui-extensions/core/sub\\deep.js'];
    }

    public function testASubdirectoryPathIsRoutable(): void
    {
        $match = self::kernel()->getContainer()->get('router')->match('/ui-extensions/core/vendor/awesomplete.min.js');

        self::assertSame('vendor/awesomplete.min.js', $match['file']);
    }

    /** The controller checks the pattern itself, so a prepared parameter is stopped too. */
    #[DataProvider('traversalAttempts')]
    public function testTraversalIsRejectedByTheController(string $name, string $file): void
    {
        $controller = new UiExtensionController(new ExtensionLocator(), [self::APP_FIXTURE]);

        $this->expectException(NotFoundHttpException::class);
        $controller->module($name, $file, new Request());
    }

    /** @return iterable<string, array{string, string}> */
    public static function traversalAttempts(): iterable
    {
        yield 'parent directory' => ['local', '../other/index.js'];
        yield 'two levels up' => ['local', '../../Fixtures/app/local/index.js'];
        yield 'encoded dots' => ['local', '%2e%2e/ace/index.js'];
        yield 'absolute path' => ['local', '/etc/passwd.js'];
        yield 'absolute js' => ['local', self::APP_FIXTURE.'/local/index.js'];
        yield 'dot segment' => ['local', './index.js'];
        yield 'empty segment' => ['local', 'sub//deep.js'];
        yield 'traversal in the name' => ['../local', 'index.js'];
        yield 'manifest by basename' => ['local', 'sub/manifest.json'];
    }

    /**
     * A REAL .js file outside the extension, reached by a path that passes the
     * type allow-list: only the realpath containment can stop it. Calling the
     * locator directly bypasses the controller's pattern check on purpose —
     * this is the last line of defence and has to hold on its own.
     */
    public function testARealFileOutsideTheExtensionIsNotResolved(): void
    {
        $this->tmp = sys_get_temp_dir().'/jx-escape-'.bin2hex(random_bytes(4));
        mkdir($this->tmp.'/root/ext', 0777, true);
        file_put_contents($this->tmp.'/root/ext/inside.js', '// inside');
        file_put_contents($this->tmp.'/root/outside.js', '// sibling of the extension');
        file_put_contents($this->tmp.'/secret.js', '// outside the root');

        $locator = new ExtensionLocator();
        $paths = [$this->tmp.'/root'];

        self::assertNotNull($locator->resolve($paths, 'ext', 'inside.js'));
        self::assertNull($locator->resolve($paths, 'ext', '../outside.js'));
        self::assertNull($locator->resolve($paths, 'ext', '../../secret.js'));
    }

    /** A symlink inside the extension passes the pattern; only realpath sees where it leads. */
    public function testASymlinkLeadingOutsideIsNotServed(): void
    {
        $this->tmp = sys_get_temp_dir().'/jx-link-'.bin2hex(random_bytes(4));
        mkdir($this->tmp.'/root/ext', 0777, true);
        file_put_contents($this->tmp.'/secret.js', '// outside the root');
        symlink($this->tmp.'/secret.js', $this->tmp.'/root/ext/link.js');

        $controller = new UiExtensionController(new ExtensionLocator(), [$this->tmp.'/root']);

        $this->expectException(NotFoundHttpException::class);
        $controller->module('ext', 'link.js', new Request());
    }

    public function testAnUnknownExtensionYields404(): void
    {
        $client = self::fixtureClient();
        $client->request('GET', '/ui-extensions/does-not-exist/index.js');

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testTheRoutesStayOutsideAnApiPrefix(): void
    {
        $routes = self::kernel()->getContainer()->get('router')->getRouteCollection();
        $ours = array_filter(
            $routes->all(),
            static fn (Route $route, string $name): bool => str_starts_with($name, 'dmstr_ui_extensions_'),
            \ARRAY_FILTER_USE_BOTH,
        );

        self::assertCount(2, $ours);
        foreach ($ours as $route) {
            self::assertStringStartsWith('/ui-extensions', $route->getPath());
            self::assertStringStartsNotWith('/api', $route->getPath());
        }
    }
}
