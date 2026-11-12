<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Admin\ExplorerAccess;
use Phil\MediaBundle\Infrastructure\Admin\MediaAdminController;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaReferences;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post;
use Phil\MediaBundle\Tests\Unit\Fixtures\Grants;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\MediaBundle\Tests\Unit\Fixtures\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

#[CoversClass(MediaAdminController::class)]
#[CoversClass(ExplorerAccess::class)]
final class AdminTest extends TestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private RequestStack $requests;

    private SameOriginCsrfTokenManager $tokens;

    private Grants $grants;

    private EntityManager $entityManager;

    private DoctrineMediaRepository $repository;

    private MediaLibrary $library;

    private MediaAdminController $controller;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager(records: true);
        $this->repository = new DoctrineMediaRepository($this->entityManager);
        $clock = new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC'));
        $this->library = new MediaLibrary($this->repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), $clock, ['admin_thumbnail' => ImageFormat::of('admin_thumbnail', 240, 240, 'fit')], references: new DoctrineMediaReferences($this->entityManager));
        $locator = new FileLocator(\dirname(__DIR__, 3).'/config');
        $routes = new PhpFileLoader($locator)->load('routes.php');
        $routes->addPrefix('/media');

        $admin = new PhpFileLoader($locator)->load('admin_routes.php');
        $admin->addPrefix('/admin/media');

        $routes->addCollection($admin);
        $urls = new UrlGenerator($routes, new RequestContext());
        $twig = Twig::environment($urls, new MediaExtension($urls, new UrlSigner('secret'), $clock));
        $this->requests = new RequestStack();
        $this->tokens = new SameOriginCsrfTokenManager($this->requests, null, null, [ExplorerAccess::TOKEN_ID]);
        $this->grants = new Grants();
        $this->controller = new MediaAdminController($this->repository, $this->library, $twig, $urls, new ExplorerAccess($this->tokens, $this->grants), '@PhilMedia/admin/layout.html.twig', 2, 1000);
    }

    public function testTheExplorerListsACollectionAsAGridOrAList(): void
    {
        $events = MediaCollection::create('events', 'Événements');
        $this->repository->save($events, MediaCollection::create('books', 'Books'));
        $image = $this->library->upload(Images::file(Images::png(40, 20)), 'fête.png', $events);
        $image->describe(null, 'Lanterns');

        $this->repository->save($image);
        $this->library->upload(Images::file('%PDF-1.4'), 'rapport.pdf', $events);
        $this->library->upload(Images::file('x'), 'notes.txt', $events);
        $this->library->upload(Images::file('y'), 'more.txt', $events);
        $this->library->upload(Images::file('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'logo.svg');

        $grid = (string) $this->controller->index($this->current(Request::create('/admin/media/?collection=events')))->getContent();
        self::assertStringContainsString('<title>Media · Événements</title>', $grid);
        self::assertStringContainsString('data-phil--media-bundle--explorer-view-value="grid"', $grid);
        self::assertStringContainsString('class="phil-media-items phil-media-grid"', $grid);
        self::assertStringContainsString('<a href="/admin/media/?collection=events&amp;view=grid&amp;page=2" rel="next">Next</a>', $grid);
        self::assertStringNotContainsString('rel="prev"', $grid);
        self::assertSame(2, substr_count($grid, '<li class="phil-media-item"'));
        self::assertStringContainsString('<span class="phil-media-thumb phil-media-file" aria-hidden="true">TXT</span>', $grid);
        self::assertStringContainsString('aria-current="page">Événements</a>', $grid);
        self::assertStringContainsString('<option value="events" selected>Événements</option>', $grid);
        self::assertStringContainsString('data-phil--media-bundle--upload-max-bytes-value="1000"', $grid);
        self::assertStringNotContainsString('role="status"', $grid);
        self::assertStringNotContainsString('role="alert"', $grid);

        $list = (string) $this->controller->index($this->current(Request::create('/admin/media/?collection=events&view=list&page=2&done=moved&error=Nope')))->getContent();
        self::assertStringContainsString('class="phil-media-items phil-media-list"', $list);
        self::assertStringContainsString('rel="prev">Previous</a>', $list);
        self::assertStringNotContainsString('rel="next"', $list);
        self::assertSame(2, substr_count($list, '<li class="phil-media-item"'));
        self::assertStringContainsString('<p class="phil-media-done" role="status">The file was moved.</p>', $list);
        self::assertStringContainsString('<p class="phil-media-error" role="alert">Nope</p>', $list);
        self::assertStringContainsString('src="/media/'.$image->id.'/1/admin_thumbnail.webp?signature=', $list);
        self::assertStringContainsString('alt="Lanterns"', $list);
        self::assertStringContainsString('image/png · 40 × 20 · 1 KiB', $list);

        $outside = (string) $this->controller->index($this->current(Request::create('/admin/media/?view=mosaic&done=exploded&page=0')))->getContent();
        self::assertStringContainsString('class="phil-media-items phil-media-grid"', $outside);
        self::assertStringContainsString('logo.svg', $outside);
        self::assertStringContainsString('<span class="phil-media-thumb phil-media-file" aria-hidden="true">SVG</span>', $outside);
        self::assertStringNotContainsString('role="status"', $outside);
        self::assertStringNotContainsString('class="phil-media-pages"', $outside);
        self::assertStringContainsString('aria-current="page">Outside every collection</a>', $outside);
        self::assertStringContainsString('<title>Media</title>', $outside);

        $empty = (string) $this->controller->index($this->current(Request::create('/admin/media/?collection=books')))->getContent();
        self::assertStringContainsString('<p>This collection holds no file.</p>', $empty);
        self::assertStringContainsString('<p>This collection holds no file.</p>', (string) $this->controller->index($this->current(Request::create('/admin/media/?collection=books&picker=1')))->getContent());
    }

    public function testThePickerIsTheListingWithoutTheLayout(): void
    {
        $image = $this->library->upload(Images::file(Images::png(40, 20)), 'fête.png');
        $pdf = $this->library->upload(Images::file('%PDF-1.4'), 'rapport.pdf');

        $picker = (string) $this->controller->index($this->current(Request::create('/admin/media/?picker=1')))->getContent();
        self::assertStringNotContainsString('<html', $picker);
        self::assertStringContainsString('data-media-id="'.$image->id.'" data-media-label="fête.png" data-media-preview="/media/'.$image->id.'/1/admin_thumbnail.webp?signature=', $picker);
        self::assertStringContainsString('data-media-id="'.$pdf->id.'" data-media-label="rapport.pdf">', $picker);
        self::assertStringContainsString('href="/admin/media/?view=grid&amp;picker=1"', $picker);
    }

    public function testFilesAreUploadedSeveralAtOnce(): void
    {
        $events = MediaCollection::create('events', 'Events');
        $this->repository->save($events);
        $request = $this->post('/admin/media/upload', ['collection' => 'events', 'view' => 'list'], ['files' => [$this->file('a.png', Images::png(4, 4)), $this->file('b.txt', 'b')]]);

        $response = $this->controller->upload($request);
        self::assertSame([Response::HTTP_SEE_OTHER, '/admin/media/?collection=events&view=list&done=uploaded'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame(['a.png', 'b.txt'], $this->names($this->repository->inCollection($events)));

        $json = $this->controller->upload($this->post('/admin/media/upload', [], ['files' => [$this->file('c.png', Images::png(4, 4))]], json: true));
        self::assertInstanceOf(JsonResponse::class, $json);
        $id = $this->repository->inCollection(null)[0]->id;
        self::assertSame([Response::HTTP_OK, ['uploaded' => [['id' => $id, 'filename' => 'c.png']], 'errors' => [], 'error' => null]], [$json->getStatusCode(), json_decode((string) $json->getContent(), true)]);
    }

    public function testAFileTooLargeOrRefusedIsReported(): void
    {
        $refused = $this->controller->upload($this->post('/admin/media/upload', [], ['files' => ['not a file', $this->file('big.bin', str_repeat('x', 1001)), $this->file('partial.png', 'x', \UPLOAD_ERR_PARTIAL), $this->file(str_repeat('n', 300).'.png', 'x'), $this->file('ok.txt', str_repeat('x', 1000))]], json: true));
        $id = $this->repository->inCollection(null)[0]->id;
        $tooLarge = 'The file is larger than 1000 bytes, or did not arrive whole.';
        $badName = \sprintf('A file name is 1 to 255 characters, without a slash; "%s.png" is not.', str_repeat('n', 300));
        self::assertSame([Response::HTTP_UNPROCESSABLE_ENTITY, [
            'uploaded' => [['id' => $id, 'filename' => 'ok.txt']],
            'errors' => [['filename' => 'big.bin', 'message' => $tooLarge], ['filename' => 'partial.png', 'message' => $tooLarge], ['filename' => str_repeat('n', 300).'.png', 'message' => $badName]],
            'error' => implode(' ', [$tooLarge, $tooLarge, $badName]),
        ]], [$refused->getStatusCode(), json_decode((string) $refused->getContent(), true)]);

        $none = $this->controller->upload($this->post('/admin/media/upload', ['view' => 'grid']));
        self::assertSame('/admin/media/?view=grid&error=No%20file%20was%20sent.', $none->headers->get('Location'));
        self::assertSame(['uploaded' => [], 'errors' => [['filename' => '', 'message' => 'No file was sent.']], 'error' => 'No file was sent.'], json_decode((string) $this->controller->upload($this->post('/admin/media/upload', json: true))->getContent(), true));
    }

    public function testCollectionsAreCreated(): void
    {
        $created = $this->controller->createCollection($this->post('/admin/media/collections', ['key' => 'events', 'name' => 'Events', 'view' => 'mosaic']));
        self::assertSame('/admin/media/?collection=events&done=created', $created->headers->get('Location'));
        self::assertSame('Events', $this->repository->collection('events')?->name());

        $twice = $this->controller->createCollection($this->post('/admin/media/collections', ['key' => 'events', 'name' => 'Again'], json: true));
        self::assertSame([Response::HTTP_UNPROCESSABLE_ENTITY, ['error' => 'The collection "events" exists already.']], [$twice->getStatusCode(), json_decode((string) $twice->getContent(), true)]);
        self::assertSame('Events', $this->repository->collection('events')?->name());

        $invalid = $this->controller->createCollection($this->post('/admin/media/collections', ['key' => 'Events!', 'name' => 'x']));
        self::assertStringStartsWith('/admin/media/?error=A%20collection%20key', (string) $invalid->headers->get('Location'));

        $json = $this->controller->createCollection($this->post('/admin/media/collections', ['key' => 'books', 'name' => 'Books'], json: true));
        self::assertSame(['key' => 'books', 'error' => null], json_decode((string) $json->getContent(), true));
    }

    public function testAMediumIsRenamedMovedAndDeleted(): void
    {
        $events = MediaCollection::create('events', 'Events');
        $this->repository->save($events);
        $media = $this->library->upload(Images::file('x'), 'a.txt', $events);

        $renamed = $this->controller->rename($this->post('/admin/media/x/rename', ['filename' => 'b.txt', 'view' => 'list']), $media->id);
        self::assertSame('/admin/media/?collection=events&view=list&done=renamed', $renamed->headers->get('Location'));
        self::assertSame(['id' => $media->id, 'filename' => 'c.txt', 'error' => null], json_decode((string) $this->controller->rename($this->post('/', ['filename' => 'c.txt'], json: true), $media->id)->getContent(), true));
        $badName = $this->controller->rename($this->post('/', ['filename' => '']), $media->id);
        self::assertStringStartsWith('/admin/media/?collection=events&error=A%20file%20name', (string) $badName->headers->get('Location'));
        $this->entityManager->clear();
        self::assertSame('c.txt', $this->repository->get($media->id)?->file()->filename);

        $moved = $this->controller->move($this->post('/', ['collection' => '']), $media->id);
        self::assertSame('/admin/media/?collection=events&done=moved', $moved->headers->get('Location'));
        $this->entityManager->clear();
        self::assertNull($this->repository->get($media->id)?->collection());
        self::assertSame(['id' => $media->id, 'collection' => 'events', 'error' => null], json_decode((string) $this->controller->move($this->post('/', ['collection' => 'events'], json: true), $media->id)->getContent(), true));
        $unknown = $this->controller->move($this->post('/', ['collection' => 'nowhere']), $media->id);
        self::assertSame('/admin/media/?collection=events&error=There%20is%20no%20collection%20%22nowhere%22.', $unknown->headers->get('Location'));
        $this->entityManager->clear();
        self::assertSame('events', $this->repository->get($media->id)?->collection()?->key);

        $deleted = $this->controller->delete($this->post('/'), $media->id);
        self::assertSame('/admin/media/?collection=events&done=deleted', $deleted->headers->get('Location'));
        self::assertNull($this->repository->get($media->id));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('There is no medium '.$media->id.'.');
        $this->controller->rename($this->post('/', ['filename' => 'd.txt']), $media->id);
    }

    public function testAMediumIsMovedOnlyToACollectionOpenToTheUser(): void
    {
        $events = MediaCollection::create('events', 'Événements');
        $closed = MediaCollection::create('closed', 'Fermée');
        $this->repository->save($events);
        $this->repository->save($closed);

        $media = $this->library->upload(Images::file('x'), 'a.txt', $events);
        $this->grants->refused = [$closed, null];

        foreach (['closed', ''] as $key) {
            try {
                $this->controller->move($this->post('/', ['collection' => $key]), $media->id);
                self::fail($key);
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('The file explorer is not open to this user.', $accessDeniedHttpException->getMessage());
            }
        }

        self::assertSame([['ROLE_ADMIN', $media], ['ROLE_ADMIN', $closed], ['ROLE_ADMIN', $media], ['ROLE_ADMIN', null]], $this->grants->asked);
        self::assertSame($events, $this->repository->get($media->id)?->collection(), 'not moved');
    }

    public function testAMediumInUseIsNotDeleted(): void
    {
        $media = $this->library->upload(Images::file('x'), 'a.txt');
        $this->entityManager->persist(new Post(1, MediaId::fromString($media->id)));
        $this->entityManager->flush();

        $refused = $this->controller->delete($this->post('/', [], json: true), $media->id);
        $answer = json_decode((string) $refused->getContent(), true);
        self::assertSame([Response::HTTP_UNPROCESSABLE_ENTITY, ['Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post.banner (1)']], [$refused->getStatusCode(), \is_array($answer) ? $answer['places'] : null]);
        self::assertInstanceOf(Media::class, $this->repository->get($media->id));
        $free = $this->library->upload(Images::file('x'), 'b.txt');
        self::assertSame(['id' => $free->id, 'error' => null], json_decode((string) $this->controller->delete($this->post('/', [], json: true), $free->id)->getContent(), true));
    }

    public function testAFormFromAnotherSiteIsRefused(): void
    {
        $media = $this->library->upload(Images::file('x'), 'a.txt');
        $trusted = $this->current(Request::create('/', 'POST', ['filename' => 'b.txt', '_csrf_token' => 'csrf-token'], server: ['HTTP_ORIGIN' => 'http://localhost']));
        self::assertSame(Response::HTTP_SEE_OTHER, $this->controller->rename($trusted, $media->id)->getStatusCode(), 'a form without JavaScript, from this site');

        foreach ([
            [[], ['_csrf_token' => self::TOKEN]],
            [['HTTP_ORIGIN' => 'https://elsewhere.example'], ['_csrf_token' => self::TOKEN]],
            [['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'http://localhost'], ['_csrf_token' => self::TOKEN]],
            [['HTTP_SEC_FETCH_SITE' => 'same-site'], ['_csrf_token' => self::TOKEN]],
            [['HTTP_SEC_FETCH_SITE' => 'same-origin'], []],
            [['HTTP_SEC_FETCH_SITE' => 'same-origin'], ['_csrf_token' => 'short']],
            [['HTTP_ORIGIN' => 'http://localhost'], ['_csrf_token' => '']],
        ] as [$server, $token]) {
            $request = $this->current(Request::create('/', 'POST', ['filename' => 'c.txt', ...$token], server: $server));

            foreach ([
                'upload' => fn (): Response => $this->controller->upload($request),
                'createCollection' => fn (): Response => $this->controller->createCollection($request),
                'rename' => fn (): Response => $this->controller->rename($request, $media->id),
                'move' => fn (): Response => $this->controller->move($request, $media->id),
                'delete' => fn (): Response => $this->controller->delete($request, $media->id),
            ] as $action => $call) {
                try {
                    $call();
                    self::fail($action);
                } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                    self::assertSame('The form was not posted from this site.', $accessDeniedHttpException->getMessage());
                }
            }
        }

        self::assertSame('b.txt', $this->repository->get($media->id)?->file()->filename);
        self::assertSame(['grid', 'list'], MediaAdminController::VIEWS);
        self::assertSame(['uploaded', 'renamed', 'moved', 'deleted', 'created'], MediaAdminController::OUTCOMES);
    }

    public function testTheExplorerIsOpenOnlyToTheConfiguredAttribute(): void
    {
        $events = MediaCollection::create('events', 'Événements');
        $this->repository->save($events);
        $media = $this->library->upload(Images::file('x'), 'a.txt', $events);
        $this->controller->index($this->current(Request::create('/admin/media/?collection=events')));
        $this->controller->rename($this->post('/', ['filename' => 'b.txt']), $media->id);
        self::assertSame([['ROLE_ADMIN', $events], ['ROLE_ADMIN', $media]], $this->grants->asked, 'the collection listed, the medium changed');

        $this->grants->granted = [];
        $this->grants->asked = [];

        foreach ([
            'index' => fn (): Response => $this->controller->index($this->current(Request::create('/admin/media/?collection=events'))),
            'picker' => fn (): Response => $this->controller->index($this->current(Request::create('/admin/media/?picker=1'))),
            'upload' => fn (): Response => $this->controller->upload($this->post('/', ['collection' => 'events'], ['files' => [$this->file('c.txt', 'c')]])),
            'createCollection' => fn (): Response => $this->controller->createCollection($this->post('/', ['key' => 'books', 'name' => 'Books'])),
            'rename' => fn (): Response => $this->controller->rename($this->post('/', ['filename' => 'c.txt']), $media->id),
            'move' => fn (): Response => $this->controller->move($this->post('/', ['collection' => '']), $media->id),
            'delete' => fn (): Response => $this->controller->delete($this->post('/'), $media->id),
        ] as $action => $call) {
            try {
                $call();
                self::fail($action);
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('The file explorer is not open to this user.', $accessDeniedHttpException->getMessage(), $action);
            }
        }

        self::assertSame([['ROLE_ADMIN', $events], ['ROLE_ADMIN', null], ['ROLE_ADMIN', $events], ['ROLE_ADMIN', null], ['ROLE_ADMIN', $media], ['ROLE_ADMIN', $media], ['ROLE_ADMIN', $media]], $this->grants->asked);
        $kept = $this->repository->get($media->id);
        self::assertSame(['b.txt', $events], [$kept?->file()->filename, $kept?->collection()], 'neither renamed, moved nor deleted');
        self::assertNull($this->repository->collection('books'));

        $this->grants->granted = ['MEDIA_EDIT'];
        $this->grants->asked = [];

        $voter = new ExplorerAccess($this->tokens, $this->grants, 'MEDIA_EDIT');
        $voter->assertGranted($events);
        self::assertSame([['MEDIA_EDIT', $events]], $this->grants->asked);

        try {
            new ExplorerAccess($this->tokens)->assertGranted(null);
            self::fail('Without security, the explorer is open.');
        } catch (AccessDeniedHttpException) {
        }

        self::assertSame(['phil_media_explorer', '_csrf_token', 'ROLE_ADMIN', 'csrf-token'], [ExplorerAccess::TOKEN_ID, ExplorerAccess::FIELD, ExplorerAccess::DEFAULT_ATTRIBUTE, $voter->cookieName()]);
    }

    public function testTheFormsCarryTheFieldOfTheToken(): void
    {
        $this->library->upload(Images::file('x'), 'a.txt');
        $html = (string) $this->controller->index($this->current(Request::create('/admin/media/')))->getContent();

        self::assertSame(5, substr_count($html, '<input type="hidden" name="_csrf_token" value="csrf-token">'), 'the collection, upload, rename, move and delete forms');
    }

    /**
     * A form of the explorer as its Stimulus controllers send it: from this site, with a new token in its field and in
     * a cookie; it is made the current request, where the token manager reads it.
     *
     * @param array<string, string>                    $parameters
     * @param array<string, list<UploadedFile|string>> $files
     */
    private function post(string $uri, array $parameters = [], array $files = [], bool $json = false): Request
    {
        return $this->current(Request::create($uri, 'POST', ['_csrf_token' => self::TOKEN, ...$parameters], ['csrf-token_'.self::TOKEN => 'csrf-token'], $files, ['HTTP_SEC_FETCH_SITE' => 'same-origin'] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : [])));
    }

    private function current(Request $request): Request
    {
        $this->requests->push($request);

        return $request;
    }

    private function file(string $name, string $contents, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        return new UploadedFile(Images::file($contents), $name, null, $error, true);
    }

    /**
     * @param list<Media> $media
     *
     * @return list<string>
     */
    private function names(array $media): array
    {
        $names = array_map(static fn (Media $medium): string => $medium->file()->filename, $media);
        sort($names);

        return $names;
    }
}
