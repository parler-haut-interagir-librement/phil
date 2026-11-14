<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Phil\UploadScanBundle\Application\QuarantinedFile;
use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Phil\UploadScanBundle\Infrastructure\EasyAdmin\QuarantineAdmin;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\CsrfRuntime;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(QuarantineAdminController::class)]
#[CoversClass(QuarantineAdmin::class)]
final class QuarantineAdminTest extends TestCase
{
    private string $root;

    private FilesystemQuarantine $quarantine;

    private CsrfTokenManager $csrf;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->root = Temporary::directory();
        $this->quarantine = new FilesystemQuarantine($this->root);
        $this->csrf = new CsrfTokenManager(storage: new class implements TokenStorageInterface {
            /** @var array<string, string> */
            private array $tokens = [];

            public function getToken(string $tokenId): string
            {
                return $this->tokens[$tokenId] ?? '';
            }

            public function setToken(string $tokenId, #[SensitiveParameter] string $token): void
            {
                $this->tokens[$tokenId] = $token;
            }

            public function removeToken(string $tokenId): ?string
            {
                $token = $this->tokens[$tokenId] ?? null;
                unset($this->tokens[$tokenId]);

                return $token;
            }

            public function hasToken(string $tokenId): bool
            {
                return isset($this->tokens[$tokenId]);
            }
        });
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        Temporary::remove($this->root);
    }

