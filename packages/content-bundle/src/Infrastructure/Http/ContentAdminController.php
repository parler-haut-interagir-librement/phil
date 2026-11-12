<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Contract\ConversionLosses;
use Phil\ContentBundle\Application\Contract\PublicationSchedule;
use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Phil\ContentBundle\Application\Exception\PublicationLosesContent;
use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The administration of the contents, reached from an EasyAdmin back office (`ContentAdmin::menuItems()`): every page
 * goes through the bundle's application services — `ContentManager`, `WorkingCopies`, `PublicationSchedule` —, so the
 * administration publishes, refuses and dispatches exactly as the console and PHP do.
 *
 * - **The list** shows each kind of `phil_content.roots` with its contents and, per locale, the place of the draft,
 *   its version and the version readers are served.
 * - **The editing page** of one content in one locale edits the draft's fields — those its capabilities give —,
 *   publishes it, takes it offline, discards its pending changes, schedules its publication when a schedule is
 *   installed, keeps and applies named drafts, and restores the author's autosave. A publication that loses content
 *   is refused with the list of what it loses, until the editor ticks « accept the losses ».
 * - **The autosave** endpoint keeps what the editing form holds for its author alone; the page's Stimulus controller
 *   posts it once the typing stops.
 *
 * Every page asks the role `phil_content.admin.role`; every form carries a CSRF token. The pages extend
 * `phil_content.admin.layout`, which the application points to a template of its back office.
 */
