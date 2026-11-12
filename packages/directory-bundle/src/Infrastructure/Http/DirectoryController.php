<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Http;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DirectoryBundle\Application\Actor;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Application\Genres;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Infrastructure\Form\EditorsType;
use Phil\DirectoryBundle\Infrastructure\Form\ListingType;
use Phil\DirectoryBundle\Infrastructure\Routing\DirectoryRoutes;
use Phil\DirectoryBundle\Infrastructure\Security\Actors;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The pages of a genre: its listings, a listing, the forms that add and change one, its editors and who finds it, its
 * history, and the publication of its draft. The genre comes with the route, from `DirectoryRoutes`.
 *
 * The page of a listing its author withdrew from search engines tells robots `noindex, noarchive`, in its
 * `X-Robots-Tag` header and its `<meta name="robots">`, and carries the choice under
 * `IndexingPolicy::REQUEST_ATTRIBUTE` for the structured data of the page.
 */
final readonly class DirectoryController
{
    public function __construct(
        private Directory $directory,
        private Genres $genres,
        private Actors $actors,
        private FormFactoryInterface $forms,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private int $pageSize = 50,
    ) {
    }

    /**
     * A page of the listings of a genre, `?page=2` and after; a page past the last, or a page that is not a number from
     * 1 to 999999999, is not found.
     */
    public function index(string $genre, Request $request): Response
    {
        $number = $request->query->getString('page', '1');

        if (1 !== preg_match('/^[1-9]\d{0,8}$/D', $number)) {
            throw new NotFoundHttpException(\sprintf('"%s" is not a page of the genre "%s".', $number, $genre));
        }

        $page = (int) $number;
        $listings = $this->directory->published($genre, $request->getLocale(), ($page - 1) * $this->pageSize, $this->pageSize + 1);

        if ($page > 1 && [] === $listings) {
            throw new NotFoundHttpException(\sprintf('The genre "%s" has no page %d.', $genre, $page));
        }

        return $this->render('index', [
            'genre' => $this->genres->get($genre),
            'listings' => \array_slice($listings, 0, $this->pageSize),
            'page' => $page,
            'next' => \count($listings) > $this->pageSize,
        ]);
    }

    public function show(string $genre, string $slug, Request $request): Response
    {
        $page = $this->directory->find($genre, $slug, $request->getLocale()) ?? throw new NotFoundHttpException(\sprintf('The genre "%s" has no listing "%s".', $genre, $slug));

        if (null !== $page->movedTo) {
            return new RedirectResponse($this->route($genre, 'show', ['slug' => $page->movedTo]), Response::HTTP_MOVED_PERMANENTLY);
        }

        // The choice of the author is carried by the request, for whatever else reads the page: its structured data.
        $indexing = $page->listing->indexing();
        $request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, $indexing);
        $response = $this->render('show', [
            'genre' => $this->genres->get($genre),
            'page' => $page,
            'listing' => $page->listing,
            'data' => $page->data,
            'related' => $this->directory->related($page->reference, $request->getLocale()),
            'robots' => $indexing->robots(),
        ]);

        if (null !== $indexing->robots()) {
            $response->headers->set('X-Robots-Tag', $indexing->robots());
        }

        return $response;
    }

    public function new(string $genre, Request $request): Response
    {
        $actor = $this->signedIn();
        $form = $this->forms->create(ListingType::class, null, ['relations' => $this->relations($genre, $request->getLocale())])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reference = $this->directory->create($genre, $request->getLocale(), ListingType::toContent($this->data($form)), $actor);

                return new RedirectResponse($this->route($genre, 'edit', ['id' => $reference->resourceId]));
            } catch (DomainError $refused) {
                $form->addError(new FormError($refused->getMessage()));
            }
        }

        return $this->render('form', ['genre' => $this->genres->get($genre), 'form' => $form->createView(), 'listing' => null], $form);
    }

    public function edit(string $genre, string $id, Request $request): Response
    {
        [$reference, $listing] = $this->listing($genre, $id);
        $actor = $this->actors->current();

        if (!$this->directory->mayEdit($listing, $actor)) {
            throw new AccessDeniedHttpException('Only the author of a listing, its editors and the administrators change it.');
        }

        $draft = $this->draft($reference, $request->getLocale());
        $form = $this->forms->create(ListingType::class, ListingType::fromContent($draft), ['relations' => $this->relations($genre, $request->getLocale(), $id, \is_array($draft['related'] ?? null) ? $draft['related'] : [])])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->directory->edit($reference, $request->getLocale(), ListingType::toContent($this->data($form)), $actor);

                return new RedirectResponse($this->route($genre, 'edit', ['id' => $id]));
            } catch (DomainError $refused) {
                $form->addError(new FormError($refused->getMessage()));
            }
        }

        return $this->render('form', ['genre' => $this->genres->get($genre), 'form' => $form->createView(), 'listing' => $listing, 'id' => $id], $form);
    }

    public function editors(string $genre, string $id, Request $request): Response
    {
        [$reference, $listing] = $this->listing($genre, $id);
        $actor = $this->actors->current();

        if (!$this->directory->mayManageEditors($listing, $actor)) {
            throw new AccessDeniedHttpException('Only the author of a listing and the administrators name its editors.');
        }

        $form = $this->forms->create(EditorsType::class, null, ['editors' => $listing->editors(), 'indexing' => $listing->indexing()])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->changeEditors($reference, $this->data($form), $actor);

                return new RedirectResponse($this->route($genre, 'editors', ['id' => $id]));
            } catch (DomainError $refused) {
                $form->addError(new FormError($refused->getMessage()));
            }
        }

        return $this->render('editors', ['genre' => $this->genres->get($genre), 'form' => $form->createView(), 'listing' => $listing, 'id' => $id], $form);
    }

    public function history(string $genre, string $id, Request $request): Response
    {
        [$reference, $listing] = $this->listing($genre, $id);

        if (!$this->directory->mayEdit($listing, $this->actors->current())) {
            throw new AccessDeniedHttpException('Only the author of a listing, its editors and the administrators read its history.');
        }

        $publish = $this->publishForm();

        return $this->render('history', [
            'genre' => $this->genres->get($genre),
            'listing' => $listing,
            'id' => $id,
            'versions' => $this->directory->history($reference, $request->getLocale()),
            'publish' => $publish->createView(),
        ]);
    }

    public function publish(string $genre, string $id, Request $request): RedirectResponse
    {
        [$reference] = $this->listing($genre, $id);
        $form = $this->publishForm()->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new AccessDeniedHttpException('A publication is asked by the form of the history.');
        }

        try {
            $this->directory->publish($reference, $request->getLocale(), $this->actors->current());
        } catch (DomainError $domainError) {
            throw new AccessDeniedHttpException($domainError->getMessage(), $domainError);
        }

        return new RedirectResponse($this->route($genre, 'history', ['id' => $id]));
    }

    /**
     * The choices of each relation of a genre: the published listings of its other genre, titled, the listing itself
     * left out; and the listings it already names that are not published, titled by their draft, so that saving the
     * form keeps them.
     *
     * @param array<array-key, mixed> $stored the related listings of the draft
     *
     * @return list<array{key: string, title: string, multiple: bool, choices: array<string, string>}>
     */
    private function relations(string $genre, string $locale, ?string $self = null, array $stored = []): array
    {
        $fields = [];

        foreach ($this->directory->relationsFrom($genre) as $relation) {
            $choices = [];

            foreach ($this->directory->published($relation->to, $locale) as $content) {
                if ($content->reference->resourceId !== $self) {
                    $choices[$content->reference->resourceId] = $content->title();
                }
            }

            foreach (\is_array($stored[$relation->key] ?? null) ? $stored[$relation->key] : [] as $id) {
                if (\is_string($id) && !isset($choices[$id])) {
                    $title = $this->draft(ContentReference::of(Directory::RESOURCE_KEY, $id), $locale)['excerptTitle'] ?? null;
                    $choices[$id] = \is_string($title) ? $title : $id;
                }
            }

            $fields[] = ['key' => $relation->key, 'title' => $relation->title, 'multiple' => $relation->multiple, 'choices' => $choices];
        }

        return $fields;
    }

    /**
     * The form that asks for the publication of the latest version: a confirmation, protected against forgery when the
     * application protects its forms.
     *
     * @return FormInterface<mixed>
     */
    private function publishForm(): FormInterface
    {
        return $this->forms->createNamedBuilder('publish', FormType::class)->add('confirm', HiddenType::class, ['data' => '1'])->getForm();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function changeEditors(ContentReference $reference, array $data, Actor $actor): void
    {
        $add = $data['add'] ?? null;
        $remove = $data['remove'] ?? [];
        $indexing = $data['indexing'] ?? null;

        if ($indexing instanceof Indexing && $indexing !== $this->directory->listing($reference)->indexing()) {
            $this->directory->changeIndexing($reference, $indexing, $actor);
        }

        if (\is_string($add) && '' !== trim($add)) {
            $this->directory->addEditor($reference, trim($add), $actor);
        }

        foreach (\is_array($remove) ? $remove : [] as $editor) {
            if (\is_string($editor)) {
                $this->directory->removeEditor($reference, $editor, $actor);
            }
        }
    }

    /**
     * @return array{ContentReference, Listing}
     */
    private function listing(string $genre, string $id): array
    {
        try {
            $reference = ContentReference::of(Directory::RESOURCE_KEY, $id);
            $listing = $this->directory->listing($reference);
        } catch (DomainError $domainError) {
            throw new NotFoundHttpException($domainError->getMessage(), $domainError);
        }

        if ($listing->genre() !== $this->genres->get($genre)->key) {
            throw new NotFoundHttpException(\sprintf('The genre "%s" has no listing %s.', $genre, $id));
        }

        return [$reference, $listing];
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(ContentReference $reference, string $locale): array
    {
        try {
            return $this->directory->draft($reference, $locale);
        } catch (DomainError) {
            return [];
        }
    }

    private function signedIn(): Actor
    {
        $actor = $this->actors->current();

        return null === $actor->user ? throw new AccessDeniedHttpException('Only someone signed in writes a listing.') : $actor;
    }

    /**
     * @template T of array<string, mixed>|null
     *
     * @param FormInterface<T> $form
     *
     * @return array<string, mixed>
     */
    private function data(FormInterface $form): array
    {
        return $form->getData() ?? [];
    }

    /**
     * @param array<string, string> $parameters
     */
    private function route(string $genre, string $action, array $parameters = []): string
    {
        return $this->urls->generate(DirectoryRoutes::name($genre, $action), $parameters);
    }

    /**
     * @template T
     *
     * @param array<string, mixed> $context
     * @param ?FormInterface<T>    $form
     */
    private function render(string $page, array $context, ?FormInterface $form = null): Response
    {
        $status = $form instanceof FormInterface && $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return new Response($this->twig->render('@PhilDirectory/'.$page.'.html.twig', $context), $status);
    }
}
