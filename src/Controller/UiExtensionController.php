<?php
// file generated with AI assistance: Claude Code - 2026-08-26

declare(strict_types=1);

namespace Dmstr\JedisonExtender\Controller;

use Dmstr\JedisonExtender\Service\ExtensionLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the catalogue of available Jedison extensions and their module files.
 *
 * WHY THESE ROUTES ARE PUBLIC AND SIT OUTSIDE ANY `/api` FIREWALL: front ends
 * load a module with a dynamic `import()`, and that cannot carry an
 * `Authorization` header. Behind a JWT firewall the module would be unreachable
 * and the route pointless. This is safe because a module holds no secret — it is
 * UI code every client executes anyway. What must never live in one is anything
 * tenant- or user-dependent; that stays behind the authenticated endpoints the
 * editors call WITH a token supplied by the host.
 *
 * `stateless: true` is not cosmetic. The routes fall outside a stateless API
 * firewall and therefore into a session-backed one, so a later change touching
 * the session here would silently start one per request. Marked stateless,
 * Symfony turns that into a loud error instead.
 *
 * CORS is required, not optional: an ES module loaded from another origin fails
 * without `Access-Control-Allow-Origin`. Configuring that is the application's
 * job — the front ends usually run on a different origin than the API.
 */
final class UiExtensionController
{
    /**
     * Served file types and their content types — an allow-list, never a deny
     * list. Everything else (`json`, `php`, `yaml`, `env`, ...) is a 404, so a
     * file that lands in an extension directory by accident is not published.
     */
    private const CONTENT_TYPES = [
        'js' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'png' => 'image/png',
    ];

    /**
     * Files above this size are streamed instead of read into memory. Only
     * vendored libraries get near it; the editors themselves are far smaller.
     */
    private const STREAM_THRESHOLD = 1024 * 1024;

    /**
     * One or more path segments. Each starts with a letter or digit — which
     * rules out `.`, `..`, hidden files and empty segments — and holds only
     * letters, digits, `.`, `_` and `-`. There is no leading slash either.
     */
    private const FILE_PATTERN = '[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*';

    /** @param list<string> $paths */
    public function __construct(
        private readonly ExtensionLocator $locator,
        private readonly array $paths,
    ) {
    }

    /**
     * The catalogue. A consumer reads this FIRST and checks the `jedison` range
     * against its own version before importing anything — otherwise an
     * incompatible extension shows up as a broken form instead of a message.
     */
    #[Route('/ui-extensions', name: 'dmstr_ui_extensions_index', methods: ['GET'], stateless: true)]
    public function index(Request $request): JsonResponse
    {
        $extensions = [];

        foreach ($this->locator->manifests($this->paths) as $name => $manifest) {
            $module = \is_string($manifest['module'] ?? null) ? $manifest['module'] : 'index.js';

            $extensions[] = [
                'name' => $name,
                'version' => $manifest['version'] ?? null,
                'jedison' => $manifest['jedison'] ?? null,
                'description' => $manifest['description'] ?? null,
                // Absolute, so a consumer can import it without knowing the layout.
                'module' => $request->getSchemeAndHttpHost().'/ui-extensions/'.$name.'/'.$module,
            ];
        }

        return new JsonResponse(['extensions' => $extensions]);
    }

    /**
     * A single file of an extension: a module, a stylesheet or an image.
     *
     * The requirements are the first defence: `{file}` admits subdirectories
     * (vendored libraries live in `vendor/`) but every segment must start with a
     * letter or digit, so neither `../` nor a decoded `%2e%2e%2f` can reach the
     * file system. Note this cannot be demonstrated with curl — an HTTP client
     * normalises dot segments before sending (RFC 3986), so such a request never
     * arrives here. The proof belongs in a test that calls the controller with a
     * prepared parameter, which is why the pattern is checked again below.
     *
     * The second defence is the locator's realpath comparison, the third the
     * allow-list of file types.
     *
     * `{name}` admits a leading underscore, and that one character is what makes
     * SHARED modules possible. A module under `/ui-extensions/core/index.js`
     * importing `'../_shared/labels.js'` is resolved by the BROWSER against its
     * own URL, so the request that arrives here is a plain
     * `/ui-extensions/_shared/labels.js` — the same RFC 3986 normalisation as
     * above, used the other way round. A directory without a `manifest.json` is
     * never listed in the catalogue but is still served, which is exactly what a
     * shared directory needs: reachable, not advertised as an extension.
     */
    #[Route(
        '/ui-extensions/{name}/{file}',
        name: 'dmstr_ui_extensions_module',
        requirements: ['name' => '_?[a-z0-9][a-z0-9-]*', 'file' => self::FILE_PATTERN],
        methods: ['GET'],
        stateless: true,
    )]
    public function module(string $name, string $file, Request $request): Response
    {
        $extension = pathinfo($file, \PATHINFO_EXTENSION);

        // `manifest.json` is excluded by its extension already; the basename
        // check keeps it a 404 should `json` ever join the allow-list.
        if (
            !preg_match('#^'.self::FILE_PATTERN.'$#D', $file)
            || 'manifest.json' === basename($file)
            || !isset(self::CONTENT_TYPES[$extension])
        ) {
            throw new NotFoundHttpException(sprintf('Unknown UI extension file "%s/%s".', $name, $file));
        }

        $path = $this->locator->resolve($this->paths, $name, $file);

        if (null === $path) {
            throw new NotFoundHttpException(sprintf('Unknown UI extension file "%s/%s".', $name, $file));
        }

        // A module script must be served with its proper type; a wrong one makes
        // the browser refuse the import.
        $headers = ['Content-Type' => self::CONTENT_TYPES[$extension]];
        $size = filesize($path);

        if ('png' === $extension || false === $size || $size > self::STREAM_THRESHOLD) {
            $response = new BinaryFileResponse($path, Response::HTTP_OK, $headers, false);
            $response->setEtag((string) md5_file($path));
        } else {
            // A plain Response keeps the body readable through getContent().
            $contents = (string) file_get_contents($path);
            $response = new Response($contents, Response::HTTP_OK, $headers);
            $response->setEtag(md5($contents));
        }

        $response->isNotModified($request);

        return $response;
    }
}