final readonly class ContentAdminController
{
    public const string LIST_ROUTE = 'phil_content_admin_list';

    public const string CREATE_ROUTE = 'phil_content_admin_create';

    public const string EDIT_ROUTE = 'phil_content_admin_edit';

    public const string AUTOSAVE_ROUTE = 'phil_content_admin_autosave';

    public const string CSRF_TOKEN_ID = 'phil_content_admin';

    public const string MOMENT_FORMAT = 'Y-m-d\TH:i';

    /**
     * The text fields of the editing form, in their order, each shown when the draft has the key.
     */
    public const array TEXT_FIELDS = ['templateKey', 'slug', 'excerptTitle', 'excerptDescription', 'seoTitle', 'seoDescription', 'seoCanonicalUrl', 'bodyFormat', 'bodySource'];

    /**
     * The yes-or-no fields of the editing form, each shown when the draft has the key.
     */
    public const array FLAG_FIELDS = ['seoNoIndex', 'seoNoFollow', 'seoHideInSitemap'];

    /**
     * @param list<string> $resourceKeys the kinds of `phil_content.roots`
     */
    public function __construct(
        private ContentManager $contents,
        private WorkingCopies $copies,
        private AuthorizationCheckerInterface $authorizationChecker,
        private TokenStorageInterface $tokens,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private array $resourceKeys,
        private string $role,
        private string $layout,
        private string $timezone = 'UTC',
        private ?ConversionLosses $losses = null,
        private ?PublicationSchedule $schedule = null,
    ) {
    }

    public function list(): Response
    {
        $this->denyUnlessGranted();
        $kinds = [];

        foreach ($this->resourceKeys as $resourceKey) {
            $kinds[$resourceKey] = array_map(fn (ContentReference $reference): array => ['reference' => $reference, 'statuses' => $this->contents->status($reference)], $this->contents->list($resourceKey));
        }

        return $this->render('list', ['kinds' => $kinds]);
    }

    public function create(Request $request, string $resourceKey): Response
    {
        $this->denyUnlessGranted();

        if (!\in_array($resourceKey, $this->resourceKeys, true)) {
            throw new NotFoundHttpException(\sprintf('"%s" is not a kind of content.', $resourceKey));
        }

        $locale = $request->request->getString('locale');
        $slug = trim($request->request->getString('slug'));

        if (!$request->isMethod('POST')) {
            return $this->render('create', ['resource_key' => $resourceKey, 'locale' => $locale, 'slug' => $slug, 'error' => null], form: true);
        }

        $this->guardForm($request);

        try {
            $reference = $this->contents->create($resourceKey, $locale, '' === $slug ? [] : ['slug' => $slug]);
        } catch (DomainError $domainError) {
            return $this->render('create', ['resource_key' => $resourceKey, 'locale' => $locale, 'slug' => $slug, 'error' => $domainError->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY, true);
        }

        return $this->seeOther(self::EDIT_ROUTE, ['reference' => $reference->toString(), 'locale' => $locale]);
    }

    public function edit(Request $request, string $reference, string $locale): Response
    {
        $this->denyUnlessGranted();
        $content = $this->reference($reference);

        if (!$request->isMethod('POST')) {
            return $this->page($content, $locale, null, null, $request->query->getString('done'));
        }

        $this->guardForm($request);
        $action = $request->request->getString('action', 'save');
        $data = $this->submitted($request);

        try {
            $done = $this->apply($action, $content, $locale, $data, $request);
        } catch (PublicationLosesContent $publicationLosesContent) {
            return $this->page($content, $locale, $data, $publicationLosesContent, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DomainError $domainError) {
            return $this->page($content, $locale, $data, $domainError, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->seeOther(self::EDIT_ROUTE, ['reference' => $content->toString(), 'locale' => $locale, 'done' => $done]);
    }

    /**
     * Keeps the editing form of the author at work as their autosave: answered 204, or 422 with the reason.
     */
    public function autosave(Request $request, string $reference, string $locale): Response
    {
        $this->denyUnlessGranted();
        $this->guardForm($request);

        try {
            $this->copies->autosave($this->reference($reference), $locale, $this->author(), $this->submitted($request));
        } catch (DomainError $domainError) {
            return new Response($domainError->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }

        return new Response(null, Response::HTTP_NO_CONTENT, ['Cache-Control' => 'no-store']);
    }

    /**
     * Does what the button pressed asks, and names what was done.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainError
     */
    private function apply(string $action, ContentReference $content, string $locale, array $data, Request $request): string
    {
        $author = $this->author();
        $name = trim($request->request->getString('name'));

        switch ($action) {
            case 'save':
                $this->contents->edit($content, $locale, $data);
                $this->copies->discardAutosave($content, $locale, $author);

                return 'saved';
            case 'publish':
                $this->contents->edit($content, $locale, $data);
                $this->copies->discardAutosave($content, $locale, $author);
                $this->contents->publish($content, $locale, '1' === $request->request->getString('accept_losses'));

                return 'published';
            case 'unpublish':
                $this->contents->unpublish($content, $locale);

                return 'unpublished';
            case 'remove_draft':
                $this->contents->removeDraft($content, $locale);

                return 'draft_removed';
            case 'schedule':
                $this->scheduled()->schedule(Publication::of($content, $locale), $request->request->getString('transition'), $this->moment($request->request->getString('at')));

                return 'scheduled';
            case 'cancel_schedule':
                $this->scheduled()->cancel(Publication::of($content, $locale));

                return 'schedule_cancelled';
            case 'save_named':
                $this->copies->saveNamed($content, $locale, $name, $author, $data);

                return 'named_saved';
            case 'apply_named':
                $this->copies->applyNamed($content, $locale, $name);

                return 'named_applied';
            case 'delete_named':
                $this->copies->deleteNamed($content, $locale, $name);

                return 'named_deleted';
            case 'apply_autosave':
                $this->copies->applyAutosave($content, $locale, $author);

                return 'autosave_applied';
            case 'discard_autosave':
                $this->copies->discardAutosave($content, $locale, $author);

                return 'autosave_discarded';
            default:
                throw new BadRequestHttpException(\sprintf('"%s" is not an action of the editing page.', mb_substr($action, 0, 40)));
        }
    }

    /**
     * The editing page: the draft, or what was submitted when it was refused, with the reason.
     *
     * @param array<string, mixed>|null $submitted
     */
    private function page(ContentReference $content, string $locale, ?array $submitted, ?DomainError $error, ?string $done, int $status = Response::HTTP_OK): Response
    {
        try {
            $draft = $this->contents->draft($content, $locale);
        } catch (ContentNotFound) {
            $draft = [];
        } catch (DomainError $domainError) {
            throw new NotFoundHttpException($domainError->getMessage(), $domainError);
        }

        $values = [...$draft, ...$submitted ?? []];
        $source = $values['bodySource'] ?? null;
        $format = $values['bodyFormat'] ?? null;

        return $this->render('edit', [
            'reference' => $content,
            'locale' => $locale,
            'statuses' => $this->contents->status($content),
            'values' => $values,
            'text_fields' => array_values(array_filter(self::TEXT_FIELDS, static fn (string $field): bool => \array_key_exists($field, $draft))),
            'flag_fields' => array_values(array_filter(self::FLAG_FIELDS, static fn (string $field): bool => \array_key_exists($field, $draft))),
            'losses' => $error instanceof PublicationLosesContent ? $error->losses : $this->lossesOf($source, $format),
            'error' => $error?->getMessage(),
            'done' => '' === $done ? null : $done,
            'named' => $this->copies->named($content, $locale),
            'autosaved' => $this->copies->autosaved($content, $locale, $this->author()),
            'scheduling' => $this->schedule instanceof PublicationSchedule,
            'pending' => $this->schedule?->pending(Publication::of($content, $locale)) ?? [],
            'timezone' => $this->timezone,
        ], $status, true);
    }

    /**
     * What the conversion of the body into UJO would lose, shown before publishing.
     *
     * @return list<ConversionLoss>
     */
    private function lossesOf(mixed $source, mixed $format): array
    {
        if (!$this->losses instanceof ConversionLosses || !\is_string($source) || !\is_string($format) || '' === $source) {
            return [];
        }

        try {
            return $this->losses->of($source, $format);
        } catch (InvalidContent) {
            return [];
        }
    }

    /**
     * The fields of the editing form: the text fields as typed, a flag ticked or not; a text field left empty clears.
     *
     * @return array<string, mixed>
     */
    private function submitted(Request $request): array
    {
        $fields = $request->request->all('content');
        $data = [];

        foreach (self::TEXT_FIELDS as $field) {
            if (\array_key_exists($field, $fields) && \is_string($fields[$field])) {
                $value = 'bodySource' === $field ? $fields[$field] : trim($fields[$field]);
                $data[$field] = '' === $value ? null : $value;
            }
        }

        foreach (self::FLAG_FIELDS as $field) {
            if (\array_key_exists($field, $fields)) {
                $data[$field] = '1' === $fields[$field];
            }
        }

        return $data;
    }

    private function scheduled(): PublicationSchedule
    {
        return $this->schedule ?? throw InvalidContent::because('No schedule is installed: phil/scheduled-transitions-bundle publishes at a set moment.');
    }

    /**
     * @throws InvalidContent
     */
    private function moment(string $moment): DateTimeImmutable
    {
        $zone = new DateTimeZone($this->timezone);

        try {
            $parsed = DateTimeImmutable::createFromFormat('!'.self::MOMENT_FORMAT, trim($moment), $zone);
        } catch (Exception) {
            $parsed = false;
        }

        if (!$parsed instanceof DateTimeImmutable || $parsed->format(self::MOMENT_FORMAT) !== trim($moment)) {
            throw InvalidContent::because(\sprintf('"%s" is not a moment of the form 2026-12-01T08:00.', mb_substr($moment, 0, 40)));
        }

        return $parsed;
    }

    private function reference(string $reference): ContentReference
    {
        try {
            $content = ContentReference::fromString($reference);
        } catch (InvalidContent $invalidContent) {
            throw new NotFoundHttpException($invalidContent->getMessage(), $invalidContent);
        }

        if (!$this->contents->exists($content)) {
            throw new NotFoundHttpException(\sprintf('The content "%s" does not exist.', $content->toString()));
        }

        return $content;
    }

    /**
     * The person at work, by the identifier of their security token: their autosave is theirs alone.
     */
    private function author(): string
    {
        $identifier = $this->tokens->getToken()?->getUserIdentifier() ?? '';

        if ('' === $identifier) {
            throw new AccessDeniedException('The administration of the contents needs a signed-in user.');
        }

        return $identifier;
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$this->role]);

            throw $exception;
        }
    }

    private function guardForm(Request $request): void
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $status = Response::HTTP_OK, bool $form = false): Response
    {
        $context['layout'] = $this->layout;

        if ($form) {
            $context['csrf_token'] = $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue();
        }

        return new Response($this->twig->render('@PhilContent/admin/'.$template.'.html.twig', $context), $status, ['Cache-Control' => 'no-store']);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function seeOther(string $route, array $parameters): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER);
    }
}