    public function testTheListShowsTheRecordsAPageAtATime(): void
    {
        $ids = [];

        foreach (['a.pdf', 'b.png', '<script>c</script>.txt'] as $index => $name) {
            $ids[] = $id = $this->quarantine->isolate($this->stream('payload of '.$name), $name, ScanResult::infected('Sig-'.$index));
            touch($this->root.'/'.$id.'.json', 1_000 + $index);
        }

        $first = $this->controller(pageSize: 2)->list(Request::create('/admin/quarantine/'));
        $html = (string) $first->getContent();
        self::assertSame(Response::HTTP_OK, $first->getStatusCode());
        self::assertStringContainsString('<title>Quarantine</title>', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertStringContainsString('&lt;script&gt;c&lt;/script&gt;.txt', $html);
        self::assertStringNotContainsString('<script>c', $html);
        self::assertStringContainsString('Sig-2', $html);
        self::assertStringContainsString('Sig-1', $html);
        self::assertStringNotContainsString('Sig-0', $html);
        self::assertStringNotContainsString('payload of', $html);
        self::assertStringContainsString('action="/admin/quarantine/'.$ids[2].'/delete"', $html);
        self::assertStringContainsString('<input type="hidden" name="_token" value="', $html);
        self::assertStringContainsString('href="/admin/quarantine/?page=2" rel="next">Older</a>', $html);
        self::assertStringNotContainsString('rel="prev"', $html);
        self::assertStringNotContainsString('role="status"', $html);

        $second = (string) $this->controller(pageSize: 2)->list(Request::create('/admin/quarantine/?page=2&done=deleted'))->getContent();
        self::assertStringContainsString('Sig-0', $second);
        self::assertStringNotContainsString('Sig-1', $second);
        self::assertStringContainsString('href="/admin/quarantine/?page=1" rel="prev">Newer</a>', $second);
        self::assertStringNotContainsString('rel="next"', $second);
        self::assertStringContainsString('<p role="status">The file was deleted.</p>', $second);

        // A page before the first is the first.
        $tokenless = static fn (string $page): string => (string) preg_replace('/name="_token" value="[^"]+"/', '', $page);
        self::assertSame($tokenless($html), $tokenless((string) $this->controller(pageSize: 2)->list(Request::create('/admin/quarantine/?page=0'))->getContent()));
        $exact = (string) $this->controller(pageSize: 3)->list(Request::create('/admin/quarantine/'))->getContent();
        self::assertStringContainsString('Sig-0', $exact);
        self::assertStringNotContainsString('<nav', $exact);
    }

    public function testThePageAsksTheInventoryForOneFileMoreThanItShows(): void
    {
        $spy = new class($this->quarantine) implements QuarantineInventory {
            /** @var list<array{int, int}> */
            public array $asked = [];

            public function __construct(private readonly QuarantineInventory $inner)
            {
            }

            public function files(int $limit, int $offset = 0): array
            {
                $this->asked[] = [$limit, $offset];

                return $this->inner->files($limit, $offset);
            }

            public function file(string $id): ?QuarantinedFile
            {
                return $this->inner->file($id);
            }

            public function delete(string $id): bool
            {
                return $this->inner->delete($id);
            }
        };

        $this->controller(pageSize: 2, inventory: $spy)->list(Request::create('/admin/quarantine/?page=3'));
        self::assertSame([[3, 4]], $spy->asked);
    }

    public function testADeletionWithoutALoggerStillGoesThrough(): void
    {
        $id = $this->quarantine->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        $token = $this->csrf->getToken(QuarantineAdminController::CSRF_TOKEN_ID)->getValue();

        $response = $this->controller(logs: false)->delete(Request::create('/admin/quarantine/'.$id.'/delete', 'POST', ['_token' => $token]), $id);

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertNull($this->quarantine->file($id));
    }

    public function testAnEmptyQuarantineSaysSo(): void
    {
        self::assertStringContainsString('The quarantine holds no file.', (string) $this->controller()->list(Request::create('/admin/quarantine/'))->getContent());
    }

    public function testAFileIsDeletedWithAValidTokenAndTheDeletionIsLogged(): void
    {
        $id = $this->quarantine->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        $token = $this->csrf->getToken(QuarantineAdminController::CSRF_TOKEN_ID)->getValue();

        $response = $this->controller()->delete(Request::create('/admin/quarantine/'.$id.'/delete', 'POST', ['_token' => $token]), $id);

        self::assertSame([Response::HTTP_SEE_OTHER, '/admin/quarantine/?done=deleted'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertNull($this->quarantine->file($id));
        self::assertSame([['notice', 'A quarantined file was deleted.', ['id' => $id]]], $this->logger->records);

        $this->expectException(NotFoundHttpException::class);
        $this->controller()->delete(Request::create('/admin/quarantine/'.$id.'/delete', 'POST', ['_token' => $token]), $id);
    }

    public function testADeletionWithoutAValidTokenIsRefused(): void
    {
        $id = $this->quarantine->isolate($this->stream('x'), 'x', ScanResult::infected('X'));

        foreach ([[], ['_token' => 'forged']] as $form) {
            try {
                $this->controller()->delete(Request::create('/admin/quarantine/'.$id.'/delete', 'POST', $form), $id);
                self::fail('A deletion without a valid token went through.');
            } catch (BadRequestHttpException $badRequestHttpException) {
                self::assertSame('The CSRF token is not valid.', $badRequestHttpException->getMessage());
            }
        }

        self::assertNotNull($this->quarantine->file($id));
        self::assertSame([], $this->logger->records);
    }

    public function testEveryPageAsksForTheRole(): void
    {
        $id = $this->quarantine->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        $token = $this->csrf->getToken(QuarantineAdminController::CSRF_TOKEN_ID)->getValue();
        $request = Request::create('/admin/quarantine/'.$id.'/delete', 'POST', ['_token' => $token]);

        foreach ([$this->grants([]), null] as $checker) {
            foreach (['list', 'delete'] as $page) {
                try {
                    'list' === $page ? $this->controller($checker)->list(Request::create('/')) : $this->controller($checker)->delete($request, $id);
                    self::fail('The quarantine opened without the role.');
                } catch (AccessDeniedException $accessDeniedException) {
                    self::assertSame(['ROLE_SECURITY'], $accessDeniedException->getAttributes());
                }
            }
        }

        self::assertNotNull($this->quarantine->file($id));
    }

    public function testTheMenuLinksToTheQuarantine(): void
    {
        $item = QuarantineAdmin::menuItem()->getAsDto();
        self::assertSame(['Quarantine', 'fa fa-biohazard', 'phil_upload_scan_quarantine'], [$item->getLabel(), $item->getIcon(), $item->getRouteName()]);

        $label = new class implements TranslatableInterface {
            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return $translator->trans('menu.quarantine', locale: $locale);
            }
        };
        $own = QuarantineAdmin::menuItem($label, null)->getAsDto();
        self::assertSame([$label, null], [$own->getLabel(), $own->getIcon()]);
        self::assertSame(['phil_upload_scan_quarantine', 'phil_upload_scan_quarantine_delete', 'phil_upload_scan_quarantine'], [QuarantineAdminController::LIST_ROUTE, QuarantineAdminController::DELETE_ROUTE, QuarantineAdminController::CSRF_TOKEN_ID]);
    }

    /**
     * @param int<1, max> $pageSize
     */
    private function controller(false|AuthorizationCheckerInterface|null $checker = false, int $pageSize = 50, bool $logs = true, ?QuarantineInventory $inventory = null): QuarantineAdminController
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('admin_routes.php');
        $routes->addPrefix('/admin/quarantine');

        $urls = new UrlGenerator($routes, new RequestContext());
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilUploadScan');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new RoutingExtension($urls));
        $twig->addExtension(new CsrfExtension());

        $csrf = $this->csrf;
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([CsrfRuntime::class => static fn (): CsrfRuntime => new CsrfRuntime($csrf)]));

        return new QuarantineAdminController($inventory ?? $this->quarantine, $twig, $urls, $this->csrf, false === $checker ? $this->grants(['ROLE_SECURITY']) : $checker, 'ROLE_SECURITY', '@PhilUploadScan/admin/layout.html.twig', $pageSize, $logs ? $this->logger : null);
    }

    /**
     * @param list<string> $roles
     */
    private function grants(array $roles): AuthorizationCheckerInterface
    {
        return new readonly class($roles) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $roles
             */
            public function __construct(private array $roles)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->roles, true);
            }
        };
    }

    /**
     * @return resource
     */
    private function stream(string $contents): mixed
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
