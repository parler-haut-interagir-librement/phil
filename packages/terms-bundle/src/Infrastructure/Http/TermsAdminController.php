<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Http;

use DateTimeImmutable;
use DateTimeZone;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The administration of the terms: the policies with their versions, and the form that publishes a new version — its
 * text in each locale of the configuration, its format, the moment it takes effect in the time zone of the
 * configuration, and whether it is material, unticked by default. A version once published is never changed here.
 */
final readonly class TermsAdminController
{
    public const string LIST_ROUTE = 'phil_terms_admin_list';

    public const string PUBLISH_ROUTE = 'phil_terms_admin_publish';

    public const string MOMENT_FORMAT = 'Y-m-d\TH:i';

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private Pages $pages,
        private Policies $policies,
        private VersionCatalog $catalog,
        private Terms $terms,
        private AuthorizationCheckerInterface $authorizationChecker,
        private string $role,
        private string $layout,
        private array $locales,
        private string $timezone,
        private bool $ujo,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->denyUnlessGranted();

        return $this->pages->render('admin/list', [
            'policies' => array_map(fn (Policy $policy): array => ['policy' => $policy, 'versions' => $this->catalog->versions($policy->key)], $this->policies->all()),
            'published' => 'published' === $request->query->getString('done'),
            'timezone' => $this->timezone,
        ], layout: $this->layout);
    }

    public function publish(Request $request, string $policy): Response
    {
        $this->denyUnlessGranted();

        if (!$this->policies->has($policy)) {
            return $this->pages->seeOther(self::LIST_ROUTE);
        }

        $draft = [
            'texts' => $this->texts($request->request->all('texts')),
            'format' => $request->request->getString('format', TextFormat::Text->value),
            'effective_at' => trim($request->request->getString('effective_at')),
            'material' => '1' === $request->request->getString('material'),
            'licence' => trim($request->request->getString('licence')),
        ];

        if (!$request->isMethod('POST')) {
            return $this->form($policy, $draft, null);
        }

        $this->pages->guardForm($request);

        try {
            $this->terms->publish($policy, $draft['texts'], $this->format($draft['format']), $this->moment($draft['effective_at']), $draft['material'], '' === $draft['licence'] ? null : $draft['licence']);
        } catch (InvalidTerms $invalidTerms) {
            return $this->form($policy, $draft, $invalidTerms);
        }

        return $this->pages->seeOther(self::LIST_ROUTE, ['done' => 'published']);
    }

    /**
     * @param array{texts: array<string, string>, format: string, effective_at: string, material: bool, licence: string} $draft
     */
    private function form(string $policy, array $draft, ?InvalidTerms $error): Response
    {
        return $this->pages->form('admin/publish', [
            'policy' => $policy,
            'licensing' => $this->policies->get($policy)->contributionLicence,
            'draft' => $draft,
            'locales' => $this->locales,
            'formats' => $this->ujo ? [TextFormat::Text->value, TextFormat::Ujo->value] : [TextFormat::Text->value],
            'timezone' => $this->timezone,
            'next' => ($this->catalog->latest($policy)->number ?? 0) + 1,
            'error' => $error instanceof InvalidTerms ? ['reason' => $error->reason, 'parameters' => $error->parameters] : null,
        ], $error instanceof InvalidTerms ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK, $this->layout);
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$this->role]);

            throw $exception;
        }
    }

    /**
     * The texts typed, by locale of the configuration: an empty one is left out.
     *
     * @param array<array-key, mixed> $submitted
     *
     * @return array<string, string>
     */
    private function texts(array $submitted): array
    {
        $texts = [];

        foreach ($this->locales as $locale) {
            $text = $submitted[$locale] ?? null;

            if (\is_string($text) && '' !== trim($text)) {
                $texts[$locale] = $text;
            }
        }

        return $texts;
    }

    private function format(string $format): TextFormat
    {
        return TextFormat::tryFrom($format) ?? throw InvalidTerms::because('format', 'The format is "text" or "ujo".');
    }

    private function moment(string $moment): ?DateTimeImmutable
    {
        if ('' === $moment) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(self::MOMENT_FORMAT, $moment, new DateTimeZone($this->timezone));

        if (false === $parsed || $parsed->format(self::MOMENT_FORMAT) !== $moment) {
            throw InvalidTerms::because('effective_at', 'The moment it takes effect reads as a date and a time.');
        }

        return $parsed;
    }
}
