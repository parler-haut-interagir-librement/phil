<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Admin;

use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Exception\MediaInUse;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The file explorer: the media of a collection as a grid or a list, a page at a time; collections created; files
 * uploaded, several at once; a medium renamed, moved to another collection, or deleted. The same listing, without the
 * layout, is the picker a form field opens in a dialog.
 *
 * Every form works without JavaScript, then answers with a `303` back to the listing and what was done; the Stimulus
 * controllers send them in the background, asking for JSON. Each listing and change is refused to a user the
 * application's security does not grant the configured attribute, and a form posted from another site is refused
 * ({@see ExplorerAccess}). The application puts the routes behind its firewall too.
 */
final readonly class MediaAdminController
{
    /** The route of the listing, as `config/admin_routes.php` names it. */
    public const string INDEX_ROUTE = 'phil_media_admin';

    public const array VIEWS = ['grid', 'list'];

    /**
     * What a listing reports after a change, by the `done` of its URL.
     */
    public const array OUTCOMES = ['uploaded', 'renamed', 'moved', 'deleted', 'created'];

    /**
     * @param int<1, max> $pageSize
     * @param int<1, max> $maxUploadBytes
     */
    public function __construct(
        private MediaRepository $media,
        private MediaLibrary $library,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ExplorerAccess $access,
        private string $layout,
        private int $pageSize,
        private int $maxUploadBytes,
    ) {
    }

    public function index(Request $request): Response
    {
        $collection = $this->collection($request->query->getString('collection'));
        $this->access->assertGranted($collection);
        $page = max(1, $request->query->getInt('page'));
        $view = $request->query->getString('view');
        $done = $request->query->getString('done');
        $media = $this->media->inCollection($collection, $this->pageSize + 1, ($page - 1) * $this->pageSize);
        $picker = $request->query->getBoolean('picker');

        return new Response($this->twig->render($picker ? '@PhilMedia/admin/picker.html.twig' : '@PhilMedia/admin/index.html.twig', [
            'layout' => $this->layout,
            'collections' => $this->media->collections(),
            'collection' => $collection,
            'media' => \array_slice($media, 0, $this->pageSize),
            'page' => $page,
            'more' => \count($media) === $this->pageSize + 1,
            'view' => \in_array($view, self::VIEWS, true) ? $view : 'grid',
            'done' => \in_array($done, self::OUTCOMES, true) ? $done : null,
            'error' => $request->query->getString('error'),
            'max_upload_bytes' => $this->maxUploadBytes,
            'csrf_cookie' => $this->access->cookieName(),
        ]));
    }

    public function upload(Request $request): Response
    {
        $this->access->assertSentFromHere($request);
        $collection = $this->collection($request->request->getString('collection'));
        $this->access->assertGranted($collection);
        $uploaded = [];
        $errors = [];

        foreach ($request->files->all('files') as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $name = $file->getClientOriginalName();

            if (!$file->isValid() || $file->getSize() > $this->maxUploadBytes) {
                $errors[] = ['filename' => $name, 'message' => \sprintf('The file is larger than %d bytes, or did not arrive whole.', $this->maxUploadBytes)];

                continue;
            }

            try {
                $media = $this->library->upload($file->getPathname(), $name, $collection);
                $uploaded[] = ['id' => $media->id, 'filename' => $media->file()->filename];
            } catch (InvalidMedia $invalidMedia) {
                $errors[] = ['filename' => $name, 'message' => $invalidMedia->getMessage()];
            }
        }

        if ([] === $uploaded && [] === $errors) {
            $errors[] = ['filename' => '', 'message' => 'No file was sent.'];
        }

        return $this->answer($request, $collection, 'uploaded', ['uploaded' => $uploaded, 'errors' => $errors], [] === $errors ? null : implode(' ', array_column($errors, 'message')));
    }

    public function createCollection(Request $request): Response
    {
        $this->access->assertSentFromHere($request);
        $this->access->assertGranted(null);

        try {
            $collection = MediaCollection::create($request->request->getString('key'), $request->request->getString('name'));
        } catch (InvalidMedia $invalidMedia) {
            return $this->answer($request, null, 'created', [], $invalidMedia->getMessage());
        }

        if ($this->media->collection($collection->key) instanceof MediaCollection) {
            return $this->answer($request, null, 'created', [], \sprintf('The collection "%s" exists already.', $collection->key));
        }

        $this->media->save($collection);

        return $this->answer($request, $collection, 'created', ['key' => $collection->key]);
    }

    public function rename(Request $request, string $id): Response
    {
        $this->access->assertSentFromHere($request);
        $media = $this->found($id);
        $this->access->assertGranted($media);

        try {
            $media->rename($request->request->getString('filename'));
            $this->media->save($media);
        } catch (InvalidMedia $invalidMedia) {
            return $this->answer($request, $media->collection(), 'renamed', [], $invalidMedia->getMessage());
        }

        return $this->answer($request, $media->collection(), 'renamed', ['id' => $media->id, 'filename' => $media->file()->filename]);
    }

    public function move(Request $request, string $id): Response
    {
        $this->access->assertSentFromHere($request);
        $media = $this->found($id);
        $this->access->assertGranted($media);
        $from = $media->collection();
        $key = $request->request->getString('collection');
        $to = $this->collection($key);

        if ('' !== $key && !$to instanceof MediaCollection) {
            return $this->answer($request, $from, 'moved', [], \sprintf('There is no collection "%s".', $key));
        }

        // The destination is asked too, the root (`null`) as an upload asks it: a medium goes nowhere closed to the user.
        $this->access->assertGranted($to);
        $media->moveTo($to);
        $this->media->save($media);

        return $this->answer($request, $from, 'moved', ['id' => $media->id, 'collection' => $to?->key]);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->access->assertSentFromHere($request);
        $media = $this->found($id);
        $this->access->assertGranted($media);
        $collection = $media->collection();

        try {
            $this->library->delete($media);
        } catch (MediaInUse $mediaInUse) {
            return $this->answer($request, $collection, 'deleted', ['places' => $mediaInUse->places], $mediaInUse->getMessage());
        }

        return $this->answer($request, $collection, 'deleted', ['id' => $id]);
    }

    /**
     * JSON for the Stimulus controllers, `422` on a refusal; otherwise a `303` back to the listing.
     *
     * @param array<string, mixed> $data
     */
    private function answer(Request $request, ?MediaCollection $collection, string $done, array $data, ?string $error = null): Response
    {
        if (\in_array('application/json', $request->getAcceptableContentTypes(), true)) {
            return new JsonResponse([...$data, 'error' => $error], null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = [
            'collection' => $collection?->key,
            'view' => \in_array($request->request->getString('view'), self::VIEWS, true) ? $request->request->getString('view') : null,
            'done' => null === $error ? $done : null,
            'error' => $error,
        ];

        return new RedirectResponse($this->urls->generate('phil_media_admin', $query), Response::HTTP_SEE_OTHER);
    }

    private function collection(string $key): ?MediaCollection
    {
        return '' === $key ? null : $this->media->collection($key);
    }

    private function found(string $id): Media
    {
        return $this->media->get($id) ?? throw new NotFoundHttpException(\sprintf('There is no medium %s.', $id));
    }
}
