<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Http;

use JsonException;
use Phil\EmailTemplatesBundle\Application\CatalogRow;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\Preview\PreviewVariant;
use Phil\EmailTemplatesBundle\Domain\Exception\EmailTemplatesException;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDraft;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
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
 * The administration of the templates, for the role `phil_email_templates.admin.role`:
 *
 * - **the list** of every template the site renders, by key and locale, and where each comes from;
 * - **the page of a template** in a locale: the form of its draft — subject, preheader, format, body, plain-text part,
 *   segments, variable schema, embedded images —, saved once it passes the checks; its publication, the changes
 *   abandoned, the locale taken offline so that the shared template or the default file is sent again; its published
 *   versions, each to copy into the draft; and the preview of the draft, one frame per variant;
 * - **the frame** of a variant: the email rendered with its sample values, served with a policy that runs no script
 *   and loads nothing but images, in an `<iframe sandbox>` of the page.
 *
 * The forms carry the stateless CSRF token `phil_email_templates_admin`; a page acts for the current site, or for the
 * shared scope with `?scope=shared`. Every response is `no-store`.
 */
final readonly class AdminController
{
    public const string INDEX_ROUTE = 'phil_email_templates_admin_index';

    public const string EDIT_ROUTE = 'phil_email_templates_admin_edit';

    public const string FRAME_ROUTE = 'phil_email_templates_admin_frame';

    public const string TOKEN_ID = 'phil_email_templates_admin';

    /** The policy of a frame: no script, no form, no frame, no request but the images; styles inline only. */
    public const string FRAME_POLICY = "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src https: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox";

    /** The largest variable schema or list of images accepted from the form, in bytes of JSON. */
    private const int MAX_JSON_BYTES = 65_536;

    public function __construct(
        private EmailTemplates $templates,
        private PreviewRenderer $previews,
        private SiteScope $sites,
        private AuthorizationCheckerInterface $authorization,
        private TokenStorageInterface $tokens,
        private CsrfTokenManagerInterface $csrf,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $role,
        private string $layout = '@PhilEmailTemplates/admin/layout.html.twig',
    ) {
    }

    public function index(Request $request): Response
    {
        $this->denyUnlessGranted();
        $site = $this->site($request);
        $keys = [];

        foreach ($this->templates->catalog($site) as $row) {
            $keys[$row->key][] = $row;
        }

        return $this->render('index', ['keys' => $keys, 'site' => $site, 'shared' => $site->isShared(), 'scope' => $this->scope($request)]);
    }

    public function edit(Request $request, string $key, string $locale): Response
    {
        $this->denyUnlessGranted();
        $site = $this->site($request);
        [$template, $language] = $this->address($key, $locale);

        if (!$request->isMethod('POST')) {
            return $this->page($request, $template, $language, $site, null, [], $request->query->getString('done'));
        }

        if (!$this->csrf->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The form has expired: reload the page and send it again.');
        }

        $expected = $request->request->getInt('expected');
        $expected = max($expected, 0);

        $author = $this->author();

        try {
            $done = match ($request->request->getString('action')) {
                'save' => $this->save($request, $template, $language, $site, $expected, $author),
                'publish' => $this->publish($template, $language, $site, $author),
                'discard' => $this->discard($template, $language, $site, $author),
                'revert' => $this->revert($request, $template, $language, $site, $expected, $author),
                'reset' => $this->reset($template, $language, $site, $author),
                default => throw new BadRequestHttpException('The action is "save", "publish", "discard", "revert" or "reset".'),
            };
        } catch (InvalidTemplate $invalidTemplate) {
            return $this->page($request, $template, $language, $site, $request, $invalidTemplate->errors, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (VersionConflict $versionConflict) {
            return $this->page($request, $template, $language, $site, $request, [$versionConflict->getMessage()], null, Response::HTTP_CONFLICT);
        } catch (UnknownTemplate $unknownTemplate) {
            throw new NotFoundHttpException($unknownTemplate->getMessage(), $unknownTemplate);
        }

        $parameters = ['key' => $template->value, 'locale' => $language->value, 'done' => $done];

        if ('shared' === $this->scope($request)) {
            $parameters['scope'] = 'shared';
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, $parameters), Response::HTTP_SEE_OTHER, ['Cache-Control' => 'no-store']);
    }

    public function frame(Request $request, string $key, string $locale): Response
    {
        $this->denyUnlessGranted();
        $site = $this->site($request);
        [$template, $language] = $this->address($key, $locale);
        $segments = array_values(array_filter(explode(',', $request->query->getString('segments')), static fn (string $segment): bool => '' !== $segment));
        $version = $request->query->has('version') ? $request->query->getInt('version') : null;

        try {
            $email = $this->previews->render(new PreviewVariant($template, $site, $language, $segments, null === $version || $version < 1 ? null : $version, '1' === $request->query->getString('draft')));
            $html = $email->htmlForBrowser();
            $status = Response::HTTP_OK;
        } catch (UnknownTemplate $unknownTemplate) {
            throw new NotFoundHttpException($unknownTemplate->getMessage(), $unknownTemplate);
        } catch (EmailTemplatesException $exception) {
            $html = '<!doctype html><meta charset="utf-8"><p>'.htmlspecialchars($exception->getMessage(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'</p>';
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return new Response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => self::FRAME_POLICY,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * @param int<0, max> $expected
     */
    private function save(Request $request, TemplateKey $key, Locale $locale, Site $site, int $expected, ?string $author): string
    {
        $this->templates->save($key, $locale->value, $this->definition($request), $expected, $site, $author);

        return 'saved';
    }

    private function publish(TemplateKey $key, Locale $locale, Site $site, ?string $author): string
    {
        $this->templates->publish($key, $locale->value, $site, $author);

        return 'published';
    }

    private function discard(TemplateKey $key, Locale $locale, Site $site, ?string $author): string
    {
        $this->templates->discard($key, $locale->value, $site, $author);

        return 'discarded';
    }

    /**
     * @param int<0, max> $expected
     */
    private function revert(Request $request, TemplateKey $key, Locale $locale, Site $site, int $expected, ?string $author): string
    {
        $version = $request->request->getInt('version');

        if ($version < 1) {
            throw new BadRequestHttpException('The version to revert to is a whole number from 1.');
        }

        $this->templates->revert($key, $locale->value, $version, $expected, $site, $author);

        return 'reverted';
    }

    private function reset(TemplateKey $key, Locale $locale, Site $site, ?string $author): string
    {
        $this->templates->reset($key, $locale->value, $site, $author);

        return 'reset';
    }

    /**
     * @param list<string> $errors
     */
    private function page(Request $request, TemplateKey $key, Locale $locale, Site $site, ?Request $submitted, array $errors, ?string $done, int $status = Response::HTTP_OK): Response
    {
        try {
            $current = $this->templates->current($key, $locale->value, $site);
            $draft = $this->templates->draft($key, $locale->value, $site);
            $variants = $this->previews->variants($key, $site, $locale, $draft instanceof TemplateDraft);
        } catch (InvalidTemplate|UnknownTemplate $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $history = $this->templates->history($key, $locale->value, $site);
        $previews = [];

        foreach ($variants as $variant) {
            try {
                $email = $this->previews->render($variant);
                $previews[] = ['variant' => $variant, 'email' => $email, 'error' => null];
            } catch (EmailTemplatesException $exception) {
                $previews[] = ['variant' => $variant, 'email' => null, 'error' => $exception->getMessage()];
            }
        }

        $locales = array_values(array_unique(array_map(static fn (CatalogRow $row): string => $row->locale, array_filter($this->templates->catalog($site), static fn (CatalogRow $row): bool => $row->key === $key->value))));

        return $this->render('edit', [
            'key' => $key->value,
            'locale' => $locale->value,
            'locales' => $locales,
            'site' => $site,
            'scope' => $this->scope($request),
            'current' => $current,
            'draft' => $draft,
            'expected' => $draft->revision ?? 0,
            'form' => $submitted instanceof Request ? $this->submitted($submitted) : $this->fields($draft->definition ?? $current->definition),
            'formats' => array_map(static fn (BodyFormat $format): string => $format->value, BodyFormat::cases()),
            'history' => array_map(static fn (TemplateVersion $version): array => ['version' => $version->version, 'saved_at' => $version->savedAt, 'published_at' => $version->publishedAt, 'author' => $version->author], $history),
            'previews' => $previews,
            'errors' => $errors,
            'done' => \in_array($done, ['saved', 'published', 'discarded', 'reverted', 'reset'], true) ? $done : null,
            'inherited' => TemplateOrigin::Site !== $current->origin && !$site->isShared(),
        ], $status);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $page, array $parameters, int $status = Response::HTTP_OK): Response
    {
        $parameters['layout'] = $this->layout;
        $parameters['csrf_token'] = $this->csrf->getToken(self::TOKEN_ID)->getValue();

        return new Response($this->twig->render('@PhilEmailTemplates/admin/'.$page.'.html.twig', $parameters), $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * @return array{TemplateKey, Locale}
     */
    private function address(string $key, string $locale): array
    {
        try {
            return [TemplateKey::fromString($key), Locale::fromString($locale)];
        } catch (InvalidTemplate $invalidTemplate) {
            throw new NotFoundHttpException($invalidTemplate->getMessage(), $invalidTemplate);
        }
    }

    private function site(Request $request): Site
    {
        return 'shared' === $this->scope($request) ? Site::shared() : $this->sites->current();
    }

    private function scope(Request $request): string
    {
        return 'shared' === $request->query->getString('scope') || 'shared' === $request->request->getString('scope') ? 'shared' : 'site';
    }

    /**
     * The definition the form describes.
     *
     * @throws InvalidTemplate when a field is out of shape
     */
    private function definition(Request $request): TemplateDefinition
    {
        $form = $this->submitted($request);
        $errors = [];
        $variables = $this->json($form['variables'], 'variable schema', $errors);
        $embeds = $this->json($form['embeds'], 'list of embedded images', $errors);

        if ([] !== $errors) {
            throw InvalidTemplate::withErrors($errors);
        }

        return TemplateDefinition::fromArray([
            'subject' => $form['subject'],
            'preheader' => $form['preheader'],
            'format' => '' === $form['format'] ? BodyFormat::Html->value : $form['format'],
            'body' => $form['body'],
            'text' => $form['text'],
            'segments' => array_values(array_filter(array_map(trim(...), explode(',', $form['segments'])), static fn (string $segment): bool => '' !== $segment)),
            'embeds' => $embeds,
            'variables' => $variables,
        ]);
    }

    /**
     * @param list<string> $errors
     *
     * @return array<array-key, mixed>
     */
    private function json(string $text, string $what, array &$errors): array
    {
        if ('' === trim($text)) {
            return [];
        }

        if (\strlen($text) > self::MAX_JSON_BYTES) {
            $errors[] = \sprintf('The %s is at most %d KiB of JSON.', $what, intdiv(self::MAX_JSON_BYTES, 1024));

            return [];
        }

        try {
            $value = json_decode($text, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $errors[] = \sprintf('The %s is not valid JSON: %s.', $what, $jsonException->getMessage());

            return [];
        }

        if (!\is_array($value)) {
            $errors[] = \sprintf('The %s is a JSON object.', $what);

            return [];
        }

        return $value;
    }

    /**
     * @return array{subject: string, preheader: string, format: string, body: string, text: string, segments: string, variables: string, embeds: string}
     */
    private function submitted(Request $request): array
    {
        $fields = [];

        foreach (['subject', 'preheader', 'format', 'body', 'text', 'segments', 'variables', 'embeds'] as $field) {
            $fields[$field] = $request->request->getString($field);
        }

        return $fields;
    }

    /**
     * @return array{subject: string, preheader: string, format: string, body: string, text: string, segments: string, variables: string, embeds: string}
     */
    private function fields(TemplateDefinition $definition): array
    {
        $flags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

        return [
            'subject' => $definition->subject,
            'preheader' => $definition->preheader,
            'format' => $definition->format->value,
            'body' => $definition->body,
            'text' => $definition->text ?? '',
            'segments' => implode(', ', $definition->segments),
            'variables' => [] === $definition->schema->variables ? '' : json_encode($definition->schema->toArray(), $flags),
            'embeds' => [] === $definition->embeds ? '' : json_encode($definition->embeds, $flags),
        ];
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->authorization->isGranted($this->role)) {
            throw new AccessDeniedException(\sprintf('The email templates are administered with the role "%s".', $this->role));
        }
    }

    private function author(): ?string
    {
        return $this->tokens->getToken()?->getUserIdentifier();
    }
}
